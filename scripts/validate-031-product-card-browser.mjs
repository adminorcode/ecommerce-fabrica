import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const canonicalHost = process.env.PETSHOP_CANONICAL_HOST || new URL(baseUrl).host;
const evidenceDir = createEvidenceDirectory('031');
const browser = await launchBrowser();
const failures = [];
const results = {
  surfaces: {},
  initialVariation: null,
  wildcard: null,
  chipChanges: [],
  storeApi: [],
  miniCart: null,
  incomplete: null,
  soldout: null,
  personalizable: null,
  promotion: null,
};

const record = (condition, message) => {
  if (!condition) failures.push(message);
};

const pageUrl = (pathAndQuery) => `${baseUrl}${pathAndQuery}`;
const shopFixtureUrls = () => [
  pageUrl('/loja/?petshop_categories%5B0%5D=bandanas&orderby=date'),
  pageUrl('/loja/?product_cat%5B%5D=bandanas&orderby=date'),
  pageUrl('/categoria-produto/plano-031-card-variavel/'),
];

const gotoFixtureListing = async (page) => {
  for (const url of shopFixtureUrls()) {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
    if (await findPlan031Card(page).count() > 0) {
      return url;
    }
  }

  return shopFixtureUrls()[0];
};

const storeApiCart = async (page) => {
  const response = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`, {
    headers: { Host: canonicalHost },
    timeout: 20000,
  });
  return response.ok() ? response.json() : null;
};

const clearCart = async (page) => {
  const response = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`, {
    headers: { Host: canonicalHost },
    timeout: 20000,
  });
  const nonce = response.headers().nonce || response.headers().Nonce || '';
  await page.request.delete(`${baseUrl}/wp-json/wc/store/v1/cart/items`, {
    headers: { Host: canonicalHost, Nonce: nonce },
    timeout: 20000,
  }).catch(() => null);
};

const cardMetrics = async (card) => card.evaluate((element) => {
  const root = element.querySelector('[data-petshop-variable-card]');
  const price = element.querySelector('[data-petshop-card-price]');
  const image = element.querySelector('.ct-image-container img, img.wp-post-image, .woocommerce-loop-product__link img, img');
  const selected = [...element.querySelectorAll('[data-petshop-attribute][aria-pressed="true"]')]
    .map((chip) => ({ attribute: chip.dataset.petshopAttribute, value: chip.dataset.value, text: chip.textContent.trim() }));
  const chips = [...element.querySelectorAll('[data-petshop-attribute]')]
    .map((chip) => ({ attribute: chip.dataset.petshopAttribute, value: chip.dataset.value, text: chip.textContent.trim(), pressed: chip.getAttribute('aria-pressed') }));
  const variations = JSON.parse(root?.dataset.variations || '[]');
  const initialVariationId = Number(root?.dataset.initialVariationId || 0);
  const initialVariation = variations.find((variation) => Number(variation.id) === initialVariationId) || null;
  const button = element.querySelector('[data-petshop-buy-now]');

  const exactSelection = Object.fromEntries(selected.map((chip) => [chip.attribute, chip.value]));
  const resolvedVariation = variations.find((variation) => Object.entries(exactSelection).every(([key, value]) => {
    const expected = variation.attributes?.[key] ?? '';
    return expected === value;
  })) || variations.find((variation) => Number(variation.id) === initialVariationId) || null;

  return {
    title: element.querySelector('.woocommerce-loop-product__title, h2, h3')?.textContent.trim() || '',
    priceText: price?.textContent.replace(/\s+/g, ' ').trim() || '',
    priceHtml: price?.innerHTML || '',
    imageSrc: image?.currentSrc || image?.src || image?.getAttribute('src') || '',
    initialVariationId,
    initialVariation,
    selected,
    chips,
    buttonText: button?.textContent.replace(/\s+/g, ' ').trim() || '',
    hasSeeOptions: /Ver opções|Select options/i.test(element.textContent || ''),
    hasRangeDash: /R\$\s*[\d.,]+\s*[–-]\s*R\$/i.test(price?.textContent || ''),
    resolvedVariation,
  };
});

const findPlan031Card = (page) => page.locator('li.product').filter({ hasText: 'Produto Variável Plano 031' }).first();

const visibleFixtureProducts = async (page) => page.locator('li.product').evaluateAll((products) => products
  .map((product) => product.textContent.replace(/\s+/g, ' ').trim())
  .filter(Boolean)
  .slice(0, 8));

