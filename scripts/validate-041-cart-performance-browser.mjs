import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { launchBrowser, routeCanonicalNavigation, createEvidenceDirectory } from './lib/browser-helpers.mjs';

const base = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const baseline = process.env.PETSHOP_041_BASELINE === '1';
const evidence = createEvidenceDirectory('041-cart-performance');
const browser = await launchBrowser();
const results = [];
const pages = [];
const pageErrors = [];
const fixtureFile = path.resolve('.local/041-browser-fixture.json');
const account = fs.existsSync(fixtureFile) ? JSON.parse(fs.readFileSync(fixtureFile, 'utf8')) : null;
try {
  if (!baseline) assert(account?.login && account?.password, 'Authenticated 041 fixture required');
  for (const { width, authenticated } of baseline ? [{ width: 1440, authenticated: false }] : [
    { width: 1440, authenticated: false }, { width: 390, authenticated: false },
    { width: 1440, authenticated: true }, { width: 390, authenticated: true },
  ]) {
    const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    pages.push(page);
    page.on('pageerror', (error) => pageErrors.push({ message: error.message, stack: error.stack }));
    await routeCanonicalNavigation(page, base);
    await page.goto(base);
    if (authenticated) {
      await page.goto(`${base}/wp-login.php`);
      await page.locator('#user_login').fill(account.login);
      await page.locator('#user_pass').fill(account.password);
      await Promise.all([page.waitForNavigation(), page.locator('#wp-submit').click()]);
      await page.goto(base);
    }
    const api = async (method, endpoint, data) => {
      const probe = await page.request.get(`${base}/wp-json/wc/store/v1/cart`);
      if (method === 'GET') return probe.json();
      const response = await page.request.fetch(`${base}/wp-json/wc/store/v1/${endpoint}`, { method, data, headers: { Nonce: probe.headers().nonce } });
      assert(response.ok(), `${method} ${endpoint}: ${response.status()}`);
      return response.json();
    };
    await api('DELETE', 'cart/items');
    await api('POST', 'cart/add-item', { id: 1486, quantity: 1 });
    await api('POST', 'cart/update-customer', { shipping_address: { country: 'BR', state: 'SP', postcode: '01310100', city: 'São Paulo', address_1: 'Avenida Paulista' } });
    await page.goto(`${base}/carrinho/`, { waitUntil: 'networkidle' });
    const input = page.locator('.wc-block-components-quantity-selector__input').first();
    await input.waitFor();
    const idle = () => page.waitForFunction(() => !petshopCartRequestCoordinator.pending() && !petshopCartOperations.busy()
      && !document.querySelector('.petshop-cart-updating'), null, { timeout: 45000 });
    await idle();
    await page.evaluate(() => {
      window.__041visual = { edits: [], paints: [] };
      const root = document.querySelector('.wp-block-woocommerce-cart');
      root.addEventListener('click', (event) => {
        if (event.target.closest('.wc-block-components-quantity-selector__button--plus')) window.__041visual.edits.push(performance.now());
      }, true);
      new MutationObserver(() => {
        const estimate = root.querySelector('[data-petshop-line-estimate]');
        if (estimate) window.__041visual.paints.push({ at: performance.now(), money: estimate.querySelector('.wc-block-formatted-money-amount')?.textContent });
        else if (window.__041responseAt) window.__041paintDelay = performance.now() - window.__041responseAt;
      }).observe(root, { childList: true, characterData: true, subtree: true });
    });
    let active = 0; let maximum = 0; let delay = 900; let failOnce = false;
    const trace = [];
    await page.route(/\/wc\/store\/v1\/(cart(?:\/|\?|$)|batch)/, async (route) => {
      active++; maximum = Math.max(maximum, active);
      const request = route.request();
      const payload = request.postDataJSON();
      const operations = payload?.requests || [{ path: new URL(request.url()).pathname, body: payload }];
      const quantities = operations.filter((operation) => operation.path.endsWith('/update-item')).map((operation) => operation.body?.quantity);
      const entry = { start: Date.now(), quantities, method: request.method(), endpoint: new URL(request.url()).pathname };
      trace.push(entry);
      try {
        await new Promise((resolve) => setTimeout(resolve, delay));
        if (failOnce && quantities.length) { failOnce = false; entry.serverDone = Date.now(); await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ code: 'gate041_network_failure', message: 'Temporary fixture failure', data: { status: 503 } }) }); }
        else { const response = await route.fetch(); entry.serverDone = Date.now(); await page.evaluate(() => { window.__041responseAt = performance.now(); }); await route.fulfill({ response }); }
      }
      finally { active--; entry.end = Date.now(); }
    });
    const start = Date.now();
    for (let click = 0; click < 5; click++) {
      await page.locator('.wc-block-components-quantity-selector__button--plus').first().click();
      if (click < 4) await page.waitForTimeout(150);
    }
    const immediate = await page.evaluate(() => {
      const unit = document.querySelector('.wc-block-cart-item__prices');
      const estimate = document.querySelector('[data-petshop-line-estimate]');
      return { unitVisible: unit && unit.getBoundingClientRect().height > 0 && unit.getBoundingClientRect().width > 0 && getComputedStyle(unit.querySelector('.wc-block-components-product-price') || unit).visibility !== 'hidden', estimate: estimate?.textContent || '', estimatePending: estimate?.dataset.pending };
    });
    await idle();
    const uiMilliseconds = Date.now() - start;
    let confirmed = await api('GET');
    const visual = await page.evaluate(() => window.__041visual);
    const visualDelays = visual.edits.map((at, index) => {
      const nextEdit = visual.edits[index + 1] || Infinity;
      const paint = visual.paints.find((entry) => entry.at >= at && entry.at < nextEdit);
      return paint ? paint.at - at : null;
    });
    const burst = { quantity: confirmed.items[0].quantity, writes: trace.flatMap((entry) => entry.quantities), uiMilliseconds, millisecondsIncludingDiagnosticRead: Date.now() - start, visualDelays, immediate };
    assert.equal(burst.quantity, 6);
    assert.equal(burst.writes.length, 1, 'Burst must submit only its final quantity');
    if (!baseline) { assert(immediate.unitVisible, 'Keep unit price readable'); assert.equal(immediate.estimatePending, 'true'); assert.match(immediate.estimate, /estimad/i); assert(visualDelays.every((ms) => ms !== null && ms <= 100), 'Each click must show its estimate within 100ms'); }
    const secondStart = trace.length;
    delay = 3200;
    await input.fill('2'); await input.press('Tab');
    await page.waitForFunction(() => petshopCartRequestCoordinator.pending());
    for (const quantity of [3, 4, 5, 7]) { await input.fill(String(quantity)); await input.press('Tab'); await page.waitForTimeout(450); }
    await idle();
    confirmed = await api('GET');
    const latest = trace.slice(secondStart).flatMap((entry) => entry.quantities);
    assert.equal(confirmed.items[0].quantity, 7);
    assert.deepEqual(latest, [2, 7], 'A slow active write must be followed only by the latest intent');
    assert.equal(maximum, 1);
    const store = await page.evaluate(() => wp.data.select('wc/store/cart').getCartData());
    assert.deepEqual(store.totals, confirmed.totals);
    const paintDelay = await page.evaluate(() => window.__041paintDelay);
    assert(Number.isFinite(paintDelay) && paintDelay <= 200, 'Measure and apply final response within 200ms');
    if (!baseline) {
      delay = 900;
      await page.evaluate(() => {
        document.querySelector('.wp-block-woocommerce-cart').addEventListener('input', (event) => {
          queueMicrotask(() => {
            document.querySelector('.wc-block-cart__submit-button').click();
            window.__041immediateGuard = { desired: [...petshopCartRequestCoordinator.desiredQuantities()].map(([, quantity]) => quantity), focused: document.activeElement === event.target };
          });
        }, { capture: true, once: true });
      });
      // Exercise native typing without blur: its child debounce is 600ms.
      // A same-turn checkout attempt must not cancel that pending edit.
      await input.fill('2');
      const guard = await page.evaluate(() => window.__041immediateGuard);
      assert.deepEqual(guard.desired, [2]);
      assert(guard.focused);
      assert(new URL(page.url()).pathname.includes('carrinho'));
      await page.waitForFunction(() => petshopCartRequestCoordinator.pending(), null, { timeout: 5000 });
      await page.waitForFunction(() => document.querySelector('.petshop-cart-order-status')?.textContent.includes('Atualizando'), null, { timeout: 350, polling: 'raf' });
      assert.equal(await page.locator('.petshop-cart-order-status').count(), 1);
      assert(await input.evaluate((element) => document.activeElement === element), 'Keep keyboard focus while recalculating');
      assert.equal(await page.locator('[data-petshop-line-estimate]').evaluate((element) => getComputedStyle(element).animationName), 'none');
      await page.screenshot({ path: path.join(evidence, `pending-${width}-${authenticated ? 'account' : 'guest'}.png`), fullPage: true });
      await idle();
      assert.equal((await api('GET')).items[0].quantity, 2, 'Typing without blur must converge to the desired quantity');
      assert.equal(await page.locator('.wc-block-cart__submit-button .wc-block-components-spinner').count(), 0);
      assert(account.products?.simple && account.products?.variation && account.products?.readonly && account.products?.coupon, 'Controlled simple/variable/readonly/discount fixtures required');
      await api('DELETE', 'cart/items');
      await api('POST', 'cart/add-item', { id: account.products.simple, quantity: 1 });
      await api('POST', 'cart/add-item', { id: account.products.variation, quantity: 1 });
      delay = 900;
      await page.reload({ waitUntil: 'networkidle' });
      const inputs = page.locator('.wc-block-components-quantity-selector__input');
      await inputs.nth(1).waitFor();
      await idle();
      await inputs.nth(0).fill('2'); await inputs.nth(0).press('Tab');
      await inputs.nth(1).fill('3'); await inputs.nth(1).press('Tab');
      await idle();
      confirmed = await api('GET');
      assert.deepEqual(confirmed.items.map((item) => item.quantity), [2, 3], 'Independent lines including variation must retain their desired quantities');
      await page.evaluate(async (coupon) => { await wp.data.dispatch('wc/store/cart').applyCoupon(coupon); }, account.products.coupon);
      await idle();
      confirmed = await api('GET');
      assert(Number(confirmed.totals.total_discount) > 0, 'Fixture coupon must change real official totals');
      for (const item of confirmed.items) {
        const text = await page.locator(`[data-cart-item-key="${item.key}"] [data-petshop-line-value]`).innerText();
        assert.equal(BigInt(text.replace(/\D/g, '')), BigInt(item.totals.line_total), 'Confirmed row must show its own after-discount total, not order/shipping total');
      }
      // Native UI clamps stock limits on blur. Capture that normalization
      // instead of leaving the raw requested 9 pending after accepting 8.
      await inputs.first().fill('9'); await inputs.first().press('Tab');
      await idle();
      assert.equal((await api('GET')).items[0].quantity, 8);
      assert.equal(await inputs.first().inputValue(), '8');
      assert.equal(await page.locator('.petshop-cart-draft').count(), 0);
      await inputs.first().fill('2'); await inputs.first().press('Tab');
      await idle();
      confirmed = await api('GET');
      // Native real400 has no data.cart. The rejected mutation must reconcile
      // through a full official read instead of retrying invalid stock forever.
      const stockError = await page.evaluate(async (key) => {
        petshopCartRequestCoordinator.noteQuantity(key, 9);
        try { await wp.data.dispatch('wc/store/cart').changeCartItemQuantity(key, 9); }
        catch (error) { return error.code; }
      }, confirmed.items[0].key);
      assert.equal(stockError, 'invalid_quantity', 'Real stock rejection must be the expected WooCommerce limit error');
      await idle();
      const rejected = await api('GET');
      assert.equal(rejected.items[0].quantity, 2);
      assert.equal(await inputs.first().inputValue(), '2');
      assert.equal(await page.locator('.petshop-cart-draft').count(), 0, 'Stock rejection must release invalid draft/checkout lock');
      failOnce = true;
      await inputs.first().fill('4'); await inputs.first().press('Tab');
      await page.getByRole('button', { name: 'Tentar novamente', exact: true }).waitFor();
      assert.equal(await page.locator('.petshop-cart-draft').count(), 1);
      await page.locator('.wc-block-cart__submit-button').click();
      assert(new URL(page.url()).pathname.includes('carrinho'), 'Do not navigate with unconfirmed quantities');
      await page.getByRole('button', { name: 'Tentar novamente', exact: true }).click();
      await idle();
      confirmed = await api('GET');
      assert.equal(confirmed.items[0].quantity, 4);
      await page.waitForFunction(() => !document.querySelector('.wc-block-cart__submit-button .wc-block-components-spinner'), null, { timeout: 5000 });
      await inputs.first().fill('2'); await inputs.first().press('Tab');
      await page.waitForFunction(() => petshopCartRequestCoordinator.pending());
      await page.evaluate(async (key) => { await wp.data.dispatch('wc/store/cart').removeItemFromCart(key); }, confirmed.items[0].key);
      await idle();
      confirmed = await api('GET');
      assert.equal(confirmed.items.length, 1);
      assert.equal(confirmed.items[0].quantity, 3);
      assert.equal(await page.locator('[data-petshop-line-estimate]').count(), 0);
      assert.equal(await page.locator('.petshop-cart-order-status').getAttribute('role'), 'status');
      await api('POST', 'cart/add-item', { id: account.products.readonly, quantity: 1 });
      await page.reload({ waitUntil: 'networkidle' });
      await idle();
      confirmed = await api('GET');
      const readonly = confirmed.items.find((item) => item.id === account.products.readonly);
      assert(readonly);
      const readonlyInputs = page.locator(`[data-cart-item-key="${readonly.key}"] input`);
      assert.equal(await readonlyInputs.count(), 0, 'Sold-individually product must not expose a quantity editor');
      const readonlyError = await page.evaluate(async (key) => {
        petshopCartRequestCoordinator.noteQuantity(key, 2);
        try { await wp.data.dispatch('wc/store/cart').changeCartItemQuantity(key, 2); }
        catch (error) { return error.code; }
      }, readonly.key);
      assert.equal(readonlyError, 'readonly_quantity');
      await idle();
      assert.equal((await api('GET')).items.find((item) => item.key === readonly.key).quantity, 1);
      assert.equal(await page.locator('.petshop-cart-draft').count(), 0);
    }
    assert.equal(maximum, 1, 'Keep one physical request throughout recovery, coupons, variation and removal');
    const ownErrors = pageErrors.filter(({ message, stack = '' }) => !(
      /^(crypto.randomUUID is not a function|Melidata client load timed out)$/.test(message)
      && /woocommerce-mercadopago\/assets\/js\/melidata\/melidata-client\.min\.js|http2\.mlstatic\.com\/storage\/v1\/plugins\/melidata\/woocommerce\.min\.js/.test(stack)
    ));
    assert.deepEqual(ownErrors, [], 'No own or unknown page errors');
    results.push({ width, authenticated, burst, latest, maximum, paintDelay, typingWithoutBlur: !baseline, immediateCheckoutGuard: !baseline, loading: !baseline, reducedMotion: !baseline, stockClamp: !baseline, readonly: !baseline, independentLines: !baseline, variable: !baseline, stockRejection: !baseline, failureRetry: !baseline, removal: !baseline, coupon: !baseline, pageErrors, trace });
    await page.screenshot({ path: path.join(evidence, `${baseline ? 'baseline' : 'final'}-${width}-${authenticated ? 'account' : 'guest'}.png`), fullPage: true });
    await page.unrouteAll({ behavior: 'wait' });
    await api('DELETE', 'cart/items');
    await context.close();
  }
  fs.writeFileSync(path.join(evidence, `${baseline ? 'baseline' : 'final'}.json`), JSON.stringify(results, null, 2));
  console.log(`041 cart performance ${baseline ? 'baseline recorded' : 'passed'}`, results.map(({ width, burst, latest, maximum }) => ({ width, burst, latest, maximum })));
} catch (error) {
  const page = pages.findLast((entry) => !entry.isClosed());
  const state = page ? await page.evaluate(() => ({
    quantities: wp.data.select('wc/store/cart').getCartData().items.map((item) => ({ key: item.key, quantity: item.quantity })),
    busy: petshopCartOperations.busy(), pending: petshopCartRequestCoordinator.pending(),
    desires: [...petshopCartRequestCoordinator.desiredQuantities()], classes: document.querySelector('.wp-block-woocommerce-cart')?.className,
  })).catch(() => ({})) : {};
  fs.writeFileSync(path.join(evidence, 'failure.json'), JSON.stringify({ error: error.message, state, pageErrors }, null, 2));
  throw error;
} finally { await browser.close(); }
