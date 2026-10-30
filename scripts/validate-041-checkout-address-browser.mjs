import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { launchBrowser, routeCanonicalNavigation, createEvidenceDirectory } from './lib/browser-helpers.mjs';
const base = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidence = createEvidenceDirectory('041-checkout-address');
const browser = await launchBrowser();
const results = [];
try {
  for (const width of [1440, 390]) {
    const context = await browser.newContext({ viewport: { width, height: 1000 } });
    const page = await context.newPage();
    const calls = [];
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push({ message: error.message, stack: error.stack }));
    try {
      await routeCanonicalNavigation(page, base);
      await page.route('**/wp-admin/admin-ajax.php', async (route) => {
        const data = new URLSearchParams(route.request().postData() || '');
        if (data.get('action') !== 'petshop_lookup_cep') return route.fallback();
        const cep = data.get('cep'); calls.push(cep);
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data:
          cep === '91210320' ? { logradouro: 'Rua Vital Brasil', bairro: 'Passo das Pedras', localidade: 'Porto Alegre', uf: 'RS', complemento: '' }
            : { logradouro: 'Avenida Paulista', bairro: 'Bela Vista', localidade: 'Sao Paulo', uf: 'SP', complemento: '' } }) });
      });
      await page.goto(base);
      let response = await page.request.get(`${base}/wp-json/wc/store/v1/cart`);
      response = await page.request.post(`${base}/wp-json/wc/store/v1/cart/add-item`, { headers: { Nonce: response.headers().nonce }, data: { id: 1486, quantity: 1 } });
      assert(response.ok());
      response = await page.request.post(`${base}/wp-json/wc/store/v1/cart/update-customer`, { headers: { Nonce: response.headers().nonce }, data: {
        shipping_address: { country: 'BR', postcode: '91210320', state: 'RS', city: '', address_1: '', 'petshop/number': '42', 'petshop/neighborhood': '' },
        billing_address: { country: 'BR', postcode: '91210320', state: 'RS', city: '', address_1: '', 'petshop/number': '42', 'petshop/neighborhood': '' },
      } });
      assert(response.ok());
      await page.goto(`${base}/finalizar-compra/`, { waitUntil: 'networkidle' });
      const postcode = page.locator('#shipping-postcode');
      await page.waitForFunction(() => document.querySelector('#shipping-address_1')?.value === 'Rua Vital Brasil'
        && document.querySelector('#shipping-petshop-neighborhood')?.value === 'Passo das Pedras');
      assert(calls.includes('91210320'), 'Session CEP must trigger lookup without input or blur');
      const scope = await postcode.evaluate((node) => [...node.closest('.wc-block-components-address-form').querySelectorAll('input:not([type="hidden"]),select')]
        .filter((field) => field.getClientRects().length && field.tabIndex !== -1).map((field) => ({ id: field.id, label: field.labels?.[0]?.textContent })));
      const expectedOrder = ['first_name', 'last_name', 'country', 'postcode', 'address_1', 'petshop-number', 'address_2', 'petshop-neighborhood', 'city', 'state', 'phone'];
      assert.deepEqual(scope.map((field) => field.id), expectedOrder.map((key) => `shipping-${key}`), 'Reference order must match DOM/keyboard sequence');
      assert.equal(await page.locator('#shipping-address_2').isVisible(), true, 'Complement must be immediately available');
      if (width > 480) {
        for (const [left, right] of [['first_name', 'last_name'], ['country', 'postcode'], ['address_1', 'petshop-number'], ['petshop-neighborhood', 'city'], ['state', 'phone']]) {
          const a = await page.locator(`#shipping-${left}`).boundingBox();
          const b = await page.locator(`#shipping-${right}`).boundingBox();
          assert(Math.abs(a.y - b.y) < 2 && b.x > a.x, `${left}/${right} must share a row`);
        }
      }
      assert.equal(scope.filter((field) => /neighborhood/.test(field.id)).length, 1, 'One neighborhood per address');
      assert.equal(scope.filter((field) => /number/.test(field.id)).length, 1, 'One number per address');
      assert.equal(scope.filter((field) => /postcode/.test(field.id)).length, 1, 'One CEP per address');
      assert(!scope.some((field) => /virtuaria/.test(field.id)), 'Redundant carrier fields must not be registered');
      assert.equal(await page.locator('#shipping-petshop-number').inputValue(), '42', 'Lookup must preserve the customer number');
      await postcode.fill('01310930');
      await page.waitForFunction(() => document.querySelector('#shipping-address_1')?.value === 'Avenida Paulista'
        && document.querySelector('#shipping-petshop-neighborhood')?.value === 'Bela Vista');
      assert(calls.includes('01310930'), 'Typing all 8 digits must consult without blur');
      await page.evaluate(() => window.petshopCartOperations.waitForIdle(() => true, 2000));
      const saved = { address_1: 'Rua informada pelo cliente', city: 'Cidade informada pelo cliente',
        address_2: 'Apartamento informado pelo cliente', 'petshop/neighborhood': 'Bairro informado pelo cliente' };
      if (!await page.locator('#shipping-address_2').isVisible()) {
        await postcode.locator('xpath=ancestor::*[contains(@class,"wc-block-components-address-form")][1]').locator('.wc-block-components-address-form__address_2-toggle').click();
      }
      for (const [key, value] of Object.entries(saved)) await page.locator(`#shipping-${key.replaceAll('/', '-')}`).fill(value);
      await page.evaluate(() => window.petshopCartOperations.waitForIdle(() => true, 2000));
      const beforeReload = calls.length;
      await page.reload({ waitUntil: 'networkidle' });
      await page.waitForFunction(() => document.querySelector('.petshop-cep-message')?.textContent.includes('Endereço encontrado'));
      assert(calls.length > beforeReload && calls.slice(beforeReload).includes('01310930'), 'Reload must consult saved session CEP immediately');
      await page.evaluate(() => window.petshopCartOperations.waitForIdle(() => true, 2000));
      const cart = await (await page.request.get(`${base}/wp-json/wc/store/v1/cart`)).json();
      for (const [key, value] of Object.entries(saved)) {
        assert.equal(await page.locator(`#shipping-${key.replaceAll('/', '-')}`).inputValue(), value, 'Initial lookup must preserve saved address detail in DOM');
        assert.equal(cart.shipping_address[key], value, 'Initial lookup must preserve saved address detail in Store API');
      }
      await page.evaluate(() => window.petshopQuotePreference.remember('01310930'));
      const beforePreference = calls.length;
      await page.reload({ waitUntil: 'networkidle' });
      await page.waitForFunction(() => window.petshopQuotePreference.read() === null
        && document.querySelector('.petshop-cep-message')?.textContent.includes('Endereço encontrado'));
      assert(calls.length > beforePreference, 'Same-CEP preference with complete address must still consult ViaCEP');
      await page.evaluate(() => window.petshopCartOperations.waitForIdle(() => true, 2000));
      const preferredCart = await (await page.request.get(`${base}/wp-json/wc/store/v1/cart`)).json();
      for (const [key, value] of Object.entries(saved)) {
        assert.equal(await page.locator(`#shipping-${key.replaceAll('/', '-')}`).inputValue(), value);
        assert.equal(preferredCart.shipping_address[key], value);
      }
      const messageBounds = await page.locator('[data-petshop-cep-for="shipping-postcode"]').boundingBox();
      const streetBounds = await page.locator('#shipping-address_1').boundingBox();
      assert(messageBounds.y + messageBounds.height <= streetBounds.y, 'Lookup status must not overlap the next field');
      await page.screenshot({ path: path.join(evidence, `${width}.png`), fullPage: true });
      const sameAddress = page.getByLabel('Usar o mesmo endereço para cobrança', { exact: true });
      await sameAddress.uncheck();
      await page.waitForFunction(() => document.querySelector('#billing-address_2')?.getClientRects().length);
      const billingOrder = await page.locator('#billing-postcode').evaluate((node) => [...node.closest('.wc-block-components-address-form').querySelectorAll('input:not([type="hidden"]),select')]
        .filter((field) => field.getClientRects().length && field.tabIndex !== -1).map((field) => field.id));
      assert.deepEqual(billingOrder, expectedOrder.map((key) => `billing-${key}`), 'Billing must follow reference order');
      // Native uncheck clears the billing geography. Complete the address before
      // asserting its server persistence; incomplete addresses stay in local state.
      const completeBilling = async () => {
        await page.waitForFunction(() => window.wp.data.select('wc/store/checkout').getUseShippingAsBilling() === false);
        await page.locator('#billing-first_name').fill('Cliente');
        await page.locator('#billing-last_name').fill('Teste');
        await page.locator('#billing-postcode').fill('91210320');
        await page.waitForFunction(() => document.querySelector('#billing-address_1')?.value === 'Rua Vital Brasil');
        await page.locator('#billing-petshop-number').fill('77');
      };
      await completeBilling();
      await page.evaluate(() => window.petshopCartOperations.waitForIdle(() => true, 2000));
      await page.locator('#billing-petshop-number').fill('123');
      await page.locator('#billing-address_2').fill('Complemento cobrança');
      await page.evaluate(() => window.petshopCartOperations.waitForIdle(() => true, 2000));
      const independentCart = await (await page.request.get(`${base}/wp-json/wc/store/v1/cart`)).json();
      assert.equal(independentCart.billing_address['petshop/number'], '123');
      assert.equal(independentCart.billing_address.address_2, 'Complemento cobrança');
      assert.equal(independentCart.shipping_address['petshop/number'], '42');
      await sameAddress.check();
      await sameAddress.uncheck();
      await page.waitForFunction(() => document.querySelector('#billing-address_2')?.getClientRects().length);
      assert.equal(await page.locator('#billing .petshop-address-heading').count(), 2, 'No duplicate headings after remount');
      await completeBilling();
      await page.evaluate(() => window.petshopCartOperations.waitForIdle(() => true, 2000));
      await page.locator('#billing-address_2').fill('Após remontagem');
      await page.evaluate(() => window.petshopCartOperations.waitForIdle(() => true, 2000));
      assert.equal((await (await page.request.get(`${base}/wp-json/wc/store/v1/cart`)).json()).billing_address.address_2, 'Após remontagem');
      // This UI gate does not accept React or native DOM reconciliation errors.
      // Payment SDK errors are recorded separately, including their source stack.
      const layoutErrors = pageErrors.filter(({ message, stack = '' }) => !(
        /^(crypto.randomUUID is not a function|Melidata client load timed out)$/.test(message)
        && /woocommerce-mercadopago\/assets\/js\/melidata\/melidata-client\.min\.js|http2\.mlstatic\.com\/storage\/v1\/plugins\/melidata\/woocommerce\.min\.js/.test(stack)
        && !/petshop-core|petshop-theme/.test(stack)
      ));
      assert.deepEqual(layoutErrors, [], 'Layout must not introduce React/DOM reconciliation errors');
      results.push({ width, sessionLookup: true, inputLookup: true, reloadLookup: true, referenceLayout: true, uniqueFields: true, numberPreserved: true, savedAddressPreserved: true, sameCepPreferenceLookup: true, calls, pageErrors });
    } catch (error) {
      console.error(`041 checkout address at ${width}: ${error.message}`);
      await page.screenshot({ path: path.join(evidence, `${width}-failure.png`), fullPage: true }).catch(() => {});
      throw error;
    } finally {
      await page.unrouteAll({ behavior: 'ignoreErrors' });
      await context.close();
    }
  }
  fs.writeFileSync(path.join(evidence, 'results.json'), JSON.stringify(results, null, 2));
  console.log('041 checkout address: reference layout, unique fields, immediate session/input/reload lookup and preserved number passed at 1440/390 (induced ViaCEP responses)');
} finally { await browser.close(); }
