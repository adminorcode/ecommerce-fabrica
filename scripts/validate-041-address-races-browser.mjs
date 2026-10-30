import assert from 'node:assert/strict';
import { launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';
const base = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const browser = await launchBrowser();
try {
  const page = await browser.newPage();
  await routeCanonicalNavigation(page, base);
  await page.goto(base);
  await page.setContent(`<form class="woocommerce-address-fields"><input id="billing_postcode" name="billing_postcode"><input id="billing_address_1" autocomplete="address-line1"><input id="billing_address_2" autocomplete="address-line2"><input id="billing_neighborhood"><input id="billing_city" autocomplete="address-level2"><select id="billing_state" autocomplete="address-level1"><option value="">Estado</option><option>SP</option><option>RS</option></select><input id="billing-petshop-number" value="17"></form>`);
  let calls = 0;
  let fail = false;
  await page.route('**/*gate041viacep*', async (route) => {
    calls++;
    const cep = new URLSearchParams(route.request().postData()).get('cep');
    await new Promise((resolve) => setTimeout(resolve, cep === '01310100' ? 800 : 100));
    await route.fulfill({ status: fail ? 503 : 200, contentType: 'application/json', body: JSON.stringify(fail
      ? { success: false, data: { message: 'Falha induzida: preencha manualmente.' } }
      : { success: true, data: { logradouro: cep === '01310100' ? 'Rua SP' : 'Rua RS', complemento: '', bairro: 'Centro', localidade: cep === '01310100' ? 'Sao Paulo' : 'Porto Alegre', uf: cep === '01310100' ? 'SP' : 'RS' } }) }).catch(() => {});
  });
  await page.evaluate((base) => { window.petshopAddressLookup = { ajaxUrl: base + '/?gate041viacep=1', action: 'fixture', nonce: 'synthetic' }; }, base);
  await page.addScriptTag({ url: base + '/wp-content/plugins/petshop-core/assets/js/address-lookup.js' });
  const postcode = page.locator('#billing_postcode');
  await postcode.fill('01310100');
  await postcode.fill('91210320');
  await page.waitForFunction(() => document.querySelector('#billing_city').value === 'Porto Alegre');
  await page.waitForTimeout(900);
  assert.equal(await page.locator('#billing_state').inputValue(), 'RS');
  assert.equal(await page.locator('#billing_address_1').inputValue(), 'Rua RS');
  assert.equal(await page.locator('#billing-petshop-number').inputValue(), '17');
  const beforeBlur = calls;
  await postcode.press('Tab');
  await page.waitForTimeout(200);
  assert.equal(calls, beforeBlur, 'Blur must not abort or duplicate the current lookup');
  await postcode.fill('01310100');
  await page.locator('#billing_address_1').fill('Rua editada manualmente');
  await page.waitForTimeout(1000);
  assert.equal(await page.locator('#billing_address_1').inputValue(), 'Rua editada manualmente');
  fail = true;
  await postcode.fill('91210320');
  await page.getByText('Falha induzida: preencha manualmente.', { exact: true }).waitFor();
  fail = false;
  await postcode.focus();
  await postcode.press('Tab');
  await page.waitForFunction(() => document.querySelector('#billing_address_1').value === 'Rua RS');
  await postcode.fill('01310100');
  await postcode.fill('123');
  await page.waitForTimeout(1000);
  assert.equal(await page.locator('#billing_city').inputValue(), 'Porto Alegre', 'Incomplete edited CEP must invalidate the old result');
  console.log('041 address races: passed (induced SP/RS latency, manual edits, repeat after error, incomplete CEP, number, deduplication)');
} finally { await browser.close(); }
