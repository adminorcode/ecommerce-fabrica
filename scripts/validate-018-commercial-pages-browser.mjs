import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('018');
const failures = [];
const browser = await launchBrowser();

const pages = [
  { slug: 'animal-republik', label: 'Animal Republik', productAnchor: 'animal-republik-produtos', categorySlug: 'animal-republik' },
  { slug: 'premium', label: 'Produtos premium', productAnchor: 'premium-produtos', categorySlug: 'premium' },
];

const viewports = [
  { name: 'mobile-390', width: 390, height: 844 },
  { name: 'tablet-768', width: 768, height: 900 },
  { name: 'desktop-1440', width: 1440, height: 1000 },
];

const visibleCount = async (locator) => {
  const count = await locator.count();
  let visible = 0;
  for (let index = 0; index < count; index += 1) {
    if (await locator.nth(index).isVisible()) {
      visible += 1;
    }
  }
  return visible;
};

try {
  for (const viewport of viewports) {
    for (const commercialPage of pages) {
      const page = await browser.newPage({ viewport });
      await routeCanonicalNavigation(page, baseUrl);
      const consoleErrors = [];
      const requestErrors = [];
      page.on('console', (message) => {
        if (message.type() === 'error') {
          consoleErrors.push(message.text());
        }
      });
      page.on('pageerror', (error) => consoleErrors.push(error.message));
      page.on('requestfailed', (request) => {
        const url = request.url();
        const sameOrigin = url.startsWith(baseUrl) || url.startsWith('http://wordpress');
        if (sameOrigin) {
          requestErrors.push(`${url}: ${request.failure()?.errorText || 'failed'}`);
        }
      });

      const response = await page.goto(`${baseUrl}/${commercialPage.slug}/`, {
        waitUntil: 'networkidle',
        timeout: 30000,
      });

      await page.evaluate(async () => {
        await Promise.race([
          Promise.all([...document.images].map((image) => (image.complete ? Promise.resolve() : new Promise((resolve) => {
            image.addEventListener('load', resolve, { once: true });
            image.addEventListener('error', resolve, { once: true });
          })))),
          new Promise((resolve) => setTimeout(resolve, 3000)),
        ]);
      });

      const metrics = await page.evaluate((expectedAnchor) => {
        const hero = document.querySelector('.petshop-commercial-hero');
        const heroImage = hero?.querySelector('.wp-block-cover__image-background');
        const breadcrumbs = document.querySelector('.petshop-breadcrumbs');
        const products = [...document.querySelectorAll('.petshop-commercial-products li.product')];
        const productWidths = products.map((product) => product.getBoundingClientRect().width);
        const cta = document.querySelector(`a[href="#${expectedAnchor}"]`);
        const viewAll = document.querySelector('.petshop-commercial-products .petshop-section-head__link');
        const anchor = document.getElementById(expectedAnchor);
        const navLinks = [...document.querySelectorAll('.petshop-commercial-menu a')].map((link) => link.textContent?.trim() || '');
        const heroRect = hero?.getBoundingClientRect();
        const breadcrumbsRect = breadcrumbs?.getBoundingClientRect();

        return {
          overflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
          h1Count: document.querySelectorAll('main h1, .site-main h1, h1.wp-block-heading').length,
          heroVisible: !!(hero && hero.getBoundingClientRect().height > 240),
          heroBreadcrumbGap: heroRect && breadcrumbsRect ? Math.round(heroRect.top - breadcrumbsRect.bottom) : null,
          heroImageLoaded: !!(heroImage && heroImage.complete && heroImage.naturalWidth > 0),
          productCount: products.length,
          minProductWidth: productWidths.length ? Math.min(...productWidths) : 0,
          hasCta: !!cta,
          viewAllText: viewAll?.textContent?.trim() || '',
          viewAllHref: viewAll?.href || '',
          hasAnchor: !!anchor,
          navLinks,
        };
      }, commercialPage.productAnchor);

      await page.screenshot({
        path: path.join(evidenceDir, `${commercialPage.slug}-${viewport.name}.png`),
        fullPage: true,
      });

      if (response?.status() !== 200) {
        failures.push(`${commercialPage.slug} ${viewport.name}: HTTP ${response?.status()}`);
      }
      if (metrics.h1Count !== 1) {
        failures.push(`${commercialPage.slug} ${viewport.name}: quantidade de H1 = ${metrics.h1Count}`);
      }
      if (!metrics.heroVisible) {
        failures.push(`${commercialPage.slug} ${viewport.name}: hero comercial invisivel ou baixo demais`);
      }
      if (metrics.heroBreadcrumbGap === null || metrics.heroBreadcrumbGap > 1) {
        failures.push(`${commercialPage.slug} ${viewport.name}: espaco entre breadcrumb e hero = ${metrics.heroBreadcrumbGap}px`);
      }
      if (!metrics.heroImageLoaded) {
        failures.push(`${commercialPage.slug} ${viewport.name}: imagem do hero nao carregou`);
      }
      if (!metrics.hasCta || !metrics.hasAnchor) {
        failures.push(`${commercialPage.slug} ${viewport.name}: CTA ou ancora da vitrine ausente`);
      }
      if (metrics.productCount <= 0) {
        failures.push(`${commercialPage.slug} ${viewport.name}: vitrine sem produtos`);
      }
      if (metrics.productCount > 20) {
        failures.push(`${commercialPage.slug} ${viewport.name}: vitrine exibe ${metrics.productCount} produtos, esperado no maximo 20`);
      }
      if (metrics.productCount > 0 && metrics.minProductWidth < 120) {
        failures.push(`${commercialPage.slug} ${viewport.name}: card de produto menor que 120px`);
      }
      if (metrics.viewAllText !== 'Ver tudo') {
        failures.push(`${commercialPage.slug} ${viewport.name}: link Ver tudo ausente ou com texto inesperado`);
      }
      if (!metrics.viewAllHref.includes('/loja/') || !metrics.viewAllHref.includes('product_cat') || !metrics.viewAllHref.includes(commercialPage.categorySlug)) {
        failures.push(`${commercialPage.slug} ${viewport.name}: Ver tudo nao aponta para loja filtrada por ${commercialPage.categorySlug}: ${metrics.viewAllHref}`);
      }
      if (metrics.overflow > 1) {
        failures.push(`${commercialPage.slug} ${viewport.name}: overflow horizontal ${metrics.overflow}px`);
      }
      if (viewport.width >= 768 && !metrics.navLinks.some((label) => label.includes(commercialPage.label === 'Produtos premium' ? 'Premium' : commercialPage.label))) {
        failures.push(`${commercialPage.slug} ${viewport.name}: link da pagina ausente na navbar visivel`);
      }
      if (viewport.width >= 1440 && metrics.viewAllHref) {
        await page.locator('.petshop-commercial-products .petshop-section-head__link').click();
        await page.waitForLoadState('networkidle');
        const destination = page.url();
        if (!destination.includes('/loja/') || !destination.includes('product_cat') || !destination.includes(commercialPage.categorySlug)) {
          failures.push(`${commercialPage.slug} ${viewport.name}: clique em Ver tudo abriu destino inesperado: ${destination}`);
        }
      }

      const blockingConsoleErrors = consoleErrors.filter((message) => !message.includes('Failed to load resource'));
      if (blockingConsoleErrors.length > 0) {
        failures.push(`${commercialPage.slug} ${viewport.name}: console/page errors: ${blockingConsoleErrors.join(' | ')}`);
      }
      if (requestErrors.length > 0) {
        failures.push(`${commercialPage.slug} ${viewport.name}: request errors: ${requestErrors.join(' | ')}`);
      }

      if (viewport.width < 768) {
        const toggle = page.locator('.petshop-commercial-header__menu-toggle');
        if (await visibleCount(toggle) > 0) {
          await toggle.first().click();
          const menuVisible = await page.locator('#petshop-commercial-menu-panel').isVisible();
          if (!menuVisible) {
            failures.push(`${commercialPage.slug} ${viewport.name}: menu mobile nao abriu`);
          }
        }
      }

      await page.close();
    }
  }
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error(failures.join('\n'));
  process.exit(1);
}

console.log('Validacao browser do Plano 018 aprovada.');
