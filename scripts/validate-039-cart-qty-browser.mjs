import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('039-cart-qty');
const products = [
  { id: Number(process.env.PETSHOP_REPRO_PRODUCT_ID || 259), label: 'bandana-neon' },
  { id: 1563, label: 'perfume-amostra' },
];
const postcode = '01001-000';
const failures = [];

const qtyInput = (page) => page.locator('.wc-block-components-quantity-selector__input').first();
const qtyPlus = (page) => page.locator('.wc-block-components-quantity-selector__button--plus').first();
const readQty = async (page) => Number(await qtyInput(page).inputValue());

const waitShippingReady = async (page) => {
  await page.waitForFunction(() => {
    const status = document.querySelector('[data-petshop-cart-shipping-status]')?.textContent || '';
    const postcodeDigits = String(
      document.querySelector('#petshop-cart-shipping-postcode')?.value || '',
    ).replace(/\D/g, '');
    return postcodeDigits === '01001000'
      && !/Atualizando/.test(status)
      && !document.querySelector('[data-petshop-cart-shipping-form] button[type="submit"]')?.disabled;
  }, null, { timeout: 30000 });
};

const assertQtyHolds = async (page, expected, label) => {
  try {
    await page.waitForFunction((qty) => (
      Number(document.querySelector('.wc-block-components-quantity-selector__input')?.value || 0) === qty
    ), expected, { timeout: 8000 });
  } catch (_error) {
    failures.push(`${label}: quantidade nao chegou a ${expected} (atual ${(await readQty(page))}).`);
    return false;
  }

  const samples = [];
  const started = Date.now();
  while (Date.now() - started < 5000) {
    const qty = await readQty(page);
    samples.push({ t: Date.now() - started, qty });
    if (qty !== expected) {
      failures.push(`${label}: quantidade piscou para ${qty} (esperado ${expected}, amostras ${JSON.stringify(samples)}).`);
      return false;
    }
    await page.waitForTimeout(150);
  }
  return true;
};

const browser = await launchBrowser();

