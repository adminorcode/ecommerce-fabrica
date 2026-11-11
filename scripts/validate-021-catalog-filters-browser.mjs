import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('021');
const browser = await launchBrowser();
const failures = [];
const results = [];

const routes = [
  '/loja/',
  '/product-category/bandanas/',
  '/product-category/conjuntos/',
  '/product-category/bandanas/?filter_pa_color=azul',
  '/loja/?product_cat%5B%5D=adesivos&product_cat%5B%5D=bandanas&min_price=&max_price=&stock_status=',
  '/loja/?product_cat%5B%5D=bandanas&filter_pa_color=azul&filter_pa_size=p&stock_status=instock',
];

const viewports = [
  { name: 'desktop-1440', width: 1440, height: 1000 },
  { name: 'desktop-1024', width: 1024, height: 900 },
  { name: 'tablet-768', width: 768, height: 900 },
  { name: 'mobile-390', width: 390, height: 844 },
];

const visible = async (locator) => locator.count().then((count) => count > 0 && locator.first().isVisible());
const box = async (locator) => {
  if (await locator.count() === 0) return null;
  return locator.first().boundingBox({ timeout: 1000 }).catch(() => null);
};
const categoryValues = (url) => [...url.searchParams.entries()]
  .filter(([key]) => /^(?:product_cat|petshop_categories)(?:\[\d*\])?$/.test(key))
  .map(([, value]) => value);

