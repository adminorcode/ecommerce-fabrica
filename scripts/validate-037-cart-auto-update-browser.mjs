import fs from 'node:fs';
import { launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const failures = [];

const envFile = fs.existsSync('.env') ? fs.readFileSync('.env', 'utf8') : '';
const fileEnv = Object.fromEntries(envFile
  .split(/\r?\n/)
  .filter((line) => line && !line.trim().startsWith('#') && line.includes('='))
  .map((line) => {
    const index = line.indexOf('=');
    return [
      line.slice(0, index).trim(),
      line.slice(index + 1).trim().replace(/^['"]|['"]$/g, ''),
    ];
  }));

const adminUser = process.env.PETSHOP_ADMIN_USER
  || process.env.WORDPRESS_ADMIN_USER
  || fileEnv.WORDPRESS_ADMIN_USER
  || 'admin';
const adminPassword = process.env.PETSHOP_ADMIN_PASSWORD
  || process.env.WORDPRESS_ADMIN_PASSWORD
  || fileEnv.WORDPRESS_ADMIN_PASSWORD
  || 'password';

const moneyToMinor = (value) => {
  const normalized = String(value || '')
    .replace(/[^\d,.-]/g, '')
    .replace(/\./g, '')
    .replace(',', '.');
  const amount = Number.parseFloat(normalized);
  return Number.isFinite(amount) ? Math.round(amount * 100) : null;
};

const waitUntil = async (predicate, { timeout = 10000, interval = 100, label = 'condicao' } = {}) => {
  const started = Date.now();
  let last = null;

  while (Date.now() - started < timeout) {
    last = await predicate();
    if (last) return last;
    await new Promise((resolve) => {
      setTimeout(resolve, interval);
    });
  }

  throw new Error(`${label} nao atendida em ${timeout} ms. Ultimo valor: ${JSON.stringify(last)}`);
};

const login = async (page) => {
  await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.fill('#user_login', adminUser);
  await page.fill('#user_pass', adminPassword);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => null),
    page.click('#wp-submit'),
  ]);

  const loggedIn = await page.locator('#wpadminbar').count() > 0 || page.url().includes('/wp-admin');
  if (!loggedIn) {
    throw new Error('Login administrativo local nao foi confirmado.');
  }
};

const fetchCart = async (page) => page.evaluate(async () => {
  const response = await fetch('/wp-json/wc/store/v1/cart');
  return {
    status: response.status,
    nonce: response.headers.get('Nonce') || '',
    body: await response.json(),
  };
});

const clearCart = async (page) => page.evaluate(async () => {
  const probe = await fetch('/wp-json/wc/store/v1/cart');
  const nonce = probe.headers.get('Nonce') || '';
  const response = await fetch('/wp-json/wc/store/v1/cart/items', {
    method: 'DELETE',
    headers: { Nonce: nonce },
  });

  if (!response.ok) {
    throw new Error(`clear cart HTTP ${response.status}`);
  }
});

const addItem = async (page, productId) => page.evaluate(async (id) => {
  const probe = await fetch('/wp-json/wc/store/v1/cart');
  const nonce = probe.headers.get('Nonce') || '';
  const response = await fetch('/wp-json/wc/store/v1/cart/add-item', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Nonce: nonce,
    },
    body: JSON.stringify({ id, quantity: 1 }),
  });

  return {
    ok: response.ok,
    status: response.status,
    body: await response.json().catch(() => null),
  };
}, productId);

const productCandidates = async (page) => {
  const response = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/products?per_page=30&orderby=date&order=desc`);
  if (!response.ok()) {
    throw new Error(`Nao foi possivel listar produtos pela Store API (HTTP ${response.status()}).`);
  }

  const products = await response.json();
  return products
    .filter((product) => product?.id)
    .map((product) => ({ id: Number(product.id), name: product.name || `#${product.id}` }));
};

const prepareCart = async (page) => {
  const candidates = await productCandidates(page);
  for (const product of candidates) {
    await clearCart(page);
    const added = await addItem(page, product.id);
    if (!added.ok) continue;

    const cart = await fetchCart(page);
    const item = cart.body.items?.[0];
    if (item && Number(item.quantity_limits?.maximum || 1) >= 2) {
      return {
        id: Number(item.id),
        key: item.key,
        name: item.name || product.name,
        maximum: Number(item.quantity_limits.maximum),
      };
    }
  }

  throw new Error('Nenhum produto adicionavel com quantidade maxima maior que 1 foi encontrado.');
};

