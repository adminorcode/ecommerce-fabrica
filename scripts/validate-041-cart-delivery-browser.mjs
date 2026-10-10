import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { launchBrowser, routeCanonicalNavigation, createEvidenceDirectory } from './lib/browser-helpers.mjs';

const base = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidence = createEvidenceDirectory('041-cart-delivery');
const browser = await launchBrowser();
const results = [];
try {
  for (const width of [1440, 390]) {
    const context = await browser.newContext({ viewport: { width, height: 1000 } });
    let page;
    try {
      page = await context.newPage();
      const apiJson = async (url) => {
        const response = await page.request.get(url, { headers: { Accept: 'application/json' }, maxRetries: 2 });
        assert(response.ok() && response.headers()['content-type']?.includes('application/json'),
          `Expected Store API JSON: HTTP ${response.status()} ${response.url()} (${response.headers()['content-type']})`);
        return response.json();
      };
      await routeCanonicalNavigation(page, base);
      await page.goto(base);
      const homeOverflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      const products = await apiJson(`${base}/wp-json/wc/store/v1/products?per_page=50`);
      const product = products.find((item) => item.type === 'simple' && item.is_purchasable && item.is_in_stock && item.name.includes('Bandanas'))
        || products.find((item) => item.type === 'simple' && item.is_purchasable && item.is_in_stock);
      assert(product, 'Physical simple product required');
      await page.goto(product.permalink, { waitUntil: 'networkidle' });
      await page.locator('[data-petshop-shipping-form] [name="postcode"]').fill('91210-320');
      const previewResponse = page.waitForResponse((response) => response.url().includes('/admin-ajax.php')
        && response.request().postData()?.includes('petshop_calculate_shipping'));
      await page.locator('[data-petshop-shipping-form] button').click();
      const preview = await (await previewResponse).json();
      assert(preview.success && preview.data.rates.length > 1, 'Real provider baseline must return multiple rates');
      const probe = await page.request.get(`${base}/wp-json/wc/store/v1/cart`);
      const added = await page.request.post(`${base}/wp-json/wc/store/v1/cart/add-item`, {
        headers: { Nonce: probe.headers().nonce }, data: { id: product.id, quantity: 1 },
      });
      assert(added.ok());
      await page.goto(`${base}/carrinho/`, { waitUntil: 'networkidle' });
      const quote = page.locator('.petshop-cart-shipping');
      await page.waitForFunction(() => window.petshopQuotePreference?.read() === null && document.querySelector('.petshop-cart-shipping')?.getAttribute('aria-busy') === 'false');
      assert.equal(await quote.evaluate((node) => Boolean(node.closest('.wp-block-woocommerce-cart-totals-block'))), true, 'CEP and rates must be inside the totals column');
      const quoteBounds = await quote.boundingBox();
      const totalBounds = await page.locator('.wc-block-components-totals-footer-item').boundingBox();
      assert(quoteBounds.y + quoteBounds.height <= totalBounds.y, 'CEP must be above the estimated total');
      assert.equal(await quote.locator('.wc-block-components-radio-control').count() > 0, true, 'Delivery must use native WooCommerce presentation');
      const api = await apiJson(`${base}/wp-json/wc/store/v1/cart`);
      const pack = api.shipping_rates[0];
      assert.deepEqual(pack.shipping_rates.map((rate) => rate.rate_id).sort(), preview.data.rates.map((rate) => rate.id).sort(), 'Same product/quantity/CEP must expose the same available method IDs');
      assert.equal(await quote.locator('input[type="radio"]').count(), pack.shipping_rates.length, 'Every API rate must be selectable');
      const inclusive = await page.evaluate(() => wc.wcSettings.getSetting('displayCartPricesIncludingTax', false));
      for (const rate of pack.shipping_rates) {
        const radio = quote.locator(`input[type="radio"][value="${rate.rate_id}"]`);
        assert(await radio.isVisible());
        const expectedPrice = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: rate.currency_code,
          minimumFractionDigits: rate.currency_minor_unit, maximumFractionDigits: rate.currency_minor_unit })
          .format((Number(rate.price) + (inclusive ? Number(rate.taxes || 0) : 0)) / 10 ** rate.currency_minor_unit).replace(/\s/g, '');
        const shownPrice = await radio.locator('..').locator('.wc-block-formatted-money-amount').innerText();
        assert.equal(shownPrice.replace(/\s/g, ''), expectedPrice, 'Displayed rate price must match API minor units/tax/currency');
      }
      const postcode = quote.locator('input[name="postcode"]');
      const button = quote.locator('button[type="submit"]');
      await postcode.focus(); await page.keyboard.press('Tab');
      assert.equal(await button.evaluate((node) => node === document.activeElement), true, 'CEP keyboard tab must reach Calculate');
      const inputRect = await postcode.boundingBox(); const buttonRect = await button.boundingBox();
      assert(inputRect.width > 0 && buttonRect.x >= inputRect.x + inputRect.width, 'CEP and button must not overlap');
      const target = pack.shipping_rates.find((rate) => !rate.selected);
      assert(target);
      const targetRadio = quote.locator(`input[type="radio"][value="${target.rate_id}"]`);
      await targetRadio.focus(); await page.keyboard.press('Space');
      await page.waitForFunction(() => document.querySelector('.petshop-cart-shipping')?.getAttribute('aria-busy') === 'false');
      const selected = await apiJson(`${base}/wp-json/wc/store/v1/cart`);
      assert(selected.shipping_rates.find((item) => item.package_id === pack.package_id).shipping_rates.some((rate) => rate.rate_id === target.rate_id && rate.selected));
      const store = await page.evaluate(() => wp.data.select('wc/store/cart').getCartData());
      assert.equal(store.totals.total_price, selected.totals.total_price, 'Selection must preserve official total consistency');
      const previous = pack.shipping_rates.find((rate) => rate.selected);
      let injected = 0;
      const failSelection = async (route) => {
        const request = route.request();
        const payload = request.postDataJSON();
        const failure = { code: 'gate041_selection_error', message: 'Falha induzida na seleção. Tente novamente.', data: { status: 503 } };
        if (request.url().includes('/cart/select-shipping-rate')) {
          injected++;
          return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify(failure) });
        }
        if (request.url().includes('/batch') && payload?.requests?.some((item) => item.path.includes('/cart/select-shipping-rate'))) {
          assert(payload.requests.every((item) => item.path.includes('/cart/select-shipping-rate')), 'Failure harness requires a selection-only batch');
          injected++;
          return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ responses: payload.requests.map(() => ({ status: 503, headers: {}, body: failure })) }) });
        }
        return route.fallback();
      };
      await page.route('**/wc/store/v1/**', failSelection);
      await quote.locator(`input[type="radio"][value="${previous.rate_id}"]`).click();
      await page.getByText('Falha induzida na seleção. Tente novamente.', { exact: true }).waitFor();
      assert.equal(await targetRadio.isChecked(), true, 'Failed selection must roll back to the confirmed native rate');
      assert.equal(injected, 1, 'Failure must intercept the real selection request');
      await page.unroute('**/wc/store/v1/**', failSelection);
      await quote.locator(`input[type="radio"][value="${previous.rate_id}"]`).click();
      await page.waitForFunction(() => document.querySelector('.petshop-cart-shipping')?.getAttribute('aria-busy') === 'false');
      await page.reload({ waitUntil: 'networkidle' });
      await page.waitForFunction(() => window.wp?.data?.select('wc/store/cart').hasFinishedResolution('getCartData'));
      await page.evaluate(() => window.petshopCartOperations.waitForIdle(() => true, 2000));
      const reloaded = await apiJson(`${base}/wp-json/wc/store/v1/cart`);
      assert(reloaded.shipping_rates.find((item) => item.package_id === pack.package_id).shipping_rates.some((rate) => rate.rate_id === previous.rate_id && rate.selected));
      assert.equal(await page.locator('.petshop-cart-shipping input[type="radio"]:checked').inputValue(), previous.rate_id);
      assert.equal(await quote.evaluate((node) => node.scrollWidth <= node.clientWidth), true, 'CEP/rates must not overflow horizontally');
      const cartBounds = await page.locator('.wp-block-woocommerce-cart').boundingBox();
      assert(cartBounds.x >= 0 && cartBounds.x + cartBounds.width <= width + 1, 'Cart must fit the viewport');
      const pageOverflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      await page.screenshot({ path: path.join(evidence, `${width}.png`), fullPage: true });
      results.push({ width, productId: product.id, quantity: 1, rates: pack.shipping_rates.map((rate) => rate.rate_id), sidebar: true, keyboard: true, selection: true, selectionFailureRetry: true, reload: true, renderedPrices: true, pricesIncludeTax: inclusive, homeOverflow, pageOverflow, scope: 'real rates; installed tax display mode only' });
      await page.unrouteAll({ behavior: 'ignoreErrors' });
    } catch (error) {
      console.error(`041 delivery at ${width}: ${error.message}`);
      await page?.screenshot({ path: path.join(evidence, `${width}-failure.png`), fullPage: true }).catch(() => {});
      throw error;
    } finally {
      await page?.unrouteAll({ behavior: 'ignoreErrors' }).catch(() => {});
      await context.close();
    }
  }
  fs.writeFileSync(path.join(evidence, 'results.json'), JSON.stringify(results, null, 2));
  console.log('041 cart delivery: sidebar, same PDP/API methods, all rates visible, keyboard, selection/failure/retry/reload passed at 1440/390');
} finally { await browser.close(); }