try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await routeCanonicalNavigation(page, baseUrl);
  await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded', timeout: 30000 });

  for (const product of products) {
    try {
    await page.evaluate(async () => {
      const nonce = (await fetch('/wp-json/wc/store/v1/cart')).headers.get('Nonce') || '';
      await fetch('/wp-json/wc/store/v1/cart/items', { method: 'DELETE', headers: { Nonce: nonce } });
    });

    const cartProbe = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`);
    const add = await page.request.post(`${baseUrl}/wp-json/wc/store/v1/cart/add-item`, {
      headers: { Nonce: cartProbe.headers()['nonce'] || '' },
      data: { id: product.id, quantity: 1 },
    });
    if (!add.ok()) {
      failures.push(`${product.label}: add-item HTTP ${add.status()}`);
      continue;
    }

    await page.goto(`${baseUrl}/carrinho/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await qtyInput(page).waitFor({ timeout: 15000 });
    await page.locator('[data-petshop-cart-shipping-form]').waitFor({ timeout: 15000 });

    if (await page.locator('#custom-postcode-form:visible, .woo-better-info-block:visible').count()) {
      failures.push(`${product.label}: widget de CEP do plugin brasileiro visivel.`);
    }
    if (await page.locator('#petshop-cart-shipping-postcode').count() !== 1) {
      failures.push(`${product.label}: CEP proprio do carrinho ausente.`);
    }

    await qtyPlus(page).click({ delay: 120 });
    if (!(await assertQtyHolds(page, 2, `${product.label} antes do CEP`))) {
      continue;
    }

    const persistReset = page.waitForRequest((request) => (
      request.method() === 'POST' && request.url().includes('/wc/store/v1/cart/update-item')
    ), { timeout: 5000 });
    await qtyInput(page).fill('1');
    await qtyInput(page).press('Enter');
    await page.waitForFunction(() => Number(document.querySelector('.wc-block-components-quantity-selector__input')?.value || 0) === 1, null, { timeout: 15000 });
    await persistReset;

    await page.locator('#petshop-cart-shipping-postcode').fill(postcode);
    await page.locator('[data-petshop-cart-shipping-form] button[type="submit"]').click();
    await waitShippingReady(page);
    await page.waitForFunction(async () => {
      const cart = await (await fetch('/wp-json/wc/store/v1/cart')).json();
      return String(cart.shipping_address?.postcode || '').replace(/\D/g, '') === '01001000';
    }, null, { timeout: 15000 });

    const maximum = await page.evaluate(async (productId) => {
      const cart = await (await fetch('/wp-json/wc/store/v1/cart')).json();
      const item = (cart.items || []).find((entry) => Number(entry.id) === Number(productId)) || cart.items?.[0];
      return Number(item?.quantity_limits?.maximum || 1);
    }, product.id);
    const increments = Math.max(0, Math.min(2, maximum - 1));
    if (increments < 1) {
      failures.push(`${product.label}: produto sem limite de quantidade para testar aumento.`);
      continue;
    }

    const itemPosts = [];
    const onItemPost = (request) => {
      if (request.method() === 'POST' && request.url().includes('/wc/store/v1/cart/update-item')) {
        itemPosts.push(Date.now());
      }
    };
    page.on('request', onItemPost);

    let fluidOk = true;
    for (let step = 1; step <= increments; step += 1) {
      await qtyPlus(page).click({ force: true, delay: 120 });
      try {
        await page.waitForFunction((qty) => (
          Number(document.querySelector('.wc-block-components-quantity-selector__input')?.value || 0) === qty
        ), 1 + step, { timeout: 500 });
      } catch (_error) {
        failures.push(`${product.label}: quantidade nao acompanhou o +${step} de forma fluida (atual ${(await readQty(page))}).`);
        fluidOk = false;
        break;
      }
    }
    if (await qtyPlus(page).isDisabled()) {
      failures.push(`${product.label}: botao + ficou desabilitado depois do clique.`);
    }

    await page.waitForTimeout(800);
    if (itemPosts.length !== 0) {
      failures.push(`${product.label}: frete disparou ${itemPosts.length} update-item antes de 1s.`);
    }
    try {
      await page.waitForRequest((request) => (
        request.method() === 'POST' && request.url().includes('/wc/store/v1/cart/update-item')
      ), { timeout: 2500 });
    } catch (_error) {
      failures.push(`${product.label}: frete nao recalculou 1s apos a quantidade.`);
    }
    await page.waitForTimeout(800);
    if (itemPosts.length !== 1) {
      failures.push(`${product.label}: frete deveria ter 1 update-item apos a quantidade, veio ${itemPosts.length}.`);
    }
    page.off('request', onItemPost);

    const expectedQty = 1 + increments;
    if (fluidOk && !(await assertQtyHolds(page, expectedQty, `${product.label} apos o CEP`))) {
      continue;
    }
    if (!fluidOk) {
      continue;
    }
    const apiCart = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`);
    const apiBody = await apiCart.json();
    const apiItem = (apiBody.items || []).find((entry) => Number(entry.id) === Number(product.id)) || apiBody.items?.[0];
    if ((apiItem?.quantity ?? null) !== expectedQty) {
      failures.push(`${product.label}: Store API quantity=${apiItem?.quantity ?? null} apos o CEP.`);
    }

    await page.screenshot({ path: path.join(evidenceDir, `${product.label}-after-cep.png`), fullPage: true });
    } catch (error) {
      failures.push(`${product.label}: ${error.message || error}`);
    }
  }

  await page.close();
} finally {
  await browser.close();
}

if (failures.length) {
  console.error(JSON.stringify({ failures, evidenceDir }, null, 2));
  process.exit(1);
}

console.log(JSON.stringify({ ok: true, evidenceDir }, null, 2));