const cartDomState = async (page) => page.evaluate(() => {
  const row = document.querySelector('.wc-block-cart-items__row');
  const input = row?.querySelector('.wc-block-components-quantity-selector__input');
  const line = row?.querySelector('.wc-block-cart-item__total .wc-block-formatted-money-amount, .wc-block-cart-item__total .woocommerce-Price-amount');
  const total = document.querySelector('.wc-block-components-totals-footer-item .wc-block-formatted-money-amount, .wc-block-components-totals-footer-item .woocommerce-Price-amount');

  return {
    quantity: Number(input?.value || 0),
    lineText: line?.textContent || '',
    totalText: total?.textContent || '',
    marker: window.__petshop037NoReload || null,
    plusDisabled: Boolean(row?.querySelector('.wc-block-components-quantity-selector__button--plus')?.disabled),
    minusDisabled: Boolean(row?.querySelector('.wc-block-components-quantity-selector__button--minus')?.disabled),
  };
});

const miniDomState = async (page) => page.evaluate(() => {
  const drawer = document.querySelector('.wc-block-components-drawer__screen-overlay, .wc-block-mini-cart__drawer, .wc-block-components-drawer');
  const input = drawer?.querySelector('.wc-block-components-quantity-selector__input');
  const footer = drawer?.querySelector('.wc-block-mini-cart__footer');
  const footerValues = [...(footer?.querySelectorAll('.wc-block-formatted-money-amount, .woocommerce-Price-amount') || [])]
    .map((element) => element.textContent || '')
    .filter(Boolean);

  return {
    quantity: Number(input?.value || 0),
    subtotalText: footerValues[0] || '',
    marker: window.__petshop037MiniNoReload || null,
    hasDrawer: Boolean(drawer),
    plusDisabled: Boolean(drawer?.querySelector('.wc-block-components-quantity-selector__button--plus')?.disabled),
    minusDisabled: Boolean(drawer?.querySelector('.wc-block-components-quantity-selector__button--minus')?.disabled),
  };
});

const officialCartItem = (cartBody, itemId) => (
  (cartBody.items || []).find((entry) => Number(entry.id) === Number(itemId)) || cartBody.items?.[0]
);

const assertCartMatchesOfficial = async (page, item, expectedQuantity, label) => {
  await waitUntil(async () => {
    const cart = await fetchCart(page);
    const apiItem = officialCartItem(cart.body, item.id);
    const dom = await cartDomState(page);

    return apiItem
      && Number(apiItem.quantity) === expectedQuantity
      && dom.quantity === expectedQuantity
      && moneyToMinor(dom.lineText) === Number(apiItem.totals?.line_total)
      && moneyToMinor(dom.totalText) === Number(cart.body.totals?.total_price)
      && dom.marker === 'cart-marker';
  }, { timeout: 12000, label });
};

const miniUpdateFromBatchResponse = async (response, item, expectedQuantity, direction) => {
  const request = response.request();
  const requestBody = JSON.parse(request.postData() || '{}');
  const updateIndex = (requestBody.requests || [])
    .findIndex((entry) => entry?.path === '/wc/store/v1/cart/update-item');

  if (updateIndex < 0) {
    throw new Error(`${direction}: batch sem sub-request /wc/store/v1/cart/update-item.`);
  }

  const responseBody = await response.json();
  const update = responseBody.responses?.[updateIndex];
  const cartBody = update?.body || {};
  const apiItem = officialCartItem(cartBody, item.id);
  const apiQuantity = Number(apiItem?.quantity);
  const expectedSubtotal = Number(cartBody.totals?.total_items);

  if (Number(update?.status) < 200 || Number(update?.status) >= 300) {
    throw new Error(`${direction}: sub-response update-item falhou: ${JSON.stringify({
      status: update?.status ?? null,
      body: cartBody,
    })}`);
  }
  if (!apiItem) {
    throw new Error(`${direction}: sub-response update-item sem item ${item.id}: ${JSON.stringify(cartBody)}`);
  }
  if (apiQuantity !== expectedQuantity) {
    throw new Error(`${direction}: update-item retornou quantidade ${apiQuantity}; esperado ${expectedQuantity}.`);
  }

  return {
    item: {
      id: Number(apiItem.id),
      name: apiItem.name || item.name || null,
    },
    quantity: apiQuantity,
    itemLineTotal: Number(apiItem.totals?.line_total),
    subtotal: expectedSubtotal,
    status: update.status,
  };
};

