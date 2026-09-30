import { launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const failures = [];

const productUrls = {
  simple: `${baseUrl}/produto/ticket-038-produto-simples/`,
  sale: `${baseUrl}/produto/ticket-038-produto-promocional/`,
  variable: `${baseUrl}/produto/ticket-038-produto-variavel/`,
};

const regexEscape = (value) => String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const parseMoney = (value, format) => {
  const decimalSeparator = format.decimalSeparator || '.';
  const thousandSeparator = format.thousandSeparator || ',';
  let normalized = String(value || '').replace(/[^\d,.-]/g, '');
  if (thousandSeparator) {
    normalized = normalized.replace(new RegExp(regexEscape(thousandSeparator), 'g'), '');
  }
  if (decimalSeparator && decimalSeparator !== '.') {
    normalized = normalized.replace(decimalSeparator, '.');
  }
  const number = Number.parseFloat(normalized);
  return Number.isFinite(number) ? number : null;
};

const moneyToNumber = async (page, value) => {
  const format = await page.evaluate(() => window.petshopProductConfig?.priceFormat || {});
  return parseMoney(value, format);
};

const closeEnough = (actual, expected) => (
  Number.isFinite(actual)
  && Math.abs(actual - expected) < 0.01
);

const readTotal = async (page) => page.evaluate(() => {
  const total = document.querySelector('[data-petshop-quantity-total]');
  const value = document.querySelector('[data-petshop-quantity-total-value]');
  const qty = document.querySelector('form.cart input.qty');
  return {
    hidden: !total || total.hidden || total.offsetParent === null,
    text: value?.textContent?.trim() || '',
    quantity: Number(qty?.value || 0),
    min: qty?.getAttribute('min') || '',
    max: qty?.getAttribute('max') || '',
    step: qty?.getAttribute('step') || '',
    marker: window.__petshop038NoRefresh || null,
  };
});

const setQuantity = async (page, quantity) => {
  const input = page.locator('form.cart input.qty').first();
  await input.fill(String(quantity));
  await input.dispatchEvent('input');
  await input.dispatchEvent('change');
};

const assertHiddenTotal = async (page, label) => {
  const state = await readTotal(page);
  if (!state.hidden || state.text !== '') {
    failures.push(`${label}: total deveria estar oculto, veio ${JSON.stringify(state)}.`);
  }
};

const assertTotal = async (page, expected, label) => {
  await page.waitForFunction((amount) => {
    const text = document.querySelector('[data-petshop-quantity-total-value]')?.textContent || '';
    const format = window.petshopProductConfig?.priceFormat || {};
    const decimalSeparator = format.decimalSeparator || '.';
    const thousandSeparator = format.thousandSeparator || ',';
    let normalized = text.replace(/[^\d,.-]/g, '');
    if (thousandSeparator) normalized = normalized.replace(new RegExp(thousandSeparator.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), '');
    if (decimalSeparator && decimalSeparator !== '.') normalized = normalized.replace(decimalSeparator, '.');
    const value = Number.parseFloat(normalized);
    return Number.isFinite(value) && Math.abs(value - amount) < 0.01;
  }, expected, { timeout: 3000 });

  const state = await readTotal(page);
  const actual = await moneyToNumber(page, state.text);
  if (!closeEnough(actual, expected) || state.hidden) {
    failures.push(`${label}: total ${state.text || '(vazio)'}; esperado ${expected.toFixed(2)}.`);
  }
  if (state.marker !== 'pdp-marker') {
    failures.push(`${label}: marcador de ausencia de refresh foi perdido.`);
  }
  const expectedText = await page.evaluate((amount) => {
    const format = window.petshopProductConfig?.priceFormat || {};
    const decimalSeparator = format.decimalSeparator || '.';
    const thousandSeparator = format.thousandSeparator || ',';
    const decimals = Number.isInteger(format.decimals) ? format.decimals : 2;
    const priceFormat = format.priceFormat || '%1$s%2$s';
    const currencySymbol = format.currencySymbol || '';
    const fixed = amount.toFixed(Math.max(0, decimals));
    const [integer, decimal = ''] = fixed.split('.');
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);
    const money = decimals > 0 ? `${grouped}${decimalSeparator}${decimal}` : grouped;
    return priceFormat.replace('%1$s', currencySymbol).replace('%2$s', money);
  }, expected);
  if (state.text !== expectedText) {
    failures.push(`${label}: texto monetario visivel ${state.text || '(vazio)'}; esperado ${expectedText}.`);
  }
};

