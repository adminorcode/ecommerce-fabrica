import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { launchBrowser, routeCanonicalNavigation, createEvidenceDirectory } from './lib/browser-helpers.mjs';
const base = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidence = createEvidenceDirectory('041-delivery-performance');
const browser = await launchBrowser();
const results = [];
try {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  const warnings = [];
  page.on('console', (message) => {
    if (/useSelect|Non-equal|Invalid DOM property/.test(message.text())) warnings.push({ text: message.text().slice(0, 500), location: message.location() });
  });
  await routeCanonicalNavigation(page, base);
  await page.route('**/wp-admin/admin-ajax.php', (route) => {
    if (!route.request().postData()?.includes('action=petshop_lookup_cep')) return route.fallback();
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { logradouro: 'Rua Vital Brasil', bairro: 'Passo das Pedras', localidade: 'Porto Alegre', uf: 'RS', complemento: '' } }) });
  });
  await page.goto(base);
  let response = await page.request.get(`${base}/wp-json/wc/store/v1/cart`);
  response = await page.request.post(`${base}/wp-json/wc/store/v1/cart/add-item`, { headers: { Nonce: response.headers().nonce }, data: { id: 1486, quantity: 1 } });
  assert(response.ok());
  const address = { first_name: 'Cliente', last_name: 'Teste', country: 'BR', postcode: '91210320', state: 'RS', city: 'Porto Alegre', address_1: 'Rua Vital Brasil', 'petshop/number': '42', 'petshop/neighborhood': 'Passo das Pedras' };
  response = await page.request.post(`${base}/wp-json/wc/store/v1/cart/update-customer`, { headers: { Nonce: response.headers().nonce }, data: { shipping_address: address, billing_address: address } });
  assert(response.ok());
  for (const routeName of ['carrinho', 'finalizar-compra']) {
    await page.goto(`${base}/${routeName}/`, { waitUntil: 'networkidle' });
    await page.evaluate(() => window.petshopCartOperations.waitForIdle(() => true, 2000));
    if (routeName === 'finalizar-compra') {
      assert(await page.evaluate(() => [...document.querySelectorAll('input[data-petshop-autocomplete]')].length > 0
        && [...document.querySelectorAll('input[data-petshop-autocomplete]')].every((field) => field.autocomplete === field.dataset.petshopAutocomplete)), 'Native autofill semantics must be preserved without lowercase React props');
    }
    for (let iteration = 0; iteration < 3; iteration++) {
      const rates = await page.evaluate(() => wp.data.select('wc/store/cart').getCartData().shippingRates[0].shipping_rates);
      const target = rates.find((rate) => !rate.selected);
      assert(target, 'Multiple real delivery methods required');
      const network = [];
      const requestStarted = new Map();
      const start = Date.now();
      const onRequest = (request) => {
        if (request.method() !== 'POST' || !request.url().includes('/wc/store/')) return;
        requestStarted.set(request, Date.now());
      };
      const onFinished = (request) => {
        if (!requestStarted.has(request)) return;
        network.push({ endpoint: new URL(request.url()).pathname, startMs: requestStarted.get(request) - start, durationMs: Date.now() - requestStarted.get(request), selection: request.postData()?.includes('select-shipping-rate') || request.url().includes('select-shipping-rate') });
      };
      page.on('request', onRequest); page.on('requestfinished', onFinished);
      const selector = routeName === 'carrinho' ? '.petshop-cart-shipping' : '.wc-block-checkout';
      await page.locator(`${selector} input[type="radio"][value="${target.rate_id}"]`).click();
      await page.waitForFunction((id) => wp.data.select('wc/store/cart').getCartData().shippingRates[0].shipping_rates.some((rate) => rate.rate_id === id && rate.selected)
        && !wp.data.select('wc/store/cart').isShippingRateBeingSelected(), target.rate_id);
      const officialMs = Date.now() - start;
      if (routeName === 'carrinho') await page.waitForFunction(() => document.querySelector('.petshop-cart-shipping').getAttribute('aria-busy') === 'false');
      const readyMs = Date.now() - start;
      page.off('request', onRequest); page.off('requestfinished', onFinished);
      results.push({ routeName, iteration, rateId: target.rate_id, officialMs, readyMs, network });
      await page.evaluate(() => window.petshopCartOperations.waitForIdle(() => true, 2000));
    }
  }
  fs.writeFileSync(path.join(evidence, 'results.json'), JSON.stringify({ results, warnings }, null, 2));
  assert(!warnings.some((warning) => /Invalid DOM property/.test(warning.text)), 'Registered field attributes must not cause React DOM warnings');
  console.log(JSON.stringify({ results, warnings }, null, 2));
  await context.close();
} finally { await browser.close(); }