const verifySurface = async (page, label, targetUrl) => {
  const urls = Array.isArray(targetUrl) ? targetUrl : [targetUrl];
  let card = null;
  const attempts = [];

  for (const url of urls) {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
    card = findPlan031Card(page);
    attempts.push({
      requestedUrl: url,
      finalUrl: page.url(),
      productCount: await page.locator('li.product').count(),
      fixtureCount: await card.count(),
      variableCardCount: await page.locator('[data-petshop-variable-card]').count(),
      products: await visibleFixtureProducts(page),
    });
    if (await card.count() > 0) {
      await card.waitFor({ state: 'visible', timeout: 20000 });
      break;
    }
  }

  if (!card || await card.count() === 0) {
    throw new Error(`${label}: produto fixture nao encontrado; tentativas: ${JSON.stringify(attempts)}`);
  }

  await card.waitFor({ state: 'visible', timeout: 20000 });
  const metrics = await cardMetrics(card);

  record(!metrics.hasRangeDash, `${label}: card variavel exibiu faixa min-max`);
  record(metrics.priceText.includes('10'), `${label}: preco inicial nao parece ser o menor/promocional da variacao inicial (${metrics.priceText})`);
  record(metrics.chips.length >= 2, `${label}: chips ausentes`);
  record(metrics.selected.some((chip) => chip.attribute === 'size' && chip.value === 'small'), `${label}: chip inicial de size nao ficou em small`);
  record(metrics.selected.some((chip) => chip.attribute === 'color' && chip.value === 'red'), `${label}: chip inicial de color nao ficou em red`);
  record(metrics.buttonText === 'Comprar agora', `${label}: CTA inesperado (${metrics.buttonText})`);
  record(!metrics.hasSeeOptions, `${label}: Ver opções apareceu no card`);
  record(metrics.initialVariationId > 0, `${label}: data-initial-variation-id ausente`);
  record(metrics.initialVariation?.attributes?.size === '', `${label}: fixture inicial nao é wildcard/any`);
  record(metrics.initialVariation?.purchasable && metrics.initialVariation?.inStock, `${label}: initialVariation nao compravel/em estoque`);
  record(metrics.imageSrc.includes('plan-031-initial'), `${label}: imagem inicial nao corresponde a initialVariation (${metrics.imageSrc})`);

  const mediumChip = card.locator('[data-petshop-attribute="size"][data-value="medium"]').first();
  await mediumChip.click();
  await page.waitForTimeout(250);
  const afterMedium = await cardMetrics(card);
  record(afterMedium.priceText.includes('30'), `${label}: troca para medium nao atualizou preco (${afterMedium.priceText})`);
  record(afterMedium.imageSrc.includes('plan-031-medium'), `${label}: troca para medium nao atualizou imagem (${afterMedium.imageSrc})`);

  results.surfaces[label] = {
    url: new URL(page.url()).pathname + new URL(page.url()).search,
    initialVariationId: metrics.initialVariationId,
    initialPrice: metrics.priceText,
    afterMediumPrice: afterMedium.priceText,
  };

  await page.screenshot({ path: path.join(evidenceDir, `${label}.png`), fullPage: true });

  return { card, metrics, afterMedium };
};

const addCurrentCardToCart = async (page, card, expectedVariationId, label) => {
  const beforeCart = await storeApiCart(page);
  const beforeCount = beforeCart?.items_count || beforeCart?.items?.reduce((total, item) => total + Number(item.quantity || 0), 0) || 0;
  const miniBefore = await page.locator('.wc-block-mini-cart__button').first().getAttribute('aria-label').catch(() => '');
  const responses = [];
  const requests = [];
  page.on('request', (request) => {
    if (request.method() === 'POST' && request.url().includes('/wc/store/v1/cart/add-item')) {
      requests.push(request.postDataJSON());
    }
  });
  page.on('response', async (response) => {
    if (response.url().includes('/wc/store/v1/cart/add-item')) {
      responses.push(response);
    }
  });

  const [addResponse] = await Promise.all([
    page.waitForResponse((response) => response.url().includes('/wc/store/v1/cart/add-item'), { timeout: 20000 }),
    card.locator('[data-petshop-buy-now]').first().click(),
  ]);
  await page.waitForTimeout(800);
  const cart = await storeApiCart(page);
  const item = cart?.items?.find((candidate) => Number(candidate.variation_id || candidate.id) === expectedVariationId) || null;
  const miniAfter = await page.locator('.wc-block-mini-cart__button').first().getAttribute('aria-label').catch(() => '');
  const responseBody = addResponse ? await addResponse.json().catch(() => ({})) : {};
  const cartCount = cart?.items_count || cart?.items?.reduce((total, candidate) => total + Number(candidate.quantity || 0), 0) || 0;

  record(addResponse?.ok(), `${label}: add-item nao retornou sucesso`);
  record(Number(requests.at(-1)?.id || 0) === expectedVariationId, `${label}: payload add-item enviou ${requests.at(-1)?.id}, esperado ${expectedVariationId}`);
  record(Boolean(item), `${label}: carrinho nao contem variation ID esperada ${expectedVariationId}`);
  record(cartCount > beforeCount, `${label}: contagem do carrinho nao incrementou`);
  record(miniAfter && miniAfter !== miniBefore, `${label}: minicarrinho nao atualizou aria-label`);

  results.storeApi.push({
    label,
    expectedVariationId,
    requestPayload: requests.at(-1) || null,
    responseOk: addResponse?.ok() || false,
    itemId: item?.id || null,
    variation: item?.variation || null,
    miniBefore,
    miniAfter,
    responseItemCount: responseBody?.items_count || null,
  });

  return { cart, item, miniBefore, miniAfter };
};