const validateSimpleProduct = async (page) => {
  await page.goto(productUrls.simple, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.locator('[data-petshop-quantity-total]').waitFor({ timeout: 10000 });
  await page.evaluate(() => {
    window.__petshop038NoRefresh = 'pdp-marker';
  });

  await assertHiddenTotal(page, 'produto simples qty 1');
  const placement = await page.evaluate(() => {
    const total = document.querySelector('[data-petshop-quantity-total]');
    const price = document.querySelector('.entry-summary > .petshop-product-price-row > .price, .summary > .petshop-product-price-row > .price');
    return {
      besidePrice: Boolean(price && total && price.parentElement === total.parentElement),
      outsideCart: !total?.closest('form.cart'),
    };
  });
  if (!placement.besidePrice || !placement.outsideCart) {
    failures.push(`produto simples: total deveria ficar ao lado do preco, veio ${JSON.stringify(placement)}.`);
  }
  await setQuantity(page, 3);
  await assertTotal(page, 59.70, 'produto simples qty 3');
  await setQuantity(page, 2);
  await assertTotal(page, 39.80, 'produto simples qty 2');

  const state = await readTotal(page);
  if (state.min !== '1' || state.max !== '20' || state.step !== '1') {
    failures.push(`produto simples: min/max/step nativos inesperados min=${state.min} max=${state.max} step=${state.step}.`);
  }
};

const validateSaleProduct = async (page) => {
  await page.goto(productUrls.sale, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.locator('[data-petshop-quantity-total]').waitFor({ timeout: 10000 });
  await page.evaluate(() => {
    window.__petshop038NoRefresh = 'pdp-marker';
  });

  await assertHiddenTotal(page, 'produto promocional qty 1');
  await setQuantity(page, 2);
  await assertTotal(page, 43.00, 'produto promocional qty 2');
};

const chooseVariation = async (page, value) => {
  const select = page.locator('form.variations_form select').first();
  await select.selectOption(value);
  await page.waitForFunction(() => {
    const variationId = document.querySelector('form.variations_form input[name="variation_id"]')?.value || '';
    return Number(variationId) > 0;
  }, null, { timeout: 5000 });
};

const validateVariableProduct = async (page) => {
  await page.goto(productUrls.variable, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.locator('form.variations_form').waitFor({ timeout: 10000 });
  await page.evaluate(() => {
    window.__petshop038NoRefresh = 'pdp-marker';
  });

  let state = await readTotal(page);
  if (!state.hidden || state.text !== '') {
    failures.push(`produto variavel antes da selecao: total deveria estar oculto/vazio, veio ${JSON.stringify(state)}.`);
  }

  await chooseVariation(page, 'p');
  await assertHiddenTotal(page, 'produto variavel P qty 1');
  await setQuantity(page, 2);
  await assertTotal(page, 48.00, 'produto variavel P qty 2');

  await chooseVariation(page, 'g');
  await assertTotal(page, 56.00, 'produto variavel G promocional qty 2');

  await page.locator('form.variations_form .reset_variations').click();
  await page.waitForTimeout(300);
  state = await readTotal(page);
  if (!state.hidden || state.text !== '') {
    failures.push(`produto variavel reset: total deveria limpar/ocultar, veio ${JSON.stringify(state)}.`);
  }
};

const readOfficialCart = async (page) => {
  const response = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`);
  return { headers: response.headers(), body: await response.json() };
};

const validateCartRegression = async (page) => {
  let cart = await readOfficialCart(page);
  await page.request.delete(`${baseUrl}/wp-json/wc/store/v1/cart/items`, {
    headers: { Nonce: cart.headers.nonce || '' },
  });

  cart = await readOfficialCart(page);
  const add = await page.request.post(`${baseUrl}/wp-json/wc/store/v1/cart/add-item`, {
    headers: { Nonce: cart.headers.nonce || '' },
    data: { id: await page.evaluate(async () => {
      const response = await fetch('/wp-json/wc/store/v1/products?search=Ticket%20038%20Produto%20Simples');
      const products = await response.json();
      return Number(products.find((product) => product.sku === 'PETSHOP-038-SIMPLE')?.id || products[0]?.id || 0);
    }), quantity: 1 },
  });
  if (!add.ok()) {
    failures.push(`carrinho: add-item falhou HTTP ${add.status()}.`);
    return;
  }

  await page.goto(`${baseUrl}/carrinho/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.locator('.wc-block-cart-items__row .wc-block-components-quantity-selector__input').first().waitFor({ timeout: 15000 });
  const input = page.locator('.wc-block-cart-items__row .wc-block-components-quantity-selector__input').first();
  await input.fill('2');
  await input.press('Enter');

  await page.waitForFunction(async () => {
    const cartResponse = await fetch('/wp-json/wc/store/v1/cart');
    const cartBody = await cartResponse.json();
    const item = cartBody.items?.[0];
    return Number(item?.quantity) === 2 && Number(item?.totals?.line_total) > 0 && Number(cartBody?.totals?.total_price) > 0;
  }, null, { timeout: 10000 });
};

const browser = await launchBrowser();

try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await routeCanonicalNavigation(page, baseUrl);
  await validateSimpleProduct(page);
  await validateSaleProduct(page);
  await validateVariableProduct(page);
  await validateCartRegression(page);
  await page.close();
} catch (error) {
  failures.push(error.message || String(error));
} finally {
  await browser.close();
}

if (failures.length) {
  console.error(JSON.stringify({ failures }, null, 2));
  process.exit(1);
}

console.log(JSON.stringify({
  ok: true,
  pdp: 'total por quantidade validado para produto simples, promocional e variável',
  cart: 'regressao leve Store API/Cart Block validada sem calculo paralelo',
}, null, 2));
