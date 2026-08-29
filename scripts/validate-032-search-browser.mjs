import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const canonicalHost = process.env.PETSHOP_CANONICAL_HOST || new URL(baseUrl).host;
const evidenceDir = createEvidenceDirectory('032');
const browser = await launchBrowser();
const failures = [];
const results = [];

const viewports = [
  { name: 'desktop-1440', width: 1440, height: 1000 },
  { name: 'mobile-390', width: 390, height: 844 },
];

const recordFailure = (condition, message) => {
  if (!condition) failures.push(message);
};

const visibleSearch = (page) => page.locator('.petshop-commercial-header__search form.woocommerce-product-search:visible').first();
const categoryValues = (url) => [...url.searchParams.entries()]
  .filter(([key]) => /^(?:product_cat|petshop_categories)(?:\[\d*\])?$/.test(key))
  .map(([, value]) => value);

const assertProductResults = async (page, action, viewportName) => {
  const finalUrl = new URL(page.url());
  const products = await page.locator('main ul.products li.product').count();
  const title = await page.locator('main h1').first().innerText().catch(() => '');

  results.push({
    viewport: viewportName,
    action,
    url: finalUrl.pathname + finalUrl.search,
    products,
    title,
  });

  recordFailure(finalUrl.searchParams.get('s') === 'Bandana', `${viewportName} ${action}: URL nao preservou s=Bandana (${finalUrl.toString()})`);
  recordFailure(finalUrl.searchParams.get('post_type') === 'product', `${viewportName} ${action}: URL nao preservou post_type=product`);
  recordFailure(!finalUrl.searchParams.has('search'), `${viewportName} ${action}: URL usou search em vez de s`);
  recordFailure(products > 0, `${viewportName} ${action}: grade de produtos vazia`);
  recordFailure(/Bandana|Resultados/i.test(title), `${viewportName} ${action}: titulo nao indica resultado de busca (${title})`);
};

