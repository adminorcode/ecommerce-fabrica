import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { launchBrowser, routeCanonicalNavigation, createEvidenceDirectory } from './lib/browser-helpers.mjs';
const base = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidence = createEvidenceDirectory('041-cart-concurrency');
const browser = await launchBrowser();
const results = [];
const fixturePath = path.resolve('.local/041-browser-fixture.json');
const account = fs.existsSync(fixturePath) ? JSON.parse(fs.readFileSync(fixturePath, 'utf8')) : null;
const pages = [];
try {
  assert(account?.login && account?.password, 'Authenticated 041 fixture required');
  for (const { width, authenticated } of [
    { width: 1440, authenticated: false }, { width: 390, authenticated: false },
    { width: 1440, authenticated: true }, { width: 390, authenticated: true },
  ]) {
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    const page = await context.newPage();
    pages.push(page);
    await routeCanonicalNavigation(page, base);
    await page.goto(base);
    if (authenticated) {
      await page.goto(`${base}/wp-login.php`);
      await page.locator('#user_login').fill(account.login);
      await page.locator('#user_pass').fill(account.password);
      await Promise.all([page.waitForNavigation(), page.locator('#wp-submit').click()]);
      await page.goto(base);
    }
    const initial = await page.request.get(`${base}/wp-json/wc/store/v1/cart`);
    const cleared = await page.request.delete(`${base}/wp-json/wc/store/v1/cart/items`, { headers: { Nonce: initial.headers().nonce } });
    assert(cleared.ok());
    const added = await page.request.post(`${base}/wp-json/wc/store/v1/cart/add-item`, { headers: { Nonce: initial.headers().nonce }, data: { id: 1486, quantity: 1 } });
    assert(added.ok());
    await page.goto(`${base}/carrinho/`, { waitUntil: 'networkidle' });
    await page.locator('#petshop-cart-shipping-postcode').waitFor();
    const waitDone = () => page.waitForFunction(() => !window.petshopCartOperations.busy() && !window.petshopCartRequestCoordinator.pending()
      && !document.querySelector('.petshop-cart-shipping button').disabled && !document.querySelector('.petshop-cart-updating'), null, { timeout: 45000 });
    await waitDone();
    let active = 0; let maximum = 0; const trace = [];
    await page.route(/\/wc\/store\/v1\/(cart(?:\/|\?|$)|batch)/, async (route) => {
      active++; maximum = Math.max(maximum, active);
      trace.push({ phase: 'start', active, path: new URL(route.request().url()).pathname });
      try {
        await new Promise((resolve) => setTimeout(resolve, 900));
        const response = await route.fetch();
        await route.fulfill({ response });
      } finally { active--; trace.push({ phase: 'end', active }); }
    });
    await page.evaluate(() => {
      window.__041seen = [];
      window.wp.data.subscribe(() => {
        const cart = wp.data.select('wc/store/cart').getCartData();
        window.__041seen.push({ quantity: cart.items[0]?.quantity, postcode: cart.shippingAddress?.postcode });
      });
      const key = wp.data.select('wc/store/cart').getCartData().items[0].key;
      window.__041first = wp.data.dispatch('wc/store/cart').changeCartItemQuantity(key, 3).catch((error) => ({ error: error.name }));
    });
    await page.waitForFunction(() => window.petshopCartRequestCoordinator.pending());
    await page.evaluate(() => {
      const key = wp.data.select('wc/store/cart').getCartData().items[0].key;
      window.__041last = wp.data.dispatch('wc/store/cart').changeCartItemQuantity(key, 5);
    });
    await page.locator('#petshop-cart-shipping-postcode').fill('01310-100');
    await page.locator('.petshop-cart-shipping button').click();
    assert(await page.evaluate(() => [...document.querySelectorAll('.wc-block-components-totals-item__value')]
      .every((node) => getComputedStyle(node).visibility === 'hidden')), 'Intermediate totals must not be presented as confirmed');
    await waitDone();
    assert.equal(maximum, 1, 'Only one physical Store API request may run at a time');
    const seen = await page.evaluate(() => window.__041seen);
    assert(!seen.some((entry) => entry.quantity === 3), 'Superseded quantity response must never enter the store');
    let confirmed = await page.request.get(`${base}/wp-json/wc/store/v1/cart`).then((response) => response.json());
    let state = await page.evaluate(() => wp.data.select('wc/store/cart').getCartData());
    assert.equal(confirmed.items[0].quantity, 5);
    assert.equal(state.items[0].quantity, 5);
    assert.equal(confirmed.shipping_address.postcode.replace(/\D/g, ''), '01310100');
    assert.deepEqual(state.totals, confirmed.totals, 'Latest confirmed totals must equal independent GET');
    await page.locator('#petshop-cart-shipping-postcode').fill('91210-320');
    await page.locator('.petshop-cart-shipping button').click();
    await page.waitForFunction(() => window.petshopCartRequestCoordinator.pending());
    await page.locator('#petshop-cart-shipping-postcode').fill('01310-100');
    await page.locator('.petshop-cart-shipping button').click();
    await waitDone();
    confirmed = await page.request.get(`${base}/wp-json/wc/store/v1/cart`).then((response) => response.json());
    state = await page.evaluate(() => wp.data.select('wc/store/cart').getCartData());
    assert.equal(confirmed.shipping_address.postcode.replace(/\D/g, ''), '01310100');
    assert.equal(state.shippingAddress.postcode.replace(/\D/g, ''), '01310100');
    assert.deepEqual(state.totals, confirmed.totals);
    assert.equal(maximum, 1);
    const quantityInput = page.locator('.wc-block-components-quantity-selector__input').first();
    await quantityInput.fill('1'); await quantityInput.press('Tab'); await waitDone();
    assert(await page.locator('.wc-block-cart-items__row').first().getAttribute('data-cart-item-key'), 'Native row must expose the actual cart key');
    await quantityInput.fill('3'); await quantityInput.press('Tab');
    await page.waitForFunction(() => window.petshopCartRequestCoordinator.pending());
    await quantityInput.fill('1'); await quantityInput.press('Tab');
    await waitDone();
    confirmed = await page.request.get(`${base}/wp-json/wc/store/v1/cart`).then((response) => response.json());
    state = await page.evaluate(() => wp.data.select('wc/store/cart').getCartData());
    assert.equal(confirmed.items[0].quantity, 1, 'Return to original quantity must be persisted despite native early return');
    assert.equal(state.items[0].quantity, 1);
    assert.equal(await quantityInput.inputValue(), '1');
    assert.deepEqual(state.totals, confirmed.totals);
    assert.equal(maximum, 1);
    await quantityInput.fill('3'); await quantityInput.press('Tab');
    await page.waitForFunction(() => window.petshopCartRequestCoordinator.pending());
    await quantityInput.fill('1');
    await quantityInput.fill('3'); await quantityInput.press('Tab');
    await page.evaluate(() => {
      window.__041inputTransitions = [];
      window.__041inputSampler = setInterval(() => window.__041inputTransitions.push(document.querySelector('.wc-block-components-quantity-selector__input')?.value), 10);
    });
    await waitDone();
    const inputTransitions = await page.evaluate(() => { clearInterval(window.__041inputSampler); return window.__041inputTransitions; });
    assert(inputTransitions.every((value) => value === '3'), 'Native pending must prevent input from reverting to original value before the current response');
    confirmed = await page.request.get(`${base}/wp-json/wc/store/v1/cart`).then((response) => response.json());
    state = await page.evaluate(() => wp.data.select('wc/store/cart').getCartData());
    assert.equal(confirmed.items[0].quantity, 3, 'Bounce back to a discarded physical intent must still confirm latest quantity');
    assert.equal(state.items[0].quantity, 3);
    assert.equal(await quantityInput.inputValue(), '3');
    assert.deepEqual(state.totals, confirmed.totals);
    assert.equal(maximum, 1);
    fs.writeFileSync(path.join(evidence, `${width}-${authenticated ? 'account' : 'guest'}.json`), JSON.stringify({ trace, seen, inputTransitions }, null, 2));
    results.push({ width, authenticated, maximum, quantity: 3, postcode: '01310100', returnToOriginal: 'passed', bounceBack: 'passed' });
    await page.unrouteAll({ behavior: 'wait' });
    await context.close();
  }
  fs.writeFileSync(path.join(evidence, 'results.json'), JSON.stringify(results, null, 2));
  console.log('041 cart concurrency: latest quantity/CEP, stale response discarded, single transport and independent totals passed', results);
} catch (error) {
  const page = pages.findLast((entry) => !entry.isClosed());
  if (page) {
    const state = await page.evaluate(() => ({
      items: wp.data.select('wc/store/cart').getCartData().items.map(({ key, quantity, totals }) => ({ key, quantity, totals })),
      totals: wp.data.select('wc/store/cart').getCartTotals(),
      pending: window.petshopCartOperations.busy(), transportPending: window.petshopCartRequestCoordinator.pending(),
      quoteStatus: document.querySelector('[data-petshop-cart-shipping-status]')?.textContent,
    })).catch(() => ({}));
    fs.writeFileSync(path.join(evidence, 'failure.json'), JSON.stringify({ error: error.message, state }, null, 2));
  }
  throw error;
} finally { await browser.close(); }