const waitForMiniUpdateResponse = async (page, item, expectedQuantity, direction) => {
  const response = await page.waitForResponse(async (candidate) => {
    if (
      candidate.request().method() !== 'POST'
      || !candidate.url().includes('/wp-json/wc/store/v1/batch')
    ) {
      return false;
    }

    const requestBody = JSON.parse(candidate.request().postData() || '{}');
    return (requestBody.requests || [])
      .some((entry) => entry?.path === '/wc/store/v1/cart/update-item');
  }, { timeout: 5000 });

  return miniUpdateFromBatchResponse(response, item, expectedQuantity, direction);
};

const assertMiniMatchesOfficial = async (page, item, direction, official, label) => {
  let last = null;
  const started = Date.now();
  while (Date.now() - started < 12000) {
    const dom = await miniDomState(page);
    const domSubtotal = moneyToMinor(dom.subtotalText);
    last = {
      item: {
        id: item.id,
        name: item.name || item.label || null,
      },
      direction,
      responseStatus: official.status,
      responseQuantity: official.quantity,
      domQuantity: dom.quantity,
      responseSubtotal: official.subtotal,
      domSubtotal,
      domSubtotalText: dom.subtotalText,
      marker: dom.marker,
    };

    const matches = dom.quantity === official.quantity
      && domSubtotal === official.subtotal
      && dom.marker === 'mini-marker';
    if (matches) return last;

    await page.waitForTimeout(100);
  }

  throw new Error(`${label} nao atendida em 12000 ms. Ultimo estado: ${JSON.stringify(last)}`);
};

const assertMiniPersistsAfterReopen = async (page, item, direction, official) => {
  await page.keyboard.press('Escape').catch(() => null);
  await page.waitForTimeout(300);

  const markerAfterClose = await page.evaluate(() => window.__petshop037MiniNoReload || null);
  if (markerAfterClose !== 'mini-marker') {
    throw new Error(`${direction}: marcador de ausencia de reload perdeu apos fechar mini-cart.`);
  }

  const miniButton = page.locator('.wc-block-mini-cart__button, .wp-block-woocommerce-mini-cart button, .ct-header-cart a').first();
  await miniButton.click();
  await page.locator('.wc-block-components-drawer__screen-overlay .wc-block-components-quantity-selector__input').first().waitFor({ timeout: 15000 });

  const dom = await miniDomState(page);
  const domSubtotal = moneyToMinor(dom.subtotalText);
  if (
    dom.quantity !== official.quantity
    || domSubtotal !== official.subtotal
    || dom.marker !== 'mini-marker'
  ) {
    throw new Error(`${direction}: mini-cart nao persistiu apos reabrir: ${JSON.stringify({
      item: {
        id: item.id,
        name: item.name || item.label || null,
      },
      responseStatus: official.status,
      responseQuantity: official.quantity,
      domQuantity: dom.quantity,
      responseSubtotal: official.subtotal,
      domSubtotal,
      domSubtotalText: dom.subtotalText,
      marker: dom.marker,
    })}`);
  }
};

const sampleQuantityStability = async (page, expectedQuantity, reader, label) => {
  const samples = [];
  const started = Date.now();
  while (Date.now() - started < 1600) {
    const state = await reader(page);
    samples.push({ t: Date.now() - started, quantity: state.quantity });
    if (state.quantity !== expectedQuantity) {
      throw new Error(`${label}: quantidade mudou para ${state.quantity}; amostras ${JSON.stringify(samples)}.`);
    }
    await page.waitForTimeout(150);
  }
};

const clickCartQuantity = async (page, direction) => {
  const selector = direction === 'plus'
    ? '.wc-block-cart-items__row .wc-block-components-quantity-selector__button--plus'
    : '.wc-block-cart-items__row .wc-block-components-quantity-selector__button--minus';
  await page.locator(selector).first().click({ force: true, delay: 80 });
};

const clickMiniQuantity = async (page, direction) => {
  const result = await page.evaluate((requestedDirection) => {
    const drawer = document.querySelector('.wc-block-components-drawer__screen-overlay, .wc-block-mini-cart__drawer, .wc-block-components-drawer');
    const selector = requestedDirection === 'plus'
      ? '.wc-block-components-quantity-selector__button--plus'
      : '.wc-block-components-quantity-selector__button--minus';
    const button = drawer?.querySelector(selector);
    if (!button || button.disabled) {
      return {
        ok: false,
        disabled: Boolean(button?.disabled),
      };
    }

    button.click();
    return { ok: true };
  }, direction);

  if (!result.ok) {
    throw new Error(`Mini-cart: botao ${direction} indisponivel (disabled=${result.disabled}).`);
  }
};

