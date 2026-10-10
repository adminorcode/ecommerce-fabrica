import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('039-cart-qty');
const primaryProductId = Number(process.env.PETSHOP_REPRO_PRODUCT_ID || 259);
const postcode = '01001-000';
const failures = [];
let selectedProducts = [];

const qtyInput = (page) => page.locator('.wc-block-components-quantity-selector__input').first();
const qtyPlus = (page) => page.locator('.wc-block-components-quantity-selector__button--plus').first();
const readQty = async (page) => Number(await qtyInput(page).inputValue());

const clearCart = async (page) => {
  const cartProbe = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`);
  const response = await page.request.delete(`${baseUrl}/wp-json/wc/store/v1/cart/items`, {
    headers: { Nonce: cartProbe.headers()['nonce'] || '' },
  });

  if (!response.ok()) {
    throw new Error(`clear cart HTTP ${response.status()}`);
  }
};

const addProductToCart = async (page, productId) => {
  const cartProbe = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`);
  return page.request.post(`${baseUrl}/wp-json/wc/store/v1/cart/add-item`, {
    headers: { Nonce: cartProbe.headers()['nonce'] || '' },
    data: { id: productId, quantity: 1 },
  });
};

const validateCandidate = async (page, candidate) => {
  await clearCart(page);

  const add = await addProductToCart(page, candidate.id);
  if (!add.ok()) {
    const body = await add.json().catch(async () => ({ raw: await add.text().catch(() => '') }));
    return {
      ok: false,
      reason: `add-item HTTP ${add.status()} ${body?.code || ''}`.trim(),
    };
  }

  const apiCart = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`);
  const apiBody = await apiCart.json();
  const apiItem = (apiBody.items || []).find((entry) => Number(entry.id) === Number(candidate.id)) || apiBody.items?.[0];
  const maximum = Number(apiItem?.quantity_limits?.maximum || 1);

  if (!apiItem) {
    return { ok: false, reason: 'produto nao entrou no carrinho' };
  }
  if (maximum < 2) {
    return { ok: false, reason: `quantity_limits.maximum=${maximum}` };
  }

  return {
    ok: true,
    product: {
      id: Number(apiItem.id),
      label: candidate.label,
      name: apiItem.name || candidate.name || candidate.label,
      maximum,
      source: candidate.source,
    },
  };
};

const requiredProducts = () => {
  if (!Number.isFinite(primaryProductId) || primaryProductId <= 0) {
    throw new Error(`PETSHOP_REPRO_PRODUCT_ID invalido: ${process.env.PETSHOP_REPRO_PRODUCT_ID}`);
  }

  const primary = {
    id: primaryProductId,
    label: primaryProductId === 259 ? 'bandana-neon' : `product-${primaryProductId}`,
    source: primaryProductId === 259 ? 'fixture-259' : 'PETSHOP_REPRO_PRODUCT_ID',
  };
  const second = {
    id: 1563,
    label: 'perfume-amostra',
    source: 'fixture-1563',
  };
  if (primary.id === second.id) {
    throw new Error('O gate 039 exige duas amostras distintas: a primaria e o produto 1563.');
  }

  return [primary, second];
};

const requireProducts = async (page) => {
  const selected = [];
  for (const candidate of requiredProducts()) {
    const result = await validateCandidate(page, candidate);
    if (!result.ok) {
      throw new Error(`${candidate.label} (${candidate.id}, ${candidate.source}) e amostra obrigatoria do 039 e nao entrou no carrinho anonimo: ${result.reason}`);
    }
    selected.push(result.product);
  }

  return selected;
};

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
let context;
let page;

const closeSafely = async (label, close) => {
  let timer;
  try {
    await Promise.race([
      close(),
      new Promise((_, reject) => {
        timer = setTimeout(() => reject(new Error('timeout ao fechar recurso')), 5000);
      }),
    ]);
  } catch (error) {
    failures.push(`${label}: ${error.message || error}`);
  } finally {
    clearTimeout(timer);
  }
};

const isNavigationFailure = (error) => (
  /page\.goto|net::|chrome-error/i.test(error?.message || String(error))
);

try {
  context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  page = await context.newPage();
  await routeCanonicalNavigation(page, baseUrl);
  await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  if (await page.locator('#wpadminbar').count() > 0 || page.url().includes('/wp-admin')) {
    throw new Error('Fluxo 039 deve rodar como visitante, mas uma sessao admin foi detectada.');
  }
  selectedProducts = await requireProducts(page);

  for (const product of selectedProducts) {
    try {
    await clearCart(page);

    const add = await addProductToCart(page, product.id);
    if (!add.ok()) {
      failures.push(`${product.label}: add-item HTTP ${add.status()}`);
      continue;
    }

    const cartResponse = await page.goto(`${baseUrl}/carrinho/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    if (page.url().startsWith('chrome-error://') || !cartResponse?.ok()) {
      throw new Error(`falha ao abrir carrinho: status=${cartResponse?.status() ?? 'sem resposta'} url=${page.url()}`);
    }
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
        itemPosts.push({
          at: Date.now(),
        });
      }
    };
    page.on('request', onItemPost);

    let fluidOk = true;
    for (let step = 1; step <= increments; step += 1) {
      await qtyPlus(page).click({ delay: 120 });
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
    await page.waitForFunction((expected) => {
      const store = window.wp.data.select('wc/store/cart');
      return store.getCartData().items[0]?.quantity === expected && !store.isItemPendingQuantity(store.getCartData().items[0].key);
    }, 1 + increments, { timeout: 15000 });
    if (!itemPosts.length) failures.push('Nenhuma atualizacao nativa foi confirmada.');
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
      const message = error.message || String(error);
      failures.push(`${product.label}: ${message}`);
      if (isNavigationFailure(error) || page.url().startsWith('chrome-error://')) {
        break;
      }
    }
  }
} catch (error) {
  failures.push(error.message || String(error));
} finally {
  if (page && !page.isClosed()) {
    await closeSafely('page.close', () => page.close());
  }
  if (context) {
    await closeSafely('context.close', () => context.close());
  }
  await closeSafely('browser.close', () => browser.close());
}

if (failures.length) {
  console.error(JSON.stringify({ failures, products: selectedProducts, evidenceDir }, null, 2));
  process.exit(1);
}

console.log(JSON.stringify({ ok: true, session: 'anonymous', products: selectedProducts, evidenceDir }, null, 2));
