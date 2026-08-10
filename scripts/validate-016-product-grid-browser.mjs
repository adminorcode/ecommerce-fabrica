import path from 'node:path';
import { createEvidenceDirectory, launchBrowser } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('016');
const failures = [];
const browser = await launchBrowser();

try {
  for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1100 },
    { name: 'desktop-1024', width: 1024, height: 900 },
    { name: 'tablet-768', width: 768, height: 900 },
    { name: 'mobile-390', width: 390, height: 900 },
  ]) {
    const page = await browser.newPage({ viewport });
    const consoleErrors = [];
    const requestErrors = [];
    page.on('console', (message) => {
      if (message.type() === 'error') {
        consoleErrors.push(message.text());
      }
    });
    page.on('pageerror', (error) => {
      consoleErrors.push(error.message);
    });
    page.on('requestfailed', (request) => {
      const failure = request.failure();
      const url = request.url();
      const sameOrigin = url.startsWith(baseUrl);
      if (sameOrigin) {
        requestErrors.push(`${url}: ${failure?.errorText || 'failed'}`);
      }
    });
    const response = await page.goto(baseUrl, { waitUntil: 'domcontentloaded', timeout: 20000 });
    await page.evaluate(async () => {
      await Promise.race([
        Promise.all([...document.images].map((image) => (image.complete ? Promise.resolve() : new Promise((resolve) => {
          image.addEventListener('load', resolve, { once: true });
          image.addEventListener('error', resolve, { once: true });
        })))),
        new Promise((resolve) => setTimeout(resolve, 3000)),
      ]);
    });

    const metrics = await page.evaluate(() => {
      const visible = (element) => !!(element && (element.offsetWidth || element.offsetHeight || element.getClientRects().length));
      const sections = [...document.querySelectorAll('.petshop-product-showcase')].filter(visible);
      const productLists = sections.flatMap((section) => [...section.querySelectorAll('ul.products')].filter(visible));
      const products = sections.flatMap((section) => [...section.querySelectorAll('li.product')].filter(visible));
      const emptyVisibleSections = sections.filter((section) => !section.querySelector('li.product'));
      const minProductWidth = products.length
        ? Math.min(...products.map((product) => product.getBoundingClientRect().width))
        : 0;

      return {
        sectionCount: sections.length,
        productListCount: productLists.length,
        productCount: products.length,
        emptyVisibleSections: emptyVisibleSections.length,
        overflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
        minProductWidth,
      };
    });

    await page.screenshot({
      path: path.join(evidenceDir, `${viewport.name}.png`),
      fullPage: true,
    });

    if (response?.status() !== 200) {
      failures.push(`${viewport.name}: HTTP ${response?.status()}`);
    }
    if (metrics.overflow > 1) {
      failures.push(`${viewport.name}: overflow horizontal ${metrics.overflow}px`);
    }
    if (metrics.sectionCount > 0 && metrics.productListCount < 1) {
      failures.push(`${viewport.name}: secoes de vitrine visiveis sem listas de produtos`);
    }
    if (metrics.emptyVisibleSections > 0) {
      failures.push(`${viewport.name}: vitrine vazia ficou visivel no storefront`);
    }
    if (metrics.productCount > 0 && metrics.minProductWidth < 120) {
      failures.push(`${viewport.name}: card de produto abaixo de 120px de largura`);
    }
    const blockingConsoleErrors = consoleErrors.filter((message) => !message.includes('Failed to load resource'));
    if (blockingConsoleErrors.length > 0) {
      failures.push(`${viewport.name}: console/page errors: ${blockingConsoleErrors.join(' | ')}`);
    }
    if (requestErrors.length > 0) {
      failures.push(`${viewport.name}: request errors: ${requestErrors.join(' | ')}`);
    }
  }
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error(failures.join('\n'));
  process.exit(1);
}

console.log('Validacao browser do Plano 016 aprovada.');
