import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { launchBrowser, routeCanonicalNavigation, createEvidenceDirectory } from './lib/browser-helpers.mjs';
const base = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidence = createEvidenceDirectory('041-product-quote');
const browser = await launchBrowser();
const checks = [];
let diagnosticPage;
const requests = [];
try {
  const page = await browser.newPage();
  diagnosticPage = page;
  await routeCanonicalNavigation(page, base);
  await page.goto(base);
  const catalog = await (await page.request.get(`${base}/wp-json/wc/store/v1/products?per_page=50`)).json();
  const product = catalog.find((p) => p.type === 'simple' && p.is_purchasable && p.is_in_stock && p.add_to_cart.maximum >= 3);
  assert(product, 'Simple product with quantity 3 required');
  let mode = 'rates';
  let delay = 0;
  await page.route('**/wp-admin/admin-ajax.php*', async (route) => {
    const raw = route.request().postData() || '';
    const payload = route.request().headers()['content-type']?.includes('multipart/form-data')
      ? new URLSearchParams([...raw.matchAll(/name="([^"]+)"\r?\n\r?\n([^\r\n]*)/g)].map((match) => [match[1], match[2]]))
      : new URLSearchParams(raw);
    if (payload.get('action') === 'petshop_lookup_cep') return route.fulfill({ contentType: 'application/json',
      body: JSON.stringify({ success: true, data: { cep: '01310-100', logradouro: 'Avenida Paulista', bairro: 'Bela Vista', localidade: 'São Paulo', uf: 'SP', complemento: '' } }) });
    if (payload.get('action') !== 'petshop_calculate_shipping') return route.fallback();
    const quantity = Number(payload.get('quantity'));
    requests.push({ quantity, productId: Number(payload.get('product_id')), variationId: Number(payload.get('variation_id')) });
    const currentMode = mode;
    await new Promise((resolve) => setTimeout(resolve, delay));
    if (currentMode === 'offline') return route.abort('internetdisconnected').catch(() => {});
    const failed = ['429', '503', 'nonce'].includes(currentMode);
    await route.fulfill({ status: failed ? (currentMode === 'nonce' ? 403 : Number(currentMode)) : 200,
      contentType: 'application/json', body: JSON.stringify({ success: !failed,
        data: failed ? { message: 'Falha técnica induzida. Tente novamente.' } : { rates: currentMode === 'empty' ? [] : [{
          label: `Ensaio quantidade ${quantity}`, costText: 'R$ 12,34', deliveryEstimate: '3 dias',
        }], productionLead: '', transportNote: '' } }) }).catch(() => {});
  });
  await page.goto(product.permalink, { waitUntil: 'networkidle' });
  const form = page.locator('[data-petshop-shipping-form]');
  const postcode = form.locator('input[name="postcode"]');
  const button = form.locator('button[type="submit"]');
  const result = page.locator('[data-petshop-shipping-result]');
  const quantity = page.locator('form.cart input.qty');
  const before = await (await page.request.get(`${base}/wp-json/wc/store/v1/cart`)).json();
  await postcode.fill('01310-100');
  for (const count of [1, 2, 3]) {
    await quantity.fill(String(count));
    await button.click();
    await page.getByText(`Ensaio quantidade ${count}`, { exact: true }).waitFor();
    assert.equal(requests.at(-1).quantity, count);
    checks.push(`quantity-${count}`);
  }
  const after = await (await page.request.get(`${base}/wp-json/wc/store/v1/cart`)).json();
  assert.deepEqual(after.items, before.items);
  assert.deepEqual(after.shipping_address, before.shipping_address);
  checks.push('frontend-quote-does-not-write-cart-or-address; PHP isolation covered by validate-041-shipping-preview.php');
  delay = 800;
  await button.click();
  await quantity.fill('2');
  await page.waitForTimeout(1000);
  assert.equal(await result.innerText(), '', 'An old quote must not revive after quantity editing');
  assert.equal(await button.isEnabled(), true);
  checks.push('late-response-invalidated');
  delay = 0;
  for (const failure of ['429', '503', 'nonce', 'offline']) {
    mode = failure;
    await button.click();
    await page.waitForFunction(() => !document.querySelector('[data-petshop-shipping-form] button').disabled);
    assert((await result.innerText()).length > 0, 'Transport error must be visible');
    assert.equal(await result.locator('.petshop-shipping-option').count(), 0, 'Transport error must not retain old rates');
    mode = 'rates';
    await button.click();
    await page.getByText('Ensaio quantidade 2', { exact: true }).waitFor();
    checks.push(`${failure}-visible-and-explicit-retry`);
  }
  mode = 'rates';
  delay = 16000;
  await button.click();
  await page.waitForFunction(() => !document.querySelector('[data-petshop-shipping-form] button').disabled, null, { timeout: 20000 });
  assert.equal(await result.locator('.petshop-shipping-option').count(), 0);
  assert((await result.innerText()).length > 0, 'Timeout must be visible');
  delay = 0;
  await button.click();
  await page.getByText('Ensaio quantidade 2', { exact: true }).waitFor();
  await page.waitForTimeout(1200);
  assert.equal(await result.locator('.petshop-shipping-option').count(), 1, 'Timed out response must not revive');
  checks.push('timeout-visible-and-explicit-retry');
  mode = 'empty';
  await button.click();
  await page.waitForFunction(() => !document.querySelector('[data-petshop-shipping-form] button').disabled);
  assert.equal(await result.locator('.petshop-shipping-option').count(), 0);
  assert((await result.innerText()).length > 0, 'Empty result must have explicit feedback');
  checks.push('empty-rates-clear-old-estimate');
  assert.equal(await page.evaluate(() => petshopQuotePreference.read()?.postcode), '01310100');
  await page.screenshot({ path: path.join(evidence, 'empty-result.png'), fullPage: true });
  const probe = await page.request.get(`${base}/wp-json/wc/store/v1/cart`);
  const added = await page.request.post(`${base}/wp-json/wc/store/v1/cart/add-item`, {
    headers: { Nonce: probe.headers().nonce }, data: { id: product.id, quantity: 1 },
  });
  assert(added.ok(), 'Direct checkout preference requires a real cart item');
  await page.goto(`${base}/finalizar-compra/`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => window.petshopQuotePreference?.read() === null
    && window.wp?.data?.select('wc/store/cart').getCartData().shippingAddress?.postcode?.replace(/\D/g, '') === '01310100'
    && !window.petshopCartOperations.busy(), null, { timeout: 25000 });
  const accepted = await (await page.request.get(`${base}/wp-json/wc/store/v1/cart`)).json();
  assert.equal(accepted.shipping_address.postcode.replace(/\D/g, ''), '01310100');
  assert.equal(accepted.items[0].quantity, 1, 'PDP preview quantity must not become a purchase');
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => window.petshopQuotePreference && window.wp?.data?.select('wc/store/cart').hasFinishedResolution('getCartData'));
  assert.equal(await page.evaluate(() => petshopQuotePreference.read()), null, 'Consumed preference must stay consumed after reload');
  await page.waitForFunction(() => !window.petshopCartOperations.busy());
  const reloadedStore = await page.evaluate(() => wp.data.select('wc/store/cart').getCartData());
  const reloadedApi = await (await page.request.get(`${base}/wp-json/wc/store/v1/cart`)).json();
  assert.equal(reloadedStore.shippingAddress.postcode.replace(/\D/g, ''), '01310100');
  assert.equal(reloadedApi.shipping_address.postcode.replace(/\D/g, ''), '01310100');
  assert.deepEqual(reloadedApi.items.map(({ key, quantity }) => ({ key, quantity })), accepted.items.map(({ key, quantity }) => ({ key, quantity })));
  assert.deepEqual(reloadedStore.items.map(({ key, quantity }) => ({ key, quantity })), reloadedApi.items.map(({ key, quantity }) => ({ key, quantity })));
  checks.push('PDP-to-direct-checkout-preference-consumed-and-preserved-after-reload');
  await page.unrouteAll({ behavior: 'ignoreErrors' });
  await page.close();
} catch (error) {
  const state = await diagnosticPage?.evaluate(() => ({
    result: document.querySelector('[data-petshop-shipping-result]')?.textContent,
    quantity: document.querySelector('form.cart input.qty')?.value,
    formValid: document.querySelector('[data-petshop-shipping-form]')?.checkValidity(),
    invalid: [...document.querySelectorAll('[data-petshop-shipping-form] :invalid')].map((field) => field.name),
  })).catch(() => ({}));
  fs.writeFileSync(path.join(evidence, 'failure.json'), JSON.stringify({ error: error.message, requests, state }, null, 2));
  throw error;
} finally { await browser.close(); }
fs.writeFileSync(path.join(evidence, 'results.json'), JSON.stringify({ checks, transport: 'induced in browser harness; not carrier validation' }, null, 2));
console.log('041 product quote: passed', checks);