const verifyIncompleteSelection = async (page) => {
  await clearCart(page);
  await page.goto(pageUrl('/'), { waitUntil: 'networkidle', timeout: 30000 });
  const card = findPlan031Card(page);
  await card.waitFor({ state: 'visible', timeout: 20000 });
  await card.evaluate((element) => {
    element.querySelectorAll('[data-petshop-attribute="color"]').forEach((chip) => {
      chip.classList.remove('is-selected');
      chip.setAttribute('aria-pressed', 'false');
    });
  });
  const addRequests = [];
  page.on('requestfinished', (request) => {
    if (request.method() === 'POST' && request.url().includes('/wc/store/v1/cart/add-item')) {
      addRequests.push(request.url());
    }
  });
  await card.locator('[data-petshop-buy-now]').first().click();
  await page.waitForTimeout(500);
  const active = await page.evaluate(() => ({
    attribute: document.activeElement?.dataset?.petshopAttribute || '',
    value: document.activeElement?.dataset?.value || '',
  }));
  const cart = await storeApiCart(page);
  const count = cart?.items_count || cart?.items?.length || 0;

  record(addRequests.length === 0, `incompleta: add-item foi chamado ${addRequests.length} vez(es)`);
  record(count === 0, `incompleta: carrinho recebeu item (${count})`);
  record(active.attribute === 'color', `incompleta: foco nao foi para atributo pendente color (${JSON.stringify(active)})`);

  results.incomplete = { addRequests: addRequests.length, count, active };
};

const verifySoldoutSelection = async (page) => {
  await clearCart(page);
  await page.goto(pageUrl('/'), { waitUntil: 'networkidle', timeout: 30000 });
  const card = findPlan031Card(page);
  await card.waitFor({ state: 'visible', timeout: 20000 });
  await card.locator('[data-petshop-attribute="size"][data-value="soldout"]').first().click();
  await page.waitForTimeout(250);
  const buttonDisabled = await card.locator('[data-petshop-buy-now]').first().evaluate((button) => (
    button.disabled || button.getAttribute('aria-disabled') === 'true' || button.classList.contains('is-disabled')
  ));
  const addResponses = [];
  page.on('response', (response) => {
    if (response.url().includes('/wc/store/v1/cart/add-item')) {
      addResponses.push(response.status());
    }
  });
  if (!buttonDisabled) {
    await card.locator('[data-petshop-buy-now]').first().click();
  }
  await page.waitForTimeout(800);
  const cart = await storeApiCart(page);
  const count = cart?.items_count || cart?.items?.length || 0;

  record(count === 0, `esgotada: carrinho recebeu item (${count})`);
  record(addResponses.length === 0, `esgotada: add-item deveria ser bloqueado antes da Store API (${addResponses.join(',')})`);

  results.soldout = { addResponses, buttonDisabled, count };
};

const verifyPersonalizable = async (page) => {
  await clearCart(page);
  await page.goto(pageUrl('/personalize/'), { waitUntil: 'networkidle', timeout: 30000 });
  const product = page.locator('li.product').filter({ has: page.locator('[data-petshop-personalizable], a[href*="petshop_personalize=1"]') }).first();
  if (await product.count() === 0) {
    results.personalizable = { skipped: 'nenhum produto personalizavel encontrado' };
    failures.push('produto 012: nenhum produto personalizavel encontrado para validar');
    return;
  }

  const addRequests = [];
  page.on('request', (request) => {
    if (request.method() === 'POST' && request.url().includes('/wc/store/v1/cart/add-item')) {
      addRequests.push(request.url());
    }
  });
  await Promise.all([
    page.waitForURL((url) => url.searchParams.get('petshop_personalize') === '1', { timeout: 20000 }),
    product.locator('[data-petshop-personalizable], a[href*="petshop_personalize=1"]').first().click(),
  ]);
  const editor = page.locator('[data-petshop-personalizer]');
  const editorPresent = await editor.count();
  const cart = await storeApiCart(page);
  const count = cart?.items_count || cart?.items?.length || 0;

  record(addRequests.length === 0, `produto 012: add-item foi chamado (${addRequests.length})`);
  record(count === 0, `produto 012: carrinho recebeu item (${count})`);
  record(editorPresent > 0, 'produto 012: personalizador/editor nao apareceu');

  results.personalizable = {
    url: new URL(page.url()).pathname + new URL(page.url()).search,
    addRequests: addRequests.length,
    count,
    editorPresent,
  };
};

