import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { launchBrowser, routeCanonicalNavigation, createEvidenceDirectory } from './lib/browser-helpers.mjs';

const base = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidence = createEvidenceDirectory('041-cart-failures');
const browser = await launchBrowser();
const results = [];
const quoteBody = (payload, url) => {
  if (url.includes('/cart/extensions')) return true;
  return (payload?.requests || []).some((item) => String(item.path || '').includes('/cart/extensions')
    && JSON.stringify(item).includes('petshop-shipping-quote'));
};
const updateBody = (payload, url) => url.includes('/cart/update-item')
  || (payload?.requests || []).some((item) => String(item.path || '').includes('/cart/update-item'));

try {
  for (const width of [1440, 390]) {
    const context = await browser.newContext({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    try {
      await routeCanonicalNavigation(page, base);
      await page.goto(base);
      const probe = await page.request.get(`${base}/wp-json/wc/store/v1/cart`);
      const nonce = probe.headers().nonce;
      const cleared = await page.request.delete(`${base}/wp-json/wc/store/v1/cart/items`, { headers: { Nonce: nonce } });
      assert(cleared.ok());
      const added = await page.request.post(`${base}/wp-json/wc/store/v1/cart/add-item`, {
        headers: { Nonce: nonce }, data: { id: 1486, quantity: 1 },
      });
      assert(added.ok(), 'Cart fixture product 1486 must be purchasable');
      const addressed = await page.request.post(`${base}/wp-json/wc/store/v1/cart/update-customer`, {
        headers: { Nonce: nonce },
        data: { shipping_address: { country: 'BR', state: 'SP', postcode: '01310100', city: 'São Paulo', address_1: 'Avenida Paulista' } },
      });
      assert(addressed.ok());
      await page.goto(`${base}/carrinho/`, { waitUntil: 'networkidle' });
      const input = page.locator('.wc-block-components-quantity-selector__input').first();
      await input.waitFor();
      const idle = () => page.waitForFunction(() => !petshopCartRequestCoordinator.pending()
        && !petshopCartOperations.busy() && !document.querySelector('.petshop-cart-updating'), null, { timeout: 45000 });
      await idle();
      let mode = 'limit';
      let updates = 0;
      let readsAfterDrop = 0;
      const dropReads = [];
      page.on('request', (request) => {
        if (mode === 'drop' && request.method() === 'GET' && request.url().includes('/cart')) dropReads.push(request.url());
      });
      let dropped = false;
      let batchSeen = false;
      let directSeen = false;
      await page.route(/\/wc\/store\/v1\/(cart(?:\/|\?|$)|batch)/, async (route) => {
        const request = route.request();
        const url = request.url();
        const payload = request.postDataJSON();
        const updating = request.method() === 'POST' && updateBody(payload, url);
        if (mode === 'limit' && updating) {
          mode = 'pass';
          updates += 1;
          if (url.includes('/batch')) batchSeen = true; else directSeen = true;
          const failure = { code: 'rate_limited', message: 'Muitas tentativas. Tente novamente.', data: { status: 429 } };
          if (url.includes('/batch')) {
            return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({
              responses: payload.requests.map((item) => (String(item.path).includes('/cart/update-item')
                ? { status: 429, headers: {}, body: failure }
                : { status: 200, headers: {}, body: {} })),
            }) });
          }
          return route.fulfill({ status: 429, contentType: 'application/json', body: JSON.stringify(failure) });
        }
        if (mode === 'drop' && updating && !dropped) {
          dropped = true;
          updates += 1;
          return route.abort('failed');
        }
        if (mode === 'drop' && request.method() === 'GET' && /\/cart(?:\?|$)/.test(new URL(url).pathname)) readsAfterDrop += 1;
        if (mode === 'empty' && request.method() === 'POST' && (quoteBody(payload, url) || url.includes('/batch') || url.includes('/cart/'))) {
          const official = await (await page.request.get(`${base}/wp-json/wc/store/v1/cart`)).json();
          if (official.shipping_address) official.shipping_address.postcode = '20040002';
          official.shipping_rates = (official.shipping_rates || []).map((pack) => ({ ...pack, shipping_rates: [] }));
          if (url.includes('/batch')) {
            return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({
              responses: payload.requests.map(() => ({ status: 200, headers: {}, body: official })),
            }) });
          }
          return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(official) });
        }
        if (mode === 'select' && (url.includes('/cart/select-shipping-rate')
          || (payload?.requests || []).some((item) => String(item.path || '').includes('/cart/select-shipping-rate')))) {
          await new Promise((resolve) => setTimeout(resolve, 1200));
        }
        return route.fallback();
      });
      await input.fill('2');
      await input.press('Tab');
      const retry = page.getByRole('button', { name: 'Tentar novamente', exact: true });
      await retry.waitFor();
      assert.equal(updates, 1, 'A rejected 429 must not be repeated automatically');
      await page.locator('.wc-block-cart__submit-button').click();
      assert(new URL(page.url()).pathname.includes('carrinho'), 'Checkout stays blocked while the quantity is unconfirmed');
      await retry.click();
      await idle();
      const limited = await (await page.request.get(`${base}/wp-json/wc/store/v1/cart`)).json();
      assert.equal(limited.items[0].quantity, 2);
      assert.equal(await page.locator('.wc-block-cart__submit-button .wc-block-components-spinner').count(), 0);
      mode = 'drop';
      updates = 0;
      readsAfterDrop = 0;
      dropped = false;
      await input.fill('3');
      await input.press('Tab');
      await retry.waitFor();
      assert.equal(updates, 1, 'A lost update response must not be posted again before the explicit retry');
      assert(readsAfterDrop >= 1 || dropReads.length >= 1, 'A lost update response must be recovered by an official cart read');
      await retry.click();
      await idle();
      const recovered = await (await page.request.get(`${base}/wp-json/wc/store/v1/cart`)).json();
      assert.equal(recovered.items[0].quantity, 3);
      const radios = page.locator('.petshop-cart-shipping input[type="radio"]');
      if (await radios.count() > 1) {
        mode = 'select';
        const selected = await page.locator('.petshop-cart-shipping input[type="radio"]:checked').inputValue();
        await page.locator(`.petshop-cart-shipping input[type="radio"]:not([value="${selected}"])`).first().click();
        await page.locator('.petshop-cart-shipping input[name="postcode"]').fill('91210-320');
        await page.waitForTimeout(1500);
        assert.equal(await page.getByText('Não foi possível selecionar a entrega. Tente novamente.', { exact: true }).count(), 0,
          'Changing the CEP during selection must not announce the old selection as a failure');
      }
      mode = 'empty';
      await page.locator('.petshop-cart-shipping input[name="postcode"]').fill('20040-002');
      await page.locator('.petshop-cart-shipping button[type="submit"]').click();
      await page.getByText('Nenhuma opção foi retornada. Complete o endereço no checkout para confirmar a entrega.', { exact: true }).waitFor();
      assert.equal(await page.getByText('Não foi possível calcular a entrega. Tente novamente.', { exact: true }).count(), 0,
        'An empty official rate list is absence of coverage, not a transport failure');
      assert.equal(await page.locator('.petshop-cart-shipping input[type="radio"]').count(), 0, 'A disappeared method must not stay selected');
      await page.screenshot({ path: path.join(evidence, `${width}.png`), fullPage: true });
      results.push({ width, rejected429: true, lostResponseRead: true, explicitRetry: true, emptyRates: true, batchSeen, directSeen });
      await page.unrouteAll({ behavior: 'ignoreErrors' });
    } catch (error) {
      console.error(`041 cart failures at ${width}: ${error.message}`);
      await page.screenshot({ path: path.join(evidence, `${width}-failure.png`), fullPage: true }).catch(() => {});
      throw error;
    } finally {
      await page.unrouteAll({ behavior: 'ignoreErrors' }).catch(() => {});
      await context.close();
    }
  }
  fs.writeFileSync(path.join(evidence, 'results.json'), JSON.stringify(results, null, 2));
  console.log('041 cart failures: 429, lost response, explicit retry, empty coverage and interrupted selection passed at 1440/390');
} finally { await browser.close(); }
