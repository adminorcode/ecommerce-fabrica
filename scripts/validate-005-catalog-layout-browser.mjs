import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('005/catalog-layout');

const failures = [];
const results = [];
const browser = await launchBrowser();
const categoryValues = (url) => [...url.searchParams.entries()]
  .filter(([key]) => /^(?:product_cat|petshop_categories)(?:\[\d*\])?$/.test(key))
  .map(([, value]) => value);

try {
  for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'desktop-1024', width: 1024, height: 900 },
    { name: 'mobile-390', width: 390, height: 844 },
  ]) {
    const page = await browser.newPage({ viewport });
    await routeCanonicalNavigation(page, baseUrl);
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    const response = await page.goto(`${baseUrl}/product-category/conjuntos/`, { waitUntil: 'networkidle', timeout: 20000 });
    const sidebar = page.locator('.petshop-catalog-sidebar');
    const main = page.locator('main ul.products');
    const checkboxes = sidebar.locator('input[type="checkbox"]');
    const labelProblems = await sidebar.locator('li label').evaluateAll((labels) => labels.filter((label) => {
      const input = label.querySelector('input');
      return !input?.id || label.htmlFor !== input.id || !label.textContent.trim();
    }).length);
    const record = {
      viewport: viewport.name,
      status: response?.status(),
      sidebar: await sidebar.boundingBox(),
      main: await main.boundingBox(),
      searchInputs: await sidebar.locator('input[type="search"]').count(),
      checkboxes: await checkboxes.count(),
      checked: await checkboxes.evaluateAll((inputs) => inputs.filter((input) => input.checked).length),
      resultCounts: await page.locator('.petshop-catalog-toolbar .woocommerce-result-count').count(),
      orderings: await page.locator('.petshop-catalog-toolbar .woocommerce-ordering').count(),
      products: await main.locator('li.product').count(),
      labelProblems,
      visibleThemeHeroes: await page.locator('.hero-section:visible').count(),
      overflow: await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth)),
      pageErrors,
    };
    results.push(record);

    if (record.status !== 200) failures.push(`${viewport.name}: HTTP ${record.status}`);
    if (!record.sidebar || !record.main) failures.push(`${viewport.name}: sidebar ou area principal ausente`);
    if (record.searchInputs !== 1) failures.push(`${viewport.name}: campo textual de categorias ausente ou duplicado`);
    if (record.checkboxes < 2 || record.checked !== 1) failures.push(`${viewport.name}: checkboxes de categoria ou estado atual divergente`);
    if (record.resultCounts !== 1 || record.orderings !== 1) failures.push(`${viewport.name}: toolbar de catalogo divergente`);
    if (record.products < 1) failures.push(`${viewport.name}: grade de produtos ausente`);
    if (record.labelProblems) failures.push(`${viewport.name}: ${record.labelProblems} checkbox(es) sem rotulo valido`);
    if (record.visibleThemeHeroes > 0) failures.push(`${viewport.name}: hero-section padrao do tema visivel no catalogo`);
    if (record.overflow > 1) failures.push(`${viewport.name}: overflow horizontal de ${record.overflow}px`);
    if (pageErrors.length) failures.push(`${viewport.name}: ${pageErrors.length} erro(s) de pagina`);

    if (record.sidebar && record.main) {
      if (viewport.width >= 1180 && record.sidebar.x >= record.main.x) failures.push(`${viewport.name}: sidebar nao esta a esquerda da grade`);
      if (viewport.width < 1180) {
        const toggle = page.locator('[data-petshop-filter-open]');
        await toggle.click();
        if (!await sidebar.evaluate((element) => element.classList.contains('is-open'))) failures.push(`${viewport.name}: painel de filtros nao abriu`);
        await page.keyboard.press('Escape');
      }
    }

    if (viewport.name === 'desktop-1440' || viewport.name === 'mobile-390') {
      await page.screenshot({ path: path.join(evidenceDir, `${viewport.name}.png`), fullPage: true });
    }
    await page.close();
  }

  const behaviorPage = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  await routeCanonicalNavigation(behaviorPage, baseUrl);
  await behaviorPage.goto(`${baseUrl}/product-category/conjuntos/`, { waitUntil: 'networkidle', timeout: 20000 });
  await behaviorPage.locator('.petshop-catalog-filter').evaluate((form, target) => {
    form.action = new URL('/loja/', target).href;
  }, baseUrl);
  const categorySearch = behaviorPage.locator('#petshop-category-search');
  await categorySearch.fill('gráva');
  const visibleOptions = await behaviorPage.locator('#petshop-category-options > li:visible .petshop-catalog-filter__name').allTextContents();
  if (visibleOptions.length !== 1 || visibleOptions[0].trim() !== 'Gravatas') failures.push(`busca textual: opcoes visiveis divergentes (${visibleOptions.join(', ')})`);
  await categorySearch.fill('');

  const gravatasInput = behaviorPage.locator('input[type="checkbox"][value="gravatas"]');
  if (!await gravatasInput.isVisible()) {
    await behaviorPage.locator('[data-petshop-filter-more]').click();
  }
  await gravatasInput.check();
  if (new URL(behaviorPage.url()).pathname !== '/product-category/conjuntos/') failures.push('checkbox isolado navegou antes da aplicacao explicita');
  await behaviorPage.locator('.petshop-catalog-filter').evaluate((form) => {
    form.querySelectorAll('input,select').forEach((control) => {
      if (!control.value) control.disabled = true;
    });
  });
  await Promise.all([
    behaviorPage.waitForURL((url) => url.pathname === '/loja/' && categoryValues(url).length > 0, { timeout: 15000 }),
    behaviorPage.locator('.petshop-catalog-filter__apply').click(),
  ]);
  const combinedUrl = new URL(behaviorPage.url());
  const combinedSlugs = categoryValues(combinedUrl);
  if (JSON.stringify(combinedSlugs) !== JSON.stringify(['conjuntos', 'gravatas'])) failures.push(`query combinada divergente: ${combinedSlugs.join(',')}`);
  if (await behaviorPage.locator('.petshop-catalog-filter input[type="checkbox"]:checked').count() !== 2) failures.push('query combinada: dois checkboxes nao permaneceram marcados');
  const combinedCategories = await behaviorPage.locator('li.product .meta-categories').allTextContents();
  if (combinedCategories.length < 2 || combinedCategories.some((text) => !/(Conjuntos|Gravatas)/i.test(text))) failures.push('query combinada: produto fora das categorias selecionadas');

  await behaviorPage.locator('input[type="checkbox"][value="conjuntos"]').uncheck();
  await behaviorPage.locator('.petshop-catalog-filter').evaluate((form) => {
    form.querySelectorAll('input,select').forEach((control) => {
      if (!control.value) control.disabled = true;
    });
  });
  await Promise.all([
    behaviorPage.waitForURL((url) => {
      const categories = categoryValues(url);
      return url.pathname === '/loja/' && categories.length === 1 && categories[0] === 'gravatas';
    }, { timeout: 15000 }),
    behaviorPage.locator('.petshop-catalog-filter__apply').click(),
  ]);
  if (await behaviorPage.locator('.petshop-catalog-filter input[type="checkbox"]:checked').count() !== 1) failures.push('query simples: checkbox de Gravatas nao permaneceu marcado');
  const gravataCategories = await behaviorPage.locator('li.product .meta-categories').allTextContents();
  if (!gravataCategories.length || gravataCategories.some((text) => !/Gravatas/i.test(text))) failures.push('query simples: resultado fora de Gravatas');
  const gravataUrl = behaviorPage.url();
  const canonicalResponse = await behaviorPage.request.get(`${baseUrl}/product-category/gravatas/?product_cat%5B%5D=conjuntos`, {
    headers: { Host: new URL(baseUrl).host },
    maxRedirects: 0,
    timeout: 20000,
  });
  const canonicalLocation = canonicalResponse.headers().location || '';
  const canonicalUrl = new URL(canonicalLocation, baseUrl);
  const canonicalCategories = categoryValues(canonicalUrl);
  if (canonicalResponse.status() !== 302 || canonicalUrl.pathname !== '/loja/' || !canonicalCategories.includes('conjuntos')) failures.push('query em arquivo de taxonomia nao foi canonicalizada para a loja');
  results.push({
    behavior: 'catalog-filter',
    textSearch: visibleOptions.map((value) => value.trim()),
    combinedUrl: combinedUrl.toString(),
    combinedProducts: combinedCategories.length,
    finalUrl: gravataUrl,
    finalProducts: gravataCategories.length,
    canonicalUrl: canonicalUrl.toString(),
  });
  await behaviorPage.close();
} finally {
  await browser.close();
}

console.log(JSON.stringify({ results, failures }, null, 2));
if (failures.length) process.exit(1);