try {
  for (const viewport of viewports) {
    for (const route of routes) {
      const page = await browser.newPage({ viewport });
      await routeCanonicalNavigation(page, baseUrl);
      const pageErrors = [];
      page.on('pageerror', (error) => pageErrors.push(error.message));
      const response = await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle', timeout: 25000 });
      const finalUrl = new URL(page.url());
      const sidebar = page.locator('.petshop-catalog-sidebar');
      const form = page.locator('.petshop-catalog-filter');
      const toggle = page.locator('[data-petshop-filter-open]');
      const closeButton = page.locator('[data-petshop-filter-close]');
      const footer = page.locator('.petshop-catalog-filter__actions');
      const products = page.locator('main ul.products li.product');
      const toolbarChips = page.locator('.petshop-catalog-toolbar .petshop-catalog-filter__applied a');
      const facetButtons = page.locator('.petshop-catalog-filter__facet-toggle');
      const record = {
        viewport: viewport.name,
        route,
        status: response?.status(),
        form: await box(form),
        footer: await box(footer),
        toggleVisible: await visible(toggle),
        visibleThemeHeroes: await page.locator('.hero-section:visible').count(),
        overflow: await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth)),
        products: await products.count(),
        orderings: await page.locator('.petshop-catalog-toolbar .woocommerce-ordering').count(),
        resultCounts: await page.locator('.petshop-catalog-toolbar .woocommerce-result-count').count(),
        toolbarChips: await toolbarChips.count(),
        facetButtons: await facetButtons.count(),
        expandedButtonsWithoutPanel: await facetButtons.evaluateAll((buttons) => buttons.filter((button) => {
          const panel = document.getElementById(button.getAttribute('aria-controls') || '');
          return !button.hasAttribute('aria-expanded') || !panel;
        }).length),
        activeClosed: await page.locator('.petshop-catalog-filter__facet.is-active .petshop-catalog-filter__facet-toggle[aria-expanded="false"]').count(),
        pageErrors,
      };
      results.push(record);

      if (record.status !== 200) failures.push(`${viewport.name} ${route}: HTTP ${record.status}`);
      if (route.startsWith('/product-category/bandanas/?')) {
        const categories = categoryValues(finalUrl);
        if (finalUrl.pathname !== '/loja/' || !categories.includes('bandanas') || finalUrl.searchParams.get('filter_pa_color') !== 'azul') {
          failures.push(`${viewport.name} ${route}: canonicalizacao perdeu categoria atual ou filtro (${finalUrl.toString()})`);
        }
      }
      if (!record.form) failures.push(`${viewport.name} ${route}: formulario de filtros ausente`);
      if (record.visibleThemeHeroes !== 0) failures.push(`${viewport.name} ${route}: hero-section padrao visivel`);
      if (record.overflow > 1) failures.push(`${viewport.name} ${route}: overflow horizontal de ${record.overflow}px`);
      if (record.products < 1) failures.push(`${viewport.name} ${route}: produtos ausentes`);
      if (record.orderings !== 1 || record.resultCounts !== 1) failures.push(`${viewport.name} ${route}: ordenacao ou contador ausente`);
      if (record.facetButtons < 4 || record.expandedButtonsWithoutPanel > 0) failures.push(`${viewport.name} ${route}: accordions sem aria/painel valido`);
      if (record.activeClosed > 0) failures.push(`${viewport.name} ${route}: grupo ativo iniciou fechado`);
      if (pageErrors.length) failures.push(`${viewport.name} ${route}: ${pageErrors.length} erro(s) de pagina`);

      if (viewport.width >= 1180) {
        if (record.form && record.form.height > viewport.height) failures.push(`${viewport.name} ${route}: painel maior que viewport`);
        if (record.footer && (record.footer.y + record.footer.height) > viewport.height) failures.push(`${viewport.name} ${route}: footer de filtros fora da viewport`);
      } else {
        if (!record.toggleVisible) failures.push(`${viewport.name} ${route}: botao de filtro invisivel`);
        const beforeScrollY = await page.evaluate(() => window.scrollY);
        await toggle.click();
        await page.waitForTimeout(220);
        const openState = await sidebar.evaluate((element) => element.classList.contains('is-open'));
        const bodyLocked = await page.evaluate(() => getComputedStyle(document.documentElement).overflow === 'hidden' || getComputedStyle(document.body).overflow === 'hidden');
        const openFooter = await footer.boundingBox();
        if (!openState) failures.push(`${viewport.name} ${route}: drawer nao abriu`);
        if (!bodyLocked) failures.push(`${viewport.name} ${route}: body nao travou com drawer aberto`);
        if (!openFooter || openFooter.y < 0 || openFooter.y + openFooter.height > viewport.height + 1) failures.push(`${viewport.name} ${route}: footer do drawer fora da viewport`);
        await page.keyboard.press('Escape');
        await page.waitForTimeout(120);
        const closedState = await sidebar.evaluate((element) => !element.classList.contains('is-open'));
        const focusReturned = await toggle.evaluate((element) => document.activeElement === element);
        const afterScrollY = await page.evaluate(() => window.scrollY);
        if (!closedState) failures.push(`${viewport.name} ${route}: Escape nao fechou drawer`);
        if (!focusReturned) failures.push(`${viewport.name} ${route}: foco nao retornou ao botao`);
        if (afterScrollY !== beforeScrollY) failures.push(`${viewport.name} ${route}: pagina rolou ao abrir/fechar drawer`);
        await toggle.click();
        await page.waitForTimeout(120);
        await closeButton.click();
        await page.waitForTimeout(120);
        const closedByButton = await sidebar.evaluate((element) => !element.classList.contains('is-open'));
        if (!closedByButton) failures.push(`${viewport.name} ${route}: botao fechar nao fechou drawer`);
      }

      if (route.includes('filter_pa_color')) {
        const expectedChips = route.includes('filter_pa_size') ? 3 : 2;
        if (record.toolbarChips < expectedChips) failures.push(`${viewport.name} ${route}: chips aplicados ausentes na toolbar`);
        const colorChip = page.locator('.petshop-catalog-toolbar .petshop-catalog-filter__applied a', { hasText: 'Cor:' }).first();
        const href = await colorChip.getAttribute('href');
        if (!href) {
          failures.push(`${viewport.name} ${route}: chip de cor sem URL de remocao`);
        } else {
          const removal = new URL(href, baseUrl);
          if (removal.searchParams.has('filter_pa_color')) failures.push(`${viewport.name} ${route}: chip de cor preserva filtro removido`);
          const categories = categoryValues(removal);
          if (route.includes('product_cat') && !categories.includes('bandanas')) failures.push(`${viewport.name} ${route}: chip de cor removeu categoria relacionada`);
        }
      }

      if (route === '/product-category/bandanas/' && viewport.name === 'desktop-1440') {
        const search = page.locator('#petshop-category-search');
        const moreButton = page.locator('[data-petshop-filter-more]');
        await search.fill('grav');
        await search.fill('');
        if (await moreButton.count() > 0 && !await moreButton.isVisible()) {
          failures.push(`${viewport.name} ${route}: Ver mais nao voltou apos limpar busca`);
        }
      }

      if (['desktop-1440', 'desktop-1024', 'mobile-390'].includes(viewport.name) && route === '/product-category/bandanas/') {
        await page.screenshot({ path: path.join(evidenceDir, `${viewport.name}.png`), fullPage: true });
      }
      await page.close();
    }
  }
} finally {
  await browser.close();
}

console.log(JSON.stringify({ results, failures }, null, 2));
if (failures.length) process.exit(1);