const validateCartChange = async (page, item, direction, expectedQuantity) => {
  const posts = [];
  const started = Date.now();
  const onRequest = (request) => {
    if (request.method() === 'POST' && request.url().includes('/wc/store/v1/cart/update-item')) {
      posts.push({
        t: Date.now() - started,
        flush: request.headers()['x-petshop-qty-flush'] || '',
      });
    }
  };

  page.on('request', onRequest);
  try {
    await clickCartQuantity(page, direction);
    await page.waitForFunction((quantity) => (
      Number(document.querySelector('.wc-block-cart-items__row .wc-block-components-quantity-selector__input')?.value || 0) === quantity
    ), expectedQuantity, { timeout: 500 });

    await page.waitForTimeout(800);
    if (posts.length !== 0) {
      throw new Error(`${direction}: esperava 0 update-item nos primeiros 800 ms; recebeu ${posts.length}.`);
    }

    await waitUntil(async () => posts.length === 1, {
      timeout: 3500,
      interval: 50,
      label: `${direction}: update-item apos debounce`,
    });
    if (posts[0].flush !== '1') {
      throw new Error(`${direction}: update-item sem header X-Petshop-Qty-Flush: 1.`);
    }

    await assertCartMatchesOfficial(page, item, expectedQuantity, `${direction}: DOM e Store API`);
    await sampleQuantityStability(page, expectedQuantity, cartDomState, `${direction}: estabilidade /carrinho`);
    if (posts.length !== 1) {
      throw new Error(`${direction}: esperava exatamente 1 update-item; recebeu ${posts.length}.`);
    }
  } finally {
    page.off('request', onRequest);
  }
};

const validateMiniChange = async (page, item, direction, expectedQuantity) => {
  const updateResponse = waitForMiniUpdateResponse(page, item, expectedQuantity, direction);
  await clickMiniQuantity(page, direction);
  await page.waitForFunction((quantity) => {
    const drawer = document.querySelector('.wc-block-components-drawer__screen-overlay, .wc-block-mini-cart__drawer, .wc-block-components-drawer');
    return Number(drawer?.querySelector('.wc-block-components-quantity-selector__input')?.value || 0) === quantity;
  }, expectedQuantity, { timeout: 1000 });
  const official = await updateResponse;
  await assertMiniMatchesOfficial(page, item, direction, official, `${direction}: mini-cart DOM e Store API`);
  await assertMiniPersistsAfterReopen(page, item, direction, official);
  await sampleQuantityStability(page, expectedQuantity, miniDomState, `${direction}: estabilidade mini-cart`);
};

const browser = await launchBrowser();

try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await routeCanonicalNavigation(page, baseUrl);
  await login(page);

  const cartItem = await prepareCart(page);
  await page.goto(`${baseUrl}/carrinho/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.locator('.wc-block-cart-items__row .wc-block-components-quantity-selector__input').first().waitFor({ timeout: 15000 });
  await page.evaluate(() => {
    window.__petshop037NoReload = 'cart-marker';
  });

  await validateCartChange(page, cartItem, 'plus', 2);
  await validateCartChange(page, cartItem, 'minus', 1);

  const miniItem = await prepareCart(page);
  await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(1500);
  const cartQuantityAssets = await page.evaluate(() => [...document.scripts]
    .map((script) => script.src)
    .filter((src) => /cart-quantity-(guard|stability)/.test(src)));
  if (cartQuantityAssets.length > 0) {
    failures.push(`Mini-cart/Home carregou assets do 039: ${cartQuantityAssets.join(', ')}`);
  }

  const miniButton = page.locator('.wc-block-mini-cart__button, .wp-block-woocommerce-mini-cart button, .ct-header-cart a').first();
  await miniButton.click();
  await page.locator('.wc-block-components-drawer__screen-overlay .wc-block-components-quantity-selector__input').first().waitFor({ timeout: 15000 });
  await page.evaluate(() => {
    window.__petshop037MiniNoReload = 'mini-marker';
  });

  await validateMiniChange(page, miniItem, 'plus', 2);
  await validateMiniChange(page, miniItem, 'minus', 1);

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
  cart: 'quantidade, line total e cart total convergiram sem refresh',
  miniCart: 'quantidade e subtotal convergiram sem refresh',
  sourceOfTruth: 'WooCommerce Store API',
}, null, 2));
