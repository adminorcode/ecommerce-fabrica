import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { launchBrowser, routeCanonicalNavigation, createEvidenceDirectory } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidence = createEvidenceDirectory('041-cart-consistency');
const browser = await launchBrowser();
const checks = [];
const pages = [];
const fixturePath = path.resolve('.local/041-browser-fixture.json');
const account = fs.existsSync(fixturePath) ? JSON.parse(fs.readFileSync(fixturePath, 'utf8')) : null;
try {
  assert(account?.login && account?.password, 'Authenticated 041 fixture required; run setup-041-browser-customer.php through the gate runner');
  for (const scenario of [
    { width: 1440, authenticated: false }, { width: 390, authenticated: false },
    ...(account ? [{ width: 1440, authenticated: true }, { width: 390, authenticated: true }] : []),
  ]) {
    const { width, authenticated } = scenario;
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    const page = await context.newPage();
    pages.push(page);
    await routeCanonicalNavigation(page, baseUrl);
    const api = async (method = 'GET', endpoint = 'cart', body) => {
      const probe = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`);
      if (method === 'GET') return probe.json();
      const response = await page.request.fetch(`${baseUrl}/wp-json/wc/store/v1/${endpoint}`, {
        method, headers: { Nonce: probe.headers().nonce }, data: body,
      });
      assert(response.ok(), `${method} ${endpoint}: HTTP ${response.status()}`);
      return response.json();
    };
    await page.goto(baseUrl, { waitUntil: 'domcontentloaded' });
    if (authenticated) {
      await page.goto(`${baseUrl}/wp-login.php`);
      await page.locator('#user_login').fill(account.login);
      await page.locator('#user_pass').fill(account.password);
      await Promise.all([page.waitForNavigation(), page.locator('#wp-submit').click()]);
      await page.goto(baseUrl);
    }
    const response = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/products?per_page=50`);
    const products = (await response.json()).filter((p) => p.type === 'simple' && p.is_purchasable && p.is_in_stock);
    const selected = [];
    await api('DELETE', 'cart/items');
    for (const product of products) {
      try {
        const cart = await api('POST', 'cart/add-item', { id: product.id, quantity: 1 });
        const item = cart.items.find((i) => i.id === product.id);
        if (item.quantity_limits.maximum >= 4) selected.push(item);
        else await api('DELETE', `cart/items/${item.key}`);
        if (selected.length === 2) break;
      } catch (_) { /* Products unavailable to guests are not quantity fixtures. */ }
    }
    assert.equal(selected.length, 2, 'Two independent quantity fixtures required');
    await page.goto(`${baseUrl}/carrinho/`, { waitUntil: 'domcontentloaded' });
    const rows = page.locator('.wc-block-cart-items__row');
    await rows.nth(1).waitFor();
    await page.locator('[data-petshop-cart-shipping-form]').waitFor();
    assert.equal(await page.locator('#petshop-cart-shipping-postcode').count(), 1);
    await page.evaluate(() => {
      window.__041observations = [];
      const sample = () => {
        const store = window.wp.data.select('wc/store/cart');
        window.__041observations.push({ at: performance.now(), quantities: [...document.querySelectorAll('.wc-block-components-quantity-selector__input')].map((n) => n.value),
          pending: window.petshopCartOperations.busy(), quotePending: Boolean(document.querySelector('[data-petshop-cart-shipping-form] button')?.disabled), totals: store.getCartTotals(),
          lines: store.getCartData().items.map(({ key, quantity, totals }) => ({ key, quantity, totals })) });
      };
      window.__041observer = new MutationObserver(sample);
      window.__041observer.observe(document.querySelector('.wc-block-cart'), { subtree: true, childList: true, characterData: true, attributes: true });
      sample();
    });
    const trace = [];
    page.on('request', (request) => {
      if (request.method() === 'POST' && /wc\/store\/v1\/(cart\/|batch)/.test(request.url())) {
        const payload = request.postDataJSON();
        for (const operation of payload?.requests || [{ path: new URL(request.url()).pathname, body: payload }]) {
          trace.push({ at: Date.now(), endpoint: operation.path.split('/').pop(), key: operation.body?.key, quantity: operation.body?.quantity });
        }
      }
    });
    const waitQuantity = async (index, quantity) => {
      await page.waitForFunction(({ index, quantity }) => {
        const cart = window.wp.data.select('wc/store/cart').getCartData();
        return cart.items[index]?.quantity === quantity && !window.petshopCartOperations.busy()
          && !document.querySelector('[data-petshop-cart-shipping-form] button')?.disabled;
      }, { index, quantity }, { timeout: 20000 });
      const official = await api();
      const storeTotals = await page.evaluate(() => window.wp.data.select('wc/store/cart').getCartTotals());
      assert.deepEqual(storeTotals, official.totals, 'Confirmed store totals must equal an independent server read');
    };
    for (const latency of [100, 800, 2000]) {
      await page.route('**/wc/store/v1/cart/update-item*', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, latency));
        await route.fallback();
      });
      const input = rows.nth(0).locator('input.wc-block-components-quantity-selector__input');
      await input.fill('2');
      await input.fill('3');
      await input.press('Tab');
      await page.locator('#petshop-cart-shipping-postcode').fill(latency === 800 ? '91210-320' : '01310-100');
      await page.locator('[data-petshop-cart-shipping-form] button').click();
      await waitQuantity(0, 3);
      await page.waitForFunction(() => !document.querySelector('[data-petshop-cart-shipping-form] button').disabled);
      const official = await api();
      assert.equal(official.items[0].quantity, 3);
      assert.equal(official.shipping_address.city, '', 'Quote must not fabricate city');
      await input.fill('1');
      await input.press('Tab');
      await waitQuantity(0, 1);
      await page.unroute('**/wc/store/v1/cart/update-item*');
      checks.push({ width, authenticated, latency, scenario: 'T01/T03 quantity and CEP', result: 'passed' });
    }
    const label = `${width}-${authenticated ? 'account' : 'guest'}`;
    fs.writeFileSync(path.join(evidence, 'transitions-' + label + '.json'), JSON.stringify(await page.evaluate(() => window.__041observations), null, 2));
    let official = await api('POST', 'cart/update-item', { key: selected[0].key, quantity: 4 });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await rows.nth(1).waitFor();
    await rows.nth(1).locator('.wc-block-components-quantity-selector__input').fill('2');
    await rows.nth(1).locator('.wc-block-components-quantity-selector__input').press('Tab');
    await waitQuantity(1, 2);
    official = await api();
    assert.equal(official.items.find((i) => i.key === selected[0].key).quantity, 4, 'Editing B must not resend historical A');
    checks.push({ width, authenticated, scenario: 'T02 two independent lines', result: 'passed' });
    await page.locator('#petshop-cart-shipping-postcode').fill('123');
    await page.locator('[data-petshop-cart-shipping-form] button').click();
    await page.getByText('Informe um CEP com 8 dígitos.', { exact: true }).waitFor();
    assert.equal(await page.locator('#custom-postcode-form:visible').count(), 0);
    await page.screenshot({ path: path.join(evidence, `cart-${label}.png`), fullPage: true });
    fs.writeFileSync(path.join(evidence, `network-${label}.json`), JSON.stringify(trace, null, 2));
    await api('DELETE', 'cart/items');
    await page.unrouteAll({ behavior: 'ignoreErrors' });
    await context.close();
  }
} catch (error) {
  // Preserve the failed state as well as successful scenarios, without account,
  // address, cookie, nonce or request payload data.
  const summarize = (cart) => ({
    items: (cart.items || []).map(({ key, quantity, totals }) => ({ key, quantity, totals })),
    totals: cart.totals,
    packages: (cart.shipping_rates || cart.shippingRates || []).map((pack) => ({
      package_id: pack.package_id ?? pack.packageId,
      rates: (pack.shipping_rates || pack.shippingRates || []).map((rate) => ({
        rate_id: rate.rate_id ?? rate.rateId, name: rate.name, price: rate.price, selected: rate.selected,
      })),
    })),
  });
  const failedPage = pages.findLast((page) => !page.isClosed());
  if (failedPage) {
    const store = await failedPage.evaluate(() => {
      const selector = window.wp?.data?.select('wc/store/cart');
      return { ...selector?.getCartData(), totals: selector?.getCartTotals() };
    }).catch(() => ({}));
    const server = await failedPage.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`).then((response) => response.json()).catch(() => ({}));
    fs.writeFileSync(path.join(evidence, 'failure.json'), JSON.stringify({ error: error.message,
      store: summarize(store || {}), server: summarize(server || {}),
      transitions: await failedPage.evaluate(() => window.__041observations || []).catch(() => []),
    }, null, 2));
  }
  throw error;
} finally {
  for (const page of pages) if (!page.isClosed()) await page.unrouteAll({ behavior: 'ignoreErrors' });
  await browser.close();
}
fs.writeFileSync(path.join(evidence, 'results.json'), JSON.stringify(checks, null, 2));
console.log('041 cart consistency: passed', checks);
