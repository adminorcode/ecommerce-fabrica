import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('039-cart-qty');
const overrideProductId = Number(process.env.PETSHOP_REPRO_PRODUCT_ID || 0);
const postcode = '01001-000';
const failures = [];
const fixtureNotes = [];
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

const discoverProducts = async (page) => {
  const selected = [];
  const rejected = [];
  const seen = new Set();
  const candidates = [];

  if (Number.isFinite(overrideProductId) && overrideProductId > 0) {
    candidates.push({
      id: overrideProductId,
      label: `override-${overrideProductId}`,
      source: 'PETSHOP_REPRO_PRODUCT_ID',
    });
  }

  const productsResponse = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/products?per_page=100&orderby=date&order=desc`);
  if (!productsResponse.ok()) {
    throw new Error(`Nao foi possivel listar produtos pela Store API (HTTP ${productsResponse.status()}).`);
  }
  const products = await productsResponse.json();
  for (const product of products) {
    const maximum = Number(product?.add_to_cart?.maximum || 1);
    if (
      product?.type !== 'simple'
      || product?.is_purchasable !== true
      || product?.is_in_stock !== true
      || maximum < 2
    ) {
      continue;
    }

    candidates.push({
      id: Number(product.id),
      label: `product-${product.id}`,
      name: product.name || `#${product.id}`,
      source: 'Store API discovery',
    });
  }

  for (const candidate of candidates) {
    if (!candidate.id || seen.has(candidate.id)) {
      continue;
    }
    seen.add(candidate.id);

    const result = await validateCandidate(page, candidate);
    if (!result.ok) {
      rejected.push({ id: candidate.id, label: candidate.label, source: candidate.source, reason: result.reason });
      continue;
    }

    selected.push(result.product);
    if (selected.length >= 2) break;
  }

  if (overrideProductId > 0 && !selected.some((product) => product.id === overrideProductId)) {
    fixtureNotes.push(`PETSHOP_REPRO_PRODUCT_ID=${overrideProductId} ignorado; fallback dinamico usado.`);
  }
  if (selected.length === 1) {
    fixtureNotes.push('Apenas um produto elegivel encontrado; gate 039 executou uma amostra real.');
  }
  if (selected.length === 0) {
    throw new Error(`Nenhum produto simples em estoque e adicionavel com quantidade > 1 foi encontrado. Rejeitados: ${JSON.stringify(rejected)}`);
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
  selectedProducts = await discoverProducts(page);

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
          flush: request.headers()['x-petshop-qty-flush'] || '',
        });
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
    } else if (itemPosts[0].flush !== '1') {
      failures.push(`${product.label}: update-item sem header X-Petshop-Qty-Flush: 1 (valor: ${itemPosts[0].flush || 'ausente'}).`);
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
  console.error(JSON.stringify({ failures, fixtureNotes, evidenceDir }, null, 2));
  process.exit(1);
}

console.log(JSON.stringify({ ok: true, products: selectedProducts, fixtureNotes, evidenceDir }, null, 2));
