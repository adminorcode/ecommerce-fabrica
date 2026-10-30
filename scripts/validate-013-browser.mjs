import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const canonicalHost = process.env.PETSHOP_CANONICAL_HOST || new URL(baseUrl).host;
const evidenceDir = createEvidenceDirectory('013');
const failures = [];
const results = [];
const browser = await launchBrowser();

const recordFailure = (condition, message) => { if (!condition) failures.push(message); };

try {
  const request = await browser.newPage();
  for (const [legacy, localized] of Object.entries({ shop: 'loja', cart: 'carrinho', checkout: 'finalizar-compra', 'my-account': 'minha-conta' })) {
    const response = await request.request.get(`${baseUrl}/${legacy}/?origem=013`, {
      headers: { Host: canonicalHost }, maxRedirects: 0, timeout: 20000,
    });
    const location = new URL(response.headers().location || '/', baseUrl);
    recordFailure(response.status() === 301, `${legacy}: redirecionamento nao retornou 301`);
    recordFailure(location.pathname === `/${localized}/` && location.searchParams.get('origem') === '013', `${legacy}: destino ou query divergente`);
  }
  await request.close();

  for (const viewport of [
    { name: 'mobile-390', width: 390, height: 844 },
    { name: 'tablet-768', width: 768, height: 900 },
    { name: 'desktop-1024', width: 1024, height: 900 },
    { name: 'desktop-1440', width: 1440, height: 1000 },
  ]) {
    const page = await browser.newPage({ viewport });
    await routeCanonicalNavigation(page, baseUrl);
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const response = await page.goto(`${baseUrl}/loja/`, { waitUntil: 'networkidle', timeout: 30000 });
    const sidebar = page.locator('.petshop-catalog-sidebar');
    const grid = page.locator('main ul.products');
    const skipLinks = page.locator('a.skip-link, a.petshop-skip-link');
    const breadcrumbs = page.locator('.ct-breadcrumbs, .woocommerce-breadcrumb');
    const overflow = await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth));
    const entry = {
      viewport: viewport.name,
      status: response?.status(),
      h1: await page.locator('main h1').count(),
      skipLinks: await skipLinks.count(),
      breadcrumbs: await breadcrumbs.count(),
      products: await grid.locator('li.product').count(),
      overflow,
      errors,
    };
    results.push(entry);
    recordFailure(entry.status === 200, `${viewport.name}: /loja/ HTTP ${entry.status}`);
    recordFailure(entry.h1 === 1, `${viewport.name}: quantidade de H1 igual a ${entry.h1}`);
    recordFailure(entry.skipLinks === 1, `${viewport.name}: quantidade de skip links igual a ${entry.skipLinks}`);
    recordFailure(entry.breadcrumbs <= 1, `${viewport.name}: breadcrumb duplicado`);
    recordFailure(entry.products > 0, `${viewport.name}: grade vazia`);
    recordFailure(overflow <= 1, `${viewport.name}: overflow horizontal de ${overflow}px`);
    recordFailure(errors.length === 0, `${viewport.name}: ${errors.length} erro(s) JavaScript`);

    if (viewport.width < 768) {
      const toggle = page.locator('[data-petshop-filter-open]');
      await toggle.click();
      recordFailure(await sidebar.evaluate((element) => element.classList.contains('is-open')), `${viewport.name}: painel mobile nao abriu`);
      await page.waitForTimeout(300);
      const focusState = await sidebar.evaluate((element) => ({
        inside: element.contains(document.activeElement) || element === document.activeElement,
        active: `${document.activeElement?.tagName || ''}#${document.activeElement?.id || ''}.${document.activeElement?.className || ''}`,
        tabIndex: element.tabIndex,
        visibility: getComputedStyle(element).visibility,
      }));
      recordFailure(focusState.inside, `${viewport.name}: foco nao entrou no painel (${JSON.stringify(focusState)})`);
      recordFailure(await sidebar.getAttribute('aria-modal') === 'true', `${viewport.name}: painel aberto sem modalidade anunciada`);
      await sidebar.locator('#petshop-category-search').fill('grava');
      let focusedHiddenCategory = false;
      for (let step = 0; step < 20; step += 1) {
        await page.keyboard.press('Tab');
        focusedHiddenCategory ||= await page.evaluate(() => Boolean(document.activeElement?.closest('[hidden]')));
      }
      recordFailure(!focusedHiddenCategory, `${viewport.name}: foco entrou em categoria ocultada pela busca`);
      await page.keyboard.press('Escape');
      recordFailure(await toggle.evaluate((element) => element === document.activeElement), `${viewport.name}: foco nao retornou ao gatilho`);
    }
    if (viewport.name === 'mobile-390' || viewport.name === 'desktop-1440') {
      await page.screenshot({ path: path.join(evidenceDir, `${viewport.name}-loja.png`), fullPage: true });
    }
    await page.close();
  }

  const page = await browser.newPage({ viewport: { width: 1024, height: 900 } });
  await routeCanonicalNavigation(page, baseUrl);
  await page.goto(`${baseUrl}/loja/?filter_pa_color=azul&filter_pa_size=p&stock_status=instock`, { waitUntil: 'networkidle', timeout: 30000 });
  recordFailure(await page.locator('.petshop-catalog-filter__applied li').count() >= 3, 'catalogo: chips dos filtros combinados ausentes');
  recordFailure(await page.locator('main ul.products li.product').count() >= 1, 'catalogo: filtro combinado sem a fixture esperada');
  recordFailure(await page.locator('.petshop-catalog-filter__clear').count() === 1, 'catalogo: limpar todos ausente');

  await page.goto(`${baseUrl}/loja/`, { waitUntil: 'networkidle', timeout: 30000 });
  const search = page.locator('form.woocommerce-product-search input[type="search"]:visible').first();
  await search.fill('Bandana');
  await page.waitForTimeout(1500);
  const searchState = await page.evaluate(async () => {
    const endpoint = new URL(window.petshopSearchConfig?.endpoint || '/', window.location.origin);
    endpoint.searchParams.set('search', 'Bandana');
    endpoint.searchParams.set('per_page', '5');
    let apiStatus = 0;
    try { apiStatus = (await fetch(endpoint)).status; } catch (_) { apiStatus = -1; }
    return {
      configured: Boolean(window.petshopSearchConfig?.endpoint),
      forms: document.querySelectorAll('form.woocommerce-product-search').length,
      lists: [...document.querySelectorAll('.petshop-search-suggestions')].map((list) => ({ hidden: list.hidden, options: list.querySelectorAll('[role="option"]').length })),
      apiStatus,
    };
  });
  results.push({ behavior: 'search-suggestions', ...searchState });
  recordFailure(searchState.lists.some((list) => !list.hidden && list.options >= 1), `busca: sugestoes da Store API ausentes (${JSON.stringify(searchState)})`);

  const skuResponse = await page.request.get(`${baseUrl}/?s=PLAN013-SIMPLE&post_type=product`, {
    headers: { Host: canonicalHost }, maxRedirects: 0, timeout: 20000,
  });
  recordFailure(skuResponse.status() === 302 && /\/product\/bandana-essencial-amostra-plano-013\/$/.test(new URL(skuResponse.headers().location || '/', baseUrl).pathname), 'busca: SKU exato nao resolveu a PDP');
  await page.goto(`${baseUrl}/?s=consulta-sem-resultado-013&post_type=product`, { waitUntil: 'networkidle', timeout: 30000 });
  recordFailure(await page.locator('.petshop-search-empty').count() === 1, 'busca: estado vazio orientado ausente');

  await page.goto(`${baseUrl}/product/bandana-com-variacoes-amostra-plano-013/`, { waitUntil: 'networkidle', timeout: 30000 });
  recordFailure(await page.locator('form.variations_form').count() === 1, 'PDP: formulario de variacoes ausente');
  recordFailure(await page.locator('.petshop-color-swatch').count() >= 2, 'PDP: amostras de cor com nome ausentes');
  recordFailure(await page.locator('[data-petshop-shipping-form]').count() === 1, 'PDP: calculadora de entrega ausente');
  await page.locator('[data-petshop-shipping-form] input[name="postcode"]').fill('123');
  await page.locator('[data-petshop-shipping-form] button[type="submit"]').click();
  recordFailure((await page.locator('[data-petshop-shipping-result]').innerText()).length > 0, 'PDP: CEP invalido sem mensagem');
  for (const select of await page.locator('form.variations_form select').all()) {
    const value = await select.locator('option:not([value=""])').first().getAttribute('value');
    if (value) await select.selectOption(value);
  }
  await page.locator('form.variations_form input[name="variation_id"]').waitFor({ state: 'attached' });
  await page.waitForFunction(() => Number(document.querySelector('form.variations_form input[name="variation_id"]')?.value || 0) > 0);
  recordFailure((await page.locator('[data-petshop-production-lead]').innerText()).trim().length > 0, 'PDP: prazo da variacao ausente');
  await page.locator('[data-petshop-shipping-form] input[name="postcode"]').fill('01001-000');
  await page.locator('[data-petshop-shipping-form] button[type="submit"]').click();
  await page.waitForFunction(() => /Entrega local de teste|Correios/i.test(document.querySelector('[data-petshop-shipping-result]')?.textContent || ''), null, { timeout: 15000 });
  recordFailure(/Producao|Produção/i.test(await page.locator('[data-petshop-shipping-result]').innerText()), 'PDP: calculo valido nao separou prazo de producao');
  const customerAfterShipping = await page.evaluate(async () => (await (await fetch('/wp-json/wc/store/v1/cart')).json()).shipping_address?.postcode || '');
  recordFailure(customerAfterShipping === '01001000', 'PDP: simulacao de frete nao persistiu o CEP do cliente');
  await page.screenshot({ path: path.join(evidenceDir, 'pdp-variable.png'), fullPage: true });

  const fixtureResponse = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/products?sku=PLAN013-SIMPLE`, { headers: { Host: canonicalHost } });
  const fixtureProducts = fixtureResponse.ok() ? await fixtureResponse.json() : [];
  const initialCartResponse = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`, { headers: { Host: canonicalHost } });
  const nonce = initialCartResponse.headers().nonce || '';
  const cartResponse = await page.request.post(`${baseUrl}/wp-json/wc/store/v1/cart/add-item`, {
    headers: { Host: canonicalHost, Nonce: nonce }, data: { id: Number(fixtureProducts[0]?.id || 0), quantity: 1 },
  });
  recordFailure(cartResponse.ok(), `carrinho: fixture nao adicionada pela Store API (HTTP ${cartResponse.status()})`);
  await page.goto(`${baseUrl}/carrinho/`, { waitUntil: 'networkidle', timeout: 30000 });
  recordFailure(await page.locator('.petshop-cart-continue').count() === 1, 'carrinho: continuar comprando ausente');
  await page.goto(`${baseUrl}/finalizar-compra/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  recordFailure(await page.locator('.petshop-checkout-return').count() === 1, 'checkout: retorno ao carrinho ausente');
  recordFailure(await page.locator('body.petshop-distraction-free-checkout').count() === 1, 'checkout: modo sem distracoes ausente');
  await page.goto(`${baseUrl}/minha-conta/`, { waitUntil: 'networkidle', timeout: 30000 });
  recordFailure(await page.locator('form.woocommerce-form-register').count() === 1, 'minha conta: cadastro ausente');
  await page.close();
} finally {
  await browser.close();
}

console.log(JSON.stringify({ results, failures }, null, 2));
if (failures.length) process.exit(1);
