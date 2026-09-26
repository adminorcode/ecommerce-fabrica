import { launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const failures = [];

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

const readOfficialCart = async (page) => {
  const response = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`);
  return {
    status: response.status(),
    nonce: response.headers()['nonce'] || '',
    body: await response.json(),
  };
};

const clearCart = async (page) => {
  const probe = await readOfficialCart(page);
  const response = await page.request.delete(`${baseUrl}/wp-json/wc/store/v1/cart/items`, {
    headers: { Nonce: probe.nonce },
  });

  if (!response.ok()) {
    throw new Error(`clear cart HTTP ${response.status()}`);
  }
};

const addItem = async (page, productId) => {
  const probe = await readOfficialCart(page);
  const response = await page.request.post(`${baseUrl}/wp-json/wc/store/v1/cart/add-item`, {
    headers: { Nonce: probe.nonce },
    data: { id: productId, quantity: 1 },
  });

  return {
    ok: response.ok(),
    status: response.status(),
    body: await response.json().catch(() => null),
  };
};

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

    const cart = await readOfficialCart(page);
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
  const totalRows = [...(drawer?.querySelectorAll('.wc-block-components-totals-item, .wc-block-mini-cart__footer-subtotal') || [])];
  const subtotalRow = totalRows.find((row) => {
    const label = row.querySelector('.wc-block-components-totals-item__label, .wc-block-mini-cart__footer-subtotal-label');
    return /subtotal/i.test(label?.textContent || '');
  }) || null;
  const subtotal = subtotalRow?.querySelector('.wc-block-components-totals-item__value, .wc-block-formatted-money-amount, .woocommerce-Price-amount');

  return {
    quantity: Number(input?.value || 0),
    subtotalText: subtotal?.textContent || '',
    subtotalFound: Boolean(subtotalRow && subtotal),
    subtotalCandidates: totalRows.map((row) => row.textContent?.trim() || '').filter(Boolean),
    marker: window.__petshop037MiniNoReload || null,
    hasDrawer: Boolean(drawer),
    plusDisabled: Boolean(drawer?.querySelector('.wc-block-components-quantity-selector__button--plus')?.disabled),
    minusDisabled: Boolean(drawer?.querySelector('.wc-block-components-quantity-selector__button--minus')?.disabled),
  };
});

const officialCartItem = (cartBody, itemId) => (
  (cartBody.items || []).find((entry) => Number(entry.id) === Number(itemId)) || cartBody.items?.[0]
);

const moneyField = (value) => Number(value || 0);

const itemLineTotal = (item, taxDisplay) => {
  const total = moneyField(item?.totals?.line_total);
  const tax = moneyField(item?.totals?.line_total_tax);
  return taxDisplay === 'incl' ? total + tax : total;
};

const cartItemsTotal = (cart, taxDisplay) => {
  const total = moneyField(cart?.totals?.total_items);
  const tax = moneyField(cart?.totals?.total_items_tax);
  return taxDisplay === 'incl' ? total + tax : total;
};

const resolveTaxDisplay = async (page) => {
  const started = Date.now();
  let last = null;

  while (Date.now() - started < 5000) {
    last = await page.evaluate(() => {
      const sources = [];

      if (window.wc?.wcSettings?.getSetting) {
        const sentinel = { missing: true };
        const value = window.wc.wcSettings.getSetting('displayCartPricesIncludingTax', sentinel);
        sources.push({
          name: 'window.wc.wcSettings.getSetting',
          type: value === sentinel ? 'missing' : typeof value,
        });
        if (typeof value === 'boolean') {
          return {
            source: 'window.wc.wcSettings.getSetting',
            displayIncludingTax: value,
            sources,
          };
        }
      } else {
        sources.push({
          name: 'window.wc.wcSettings.getSetting',
          type: typeof window.wc?.wcSettings?.getSetting,
        });
      }

      const allSettingsValue = window.wc?.wcSettings?.allSettings?.displayCartPricesIncludingTax;
      sources.push({
        name: 'window.wc.wcSettings.allSettings.displayCartPricesIncludingTax',
        type: typeof allSettingsValue,
      });
      if (typeof allSettingsValue === 'boolean') {
        return {
          source: 'window.wc.wcSettings.allSettings.displayCartPricesIncludingTax',
          displayIncludingTax: allSettingsValue,
          sources,
        };
      }

      const globalValue = window.wcSettings?.displayCartPricesIncludingTax;
      sources.push({
        name: 'window.wcSettings.displayCartPricesIncludingTax',
        type: typeof globalValue,
      });
      if (typeof globalValue === 'boolean') {
        return {
          source: 'window.wcSettings.displayCartPricesIncludingTax',
          displayIncludingTax: globalValue,
          sources,
        };
      }

      return {
        pending: true,
        sources,
        publicKeys: {
          wc: window.wc && typeof window.wc === 'object' ? Object.keys(window.wc).sort() : [],
          wcSettings: window.wcSettings && typeof window.wcSettings === 'object' ? Object.keys(window.wcSettings).sort() : [],
          wcSettingsObject: window.wc?.wcSettings && typeof window.wc.wcSettings === 'object' ? Object.keys(window.wc.wcSettings).sort() : [],
        },
      };
    });

    if (typeof last?.displayIncludingTax === 'boolean') {
      return {
        taxDisplay: last.displayIncludingTax ? 'incl' : 'excl',
        source: last.source,
      };
    }

    await page.waitForTimeout(100);
  }

  throw new Error(`Nao foi possivel determinar displayCartPricesIncludingTax publico do Woo Blocks: ${JSON.stringify(last)}`);
};

const officialCartState = (cartBody, item, taxDisplay, status = null) => {
  const apiItem = officialCartItem(cartBody, item.id);

  return {
    status,
    item: apiItem ? {
      id: Number(apiItem.id),
      quantity: Number(apiItem.quantity),
      lineTotal: itemLineTotal(apiItem, taxDisplay),
      lineTotalRaw: moneyField(apiItem.totals?.line_total),
      lineTotalTax: moneyField(apiItem.totals?.line_total_tax),
    } : null,
    total: moneyField(cartBody?.totals?.total_price),
    itemsTotal: cartItemsTotal(cartBody, taxDisplay),
    totalTax: moneyField(cartBody?.totals?.total_tax),
    itemsCount: Array.isArray(cartBody?.items) ? cartBody.items.length : null,
  };
};

const domCartStateForReport = (dom) => ({
  quantity: dom.quantity,
  lineText: dom.lineText,
  lineTotal: moneyToMinor(dom.lineText),
  subtotalText: dom.totalText,
  subtotal: moneyToMinor(dom.totalText),
  subtotalHasMoney: moneyToMinor(dom.totalText) !== null,
  marker: dom.marker,
  plusDisabled: dom.plusDisabled,
  minusDisabled: dom.minusDisabled,
});

const cartStateMatches = (dom, official, expectedQuantity) => (
  official.item
    && official.item.quantity === expectedQuantity
    && dom.quantity === expectedQuantity
    && moneyToMinor(dom.lineText) === official.item.lineTotal
    && moneyToMinor(dom.totalText) === official.total
    && dom.marker === 'cart-marker'
);

const assertCartMatchesOfficial = async (page, item, expectedQuantity, direction, officialFromResponse, taxDisplay, label) => {
  let last = null;
  const started = Date.now();

  while (Date.now() - started < 12000) {
    const dom = await cartDomState(page);
    const officialProbe = await readOfficialCart(page);
    const official = officialCartState(officialProbe.body, item, taxDisplay, officialProbe.status);
    last = {
      direction,
      expectedQuantity,
      url: page.url(),
      responseOfficial: officialFromResponse,
      official,
      dom: domCartStateForReport(dom),
      conditions: {
        responseQuantity: officialFromResponse.item?.quantity === expectedQuantity,
        officialQuantity: official.item?.quantity === expectedQuantity,
        domQuantity: dom.quantity === expectedQuantity,
        domLineTotal: moneyToMinor(dom.lineText) === officialFromResponse.item?.lineTotal,
        domSubtotalPresent: moneyToMinor(dom.totalText) !== null,
        domSubtotal: moneyToMinor(dom.totalText) === officialFromResponse.total,
        marker: dom.marker === 'cart-marker',
      },
    };

    if (
      officialFromResponse.item?.quantity === expectedQuantity
      && official.item?.quantity === expectedQuantity
      && cartStateMatches(dom, officialFromResponse, expectedQuantity)
      && cartStateMatches(dom, official, expectedQuantity)
    ) {
      return last;
    }

    await page.waitForTimeout(100);
  }

  throw new Error(`${label} nao atendida em 12000 ms. Ultimo estado: ${JSON.stringify(last)}`);
};

const cartUpdateFromResponse = async (response, item, expectedQuantity, direction, taxDisplay) => {
  const status = response.status();
  const body = await response.json().catch(() => null);
  const official = officialCartState(body || {}, item, taxDisplay, status);

  if (status < 200 || status >= 300) {
    throw new Error(`${direction}: update-item falhou: ${JSON.stringify({
      status,
      body: {
        itemsCount: Array.isArray(body?.items) ? body.items.length : null,
        totals: body?.totals ? {
          total_items: body.totals.total_items,
          total_items_tax: body.totals.total_items_tax,
          total_price: body.totals.total_price,
          total_tax: body.totals.total_tax,
        } : null,
      },
    })}`);
  }
  if (!official.item) {
    throw new Error(`${direction}: update-item sem item ${item.id}: ${JSON.stringify({
      status,
      itemsCount: official.itemsCount,
    })}`);
  }
  if (official.item.quantity !== expectedQuantity) {
    throw new Error(`${direction}: update-item retornou quantidade ${official.item.quantity}; esperado ${expectedQuantity}.`);
  }

  return official;
};

const waitForCartUpdateResponse = async (page, item, expectedQuantity, direction, taxDisplay) => {
  const response = await page.waitForResponse((candidate) => {
    if (
      candidate.request().method() !== 'POST'
      || !candidate.url().includes('/wp-json/wc/store/v1/cart/update-item')
    ) {
      return false;
    }

    let requestBody = {};
    try {
      requestBody = JSON.parse(candidate.request().postData() || '{}');
    } catch {
      return false;
    }

    return Number(requestBody.quantity) === expectedQuantity
      && (!item.key || requestBody.key === item.key);
  }, { timeout: 5000 });

  return cartUpdateFromResponse(response, item, expectedQuantity, direction, taxDisplay);
};

const miniUpdateFromBatchResponse = async (response, item, expectedQuantity, direction, taxDisplay) => {
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
  const expectedSubtotal = cartItemsTotal(cartBody, taxDisplay);

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

const waitForMiniUpdateResponse = async (page, item, expectedQuantity, direction, taxDisplay) => {
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

  return miniUpdateFromBatchResponse(response, item, expectedQuantity, direction, taxDisplay);
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
      subtotalFound: dom.subtotalFound,
      subtotalCandidates: dom.subtotalCandidates,
      marker: dom.marker,
    };

    const matches = dom.quantity === official.quantity
      && dom.subtotalFound
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
    || !dom.subtotalFound
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
      subtotalFound: dom.subtotalFound,
      subtotalCandidates: dom.subtotalCandidates,
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

const validateCartChange = async (page, item, direction, expectedQuantity, taxDisplay) => {
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
    const updateResponse = waitForCartUpdateResponse(page, item, expectedQuantity, direction, taxDisplay);
    updateResponse.catch(() => null);
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

    const official = await updateResponse;
    await assertCartMatchesOfficial(page, item, expectedQuantity, direction, official, taxDisplay, `${direction}: DOM e Store API`);
    await sampleQuantityStability(page, expectedQuantity, cartDomState, `${direction}: estabilidade /carrinho`);
    if (posts.length !== 1) {
      throw new Error(`${direction}: esperava exatamente 1 update-item; recebeu ${posts.length}.`);
    }
  } finally {
    page.off('request', onRequest);
  }
};

const validateMiniChange = async (page, item, direction, expectedQuantity, taxDisplay) => {
  const updateResponse = waitForMiniUpdateResponse(page, item, expectedQuantity, direction, taxDisplay);
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
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  await routeCanonicalNavigation(page, baseUrl);
  await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  if (await page.locator('#wpadminbar').count() > 0 || page.url().includes('/wp-admin')) {
    throw new Error('Fluxo 037 deve rodar como visitante, mas uma sessao admin foi detectada.');
  }

  const cartItem = await prepareCart(page);
  await page.goto(`${baseUrl}/carrinho/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.locator('.wc-block-cart-items__row .wc-block-components-quantity-selector__input').first().waitFor({ timeout: 15000 });
  const taxDisplay = await resolveTaxDisplay(page);
  await page.evaluate(() => {
    window.__petshop037NoReload = 'cart-marker';
  });

  await validateCartChange(page, cartItem, 'plus', 2, taxDisplay.taxDisplay);
  await validateCartChange(page, cartItem, 'minus', 1, taxDisplay.taxDisplay);

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

  await validateMiniChange(page, miniItem, 'plus', 2, taxDisplay.taxDisplay);
  await validateMiniChange(page, miniItem, 'minus', 1, taxDisplay.taxDisplay);

  await page.close();
  await context.close();
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