try {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  await routeCanonicalNavigation(page, baseUrl);
  await clearCart(page);

  const home = await verifySurface(page, 'home', pageUrl('/'));
  results.initialVariation = {
    id: home.metrics.initialVariationId,
    price: home.metrics.priceText,
    image: home.metrics.imageSrc,
    variation: home.metrics.initialVariation,
  };
  results.wildcard = {
    initialSizeAttribute: home.metrics.initialVariation?.attributes?.size ?? null,
    selectedSizeChip: home.metrics.selected.find((chip) => chip.attribute === 'size')?.value || null,
    browserStayedOnInitialImage: home.metrics.imageSrc.includes('plan-031-initial'),
  };

  await verifySurface(page, 'loja', shopFixtureUrls());

  await clearCart(page);
  await page.goto(pageUrl('/'), { waitUntil: 'networkidle', timeout: 30000 });
  const shopCard = findPlan031Card(page);
  await shopCard.waitFor({ state: 'visible', timeout: 20000 });
  const shopMetrics = await cardMetrics(shopCard);
  await addCurrentCardToCart(page, shopCard, shopMetrics.initialVariationId, 'initial-without-user-change');

  await clearCart(page);
  await page.goto(pageUrl('/'), { waitUntil: 'networkidle', timeout: 30000 });
  const chipCard = findPlan031Card(page);
  await chipCard.waitFor({ state: 'visible', timeout: 20000 });
  await chipCard.locator('[data-petshop-attribute="size"][data-value="medium"]').first().click();
  await page.waitForTimeout(250);
  let mediumMetrics = await cardMetrics(chipCard);
  results.chipChanges.push({ target: 'medium', price: mediumMetrics.priceText, image: mediumMetrics.imageSrc });
  await addCurrentCardToCart(page, chipCard, Number(mediumMetrics.resolvedVariation?.id || 0), 'after-medium-chip');

  await clearCart(page);
  await page.goto(pageUrl('/'), { waitUntil: 'networkidle', timeout: 30000 });
  const multiCard = findPlan031Card(page);
  await multiCard.waitFor({ state: 'visible', timeout: 20000 });
  await multiCard.locator('[data-petshop-attribute="size"][data-value="medium"]').first().click();
  await page.waitForTimeout(150);
  await multiCard.locator('[data-petshop-attribute="size"][data-value="small"]').first().click();
  await page.waitForTimeout(250);
  const smallMetrics = await cardMetrics(multiCard);
  results.chipChanges.push({ target: 'small-after-medium', price: smallMetrics.priceText, image: smallMetrics.imageSrc });
  record(smallMetrics.priceText.includes('20'), `troca medium -> small nao resolveu variacao small (${smallMetrics.priceText})`);
  record(smallMetrics.imageSrc.includes('plan-031-small'), `troca medium -> small nao resolveu imagem small (${smallMetrics.imageSrc})`);

  await clearCart(page);
  await verifySurface(page, 'busca', pageUrl('/?s=plan031busca&post_type=product'));
  await verifySurface(page, 'relacionados', pageUrl('/produto/produto-relacionado-plano-031/'));
  await verifyIncompleteSelection(page);
  await verifySoldoutSelection(page);
  await verifyPersonalizable(page);

  results.promotion = {
    source: 'fixture PLAN031-VAR-INITIAL',
    assertion: 'initial variation has regular 15 and sale 10; displayed price contains both 15 and 10 from WooCommerce price_html',
    priceHtml: home.metrics.priceHtml,
  };
  record(/15/.test(home.metrics.priceHtml) && /10/.test(home.metrics.priceHtml), `promocao: price_html inicial nao contem regular 15 e sale 10 (${home.metrics.priceHtml})`);

  results.miniCart = results.storeApi[0] ? {
    before: results.storeApi[0].miniBefore,
    after: results.storeApi[0].miniAfter,
  } : null;

  await page.close();
  await context.close();
} finally {
  await browser.close();
}

console.log(JSON.stringify({ results, failures, evidenceDir }, null, 2));
if (failures.length) {
  process.exit(1);
}