try {
  for (const viewport of viewports) {
    const page = await browser.newPage({ viewport });
    await routeCanonicalNavigation(page, baseUrl);
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle', timeout: 30000 });
    const form = visibleSearch(page);
    const input = form.locator('input[type="search"]').first();
    const submit = form.locator('button[type="submit"]').first();
    recordFailure(await form.count() === 1, `${viewport.name}: formulario visivel ausente no header`);
    recordFailure(await input.getAttribute('name') === 's', `${viewport.name}: input nao usa name=s`);
    recordFailure(await form.locator('input[type="hidden"][name="post_type"][value="product"]').count() === 1, `${viewport.name}: hidden post_type=product ausente`);
    recordFailure(await submit.count() === 1, `${viewport.name}: botao submit da lupa ausente`);

    await input.fill('Bandana');
    await Promise.all([
      page.waitForURL((url) => url.searchParams.get('s') === 'Bandana' && url.searchParams.get('post_type') === 'product', { timeout: 30000 }),
      submit.click(),
    ]);
    await page.waitForLoadState('networkidle');
    await assertProductResults(page, 'lupa', viewport.name);
    await page.screenshot({ path: path.join(evidenceDir, `${viewport.name}-lupa.png`), fullPage: true });

    await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle', timeout: 30000 });
    const enterForm = visibleSearch(page);
    const enterInput = enterForm.locator('input[type="search"]').first();
    await enterInput.fill('Bandana');
    await Promise.all([
      page.waitForURL((url) => url.searchParams.get('s') === 'Bandana' && url.searchParams.get('post_type') === 'product', { timeout: 30000 }),
      enterInput.press('Enter'),
    ]);
    await page.waitForLoadState('networkidle');
    await assertProductResults(page, 'enter-sem-sugestao', viewport.name);

    await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle', timeout: 30000 });
    const suggestionForm = visibleSearch(page);
    const suggestionInput = suggestionForm.locator('input[type="search"]').first();
    await suggestionInput.fill('Bandana');
    await page.locator('.petshop-search-suggestions [role="option"]').first().waitFor({ state: 'visible', timeout: 15000 });
    const suggestionHref = await page.locator('.petshop-search-suggestions [role="option"]').first().getAttribute('href');
    await page.locator('.petshop-search-suggestions [role="option"]').first().evaluate((link, internalBaseUrl) => {
      const target = new URL(link.href);
      link.setAttribute('href', `${internalBaseUrl}${target.pathname}${target.search}`);
    }, baseUrl);
    await page.locator('.petshop-search-suggestions [role="option"]').first().click();
    await page.waitForLoadState('networkidle');
    recordFailure(new URL(page.url()).pathname === new URL(suggestionHref || '/', baseUrl).pathname, `${viewport.name}: clique na sugestao nao abriu PDP`);

    await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle', timeout: 30000 });
    const keyboardForm = visibleSearch(page);
    const keyboardInput = keyboardForm.locator('input[type="search"]').first();
    await keyboardInput.fill('Bandana');
    await page.locator('.petshop-search-suggestions [role="option"]').first().waitFor({ state: 'visible', timeout: 15000 });
    await keyboardInput.press('ArrowDown');
    const activeHref = await page.locator('.petshop-search-suggestions [aria-selected="true"]').first().getAttribute('href');
    await page.locator('.petshop-search-suggestions [aria-selected="true"]').first().evaluate((link, internalBaseUrl) => {
      const target = new URL(link.href);
      link.setAttribute('href', `${internalBaseUrl}${target.pathname}${target.search}`);
    }, baseUrl);
    await Promise.all([
      page.waitForURL((url) => url.pathname === new URL(activeHref || '/', baseUrl).pathname, { timeout: 30000 }),
      keyboardInput.press('Enter'),
    ]);
    recordFailure(new URL(page.url()).pathname === new URL(activeHref || '/', baseUrl).pathname, `${viewport.name}: Enter com sugestao ativa nao abriu PDP`);

    await page.goto(`${baseUrl}/product-category/bandanas/?s=Bandana&post_type=product&filter_pa_color=azul`, { waitUntil: 'networkidle', timeout: 30000 });
    const canonicalUrl = new URL(page.url());
    const canonicalProducts = await page.locator('main ul.products li.product').count();
    const filterForm = page.locator('.petshop-catalog-filter').first();
    const filterFormCount = await filterForm.count();
    const filterToggle = page.locator('[data-petshop-filter-open]').first();
    if (viewport.width < 1180 && await filterToggle.count() > 0) {
      await page.locator('[data-petshop-filter-open]').click();
    }
    results.push({
      viewport: viewport.name,
      action: 'canonical-com-filtro',
      url: canonicalUrl.pathname + canonicalUrl.search,
      products: canonicalProducts,
      filterForm: filterFormCount,
      filterHiddenSearch: filterFormCount > 0 ? await filterForm.locator('input[type="hidden"][name="s"][value="Bandana"]').count() : 0,
      filterHiddenPostType: filterFormCount > 0 ? await filterForm.locator('input[type="hidden"][name="post_type"][value="product"]').count() : 0,
      colorChecked: filterFormCount > 0 ? await filterForm.locator('input[name="filter_pa_color"][value="azul"]:checked').count() : 0,
    });
    recordFailure(canonicalUrl.pathname === '/loja/', `${viewport.name}: canonical de taxonomia nao foi para /loja/ (${canonicalUrl.toString()})`);
    recordFailure(canonicalUrl.searchParams.get('s') === 'Bandana', `${viewport.name}: canonical perdeu s=Bandana`);
    recordFailure(canonicalUrl.searchParams.get('post_type') === 'product', `${viewport.name}: canonical perdeu post_type=product`);
    recordFailure(canonicalUrl.searchParams.get('filter_pa_color') === 'azul', `${viewport.name}: canonical perdeu filtro de cor`);
    recordFailure(categoryValues(canonicalUrl).includes('bandanas'), `${viewport.name}: canonical perdeu categoria bandanas`);
    recordFailure(canonicalProducts > 0, `${viewport.name}: busca canonicalizada com filtro nao listou produtos`);
    if (filterFormCount > 0) {
      recordFailure(await filterForm.locator('input[type="hidden"][name="s"][value="Bandana"]').count() === 1, `${viewport.name}: form de filtros nao preservou hidden s`);
      recordFailure(await filterForm.locator('input[type="hidden"][name="post_type"][value="product"]').count() === 1, `${viewport.name}: form de filtros nao preservou hidden post_type`);
    }

    await page.goto(`${baseUrl}/?s=consulta-sem-resultado-032&post_type=product`, { waitUntil: 'networkidle', timeout: 30000 });
    recordFailure(await page.locator('.petshop-search-empty').count() === 1, `${viewport.name}: estado vazio do Plano 013 ausente`);

    const skuResponse = await page.request.get(`${baseUrl}/?s=PLAN013-SIMPLE&post_type=product`, {
      headers: { Host: canonicalHost },
      maxRedirects: 0,
      timeout: 20000,
    });
    const skuLocation = new URL(skuResponse.headers().location || '/', baseUrl);
    recordFailure(skuResponse.status() === 302 && /\/product\/bandana-essencial-amostra-plano-013\/$/.test(skuLocation.pathname), `${viewport.name}: SKU exato nao redirecionou para PDP`);
    recordFailure(pageErrors.length === 0, `${viewport.name}: ${pageErrors.length} erro(s) JavaScript`);
    await page.close();
  }
} finally {
  await browser.close();
}

console.log(JSON.stringify({ results, failures }, null, 2));
if (failures.length) process.exit(1);
