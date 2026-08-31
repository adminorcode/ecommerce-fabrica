import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('022');
const browser = await launchBrowser();
const failures = [];

// A secao "Compre por categoria" e ocultada pelo tema em <=900px.
const viewports = [
  { name: 'desktop-1440', width: 1440, height: 1000, expectGridVisible: true },
  { name: 'tablet-1024', width: 1024, height: 900, expectGridVisible: true },
  { name: 'mobile-390', width: 390, height: 844, expectGridVisible: false },
];

try {
  for (const viewport of viewports) {
    const page = await browser.newPage({ viewport });
    await routeCanonicalNavigation(page, baseUrl);
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    const response = await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle', timeout: 25000 });
    const sectionHiddenByTheme = await page.evaluate(() => {
      const section = document.querySelector('.petshop-section:has(.petshop-category-grid)');
      if (!section) {
        return true;
      }
      return getComputedStyle(section).display === 'none';
    });
    const metrics = await page.evaluate(() => {
      const grid = document.querySelector('.petshop-category-grid');
      const rect = grid ? grid.getBoundingClientRect() : null;
      return {
        cardCount: document.querySelectorAll('.petshop-category-card').length,
        iconCount: document.querySelectorAll('.petshop-category-card__icon').length,
        mediaCount: document.querySelectorAll('.petshop-category-card__icon--media img').length,
        height: rect ? rect.height : 0,
        width: rect ? rect.width : 0,
        pageOverflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
      };
    });

    if (response?.status() !== 200) failures.push(`${viewport.name}: HTTP ${response?.status()}`);
    if (pageErrors.length) failures.push(`${viewport.name}: ${pageErrors.length} erro(s) de pagina`);

    if (viewport.expectGridVisible) {
      if (sectionHiddenByTheme) failures.push(`${viewport.name}: secao Compre por categoria invisivel`);
      if (metrics.cardCount < 1) failures.push(`${viewport.name}: grade de categorias ausente`);
      if (metrics.iconCount < metrics.cardCount) failures.push(`${viewport.name}: icones ausentes em cards`);
      if (metrics.height < 40 || metrics.width < 40) failures.push(`${viewport.name}: grade sem altura util`);
      if (metrics.pageOverflow > 1) failures.push(`${viewport.name}: overflow horizontal de ${metrics.pageOverflow}px`);

      const mediaIcons = page.locator('.petshop-category-card__icon--media img');
      const mediaCount = await mediaIcons.count();
      for (let index = 0; index < Math.min(mediaCount, 4); index += 1) {
        const natural = await mediaIcons.nth(index).evaluate((img) => ({
          complete: img.complete,
          naturalWidth: img.naturalWidth,
          width: img.getBoundingClientRect().width,
          height: img.getBoundingClientRect().height,
        }));
        if (!natural.complete || natural.naturalWidth < 1) {
          failures.push(`${viewport.name}: icone personalizado ${index} nao carregou`);
        }
        if (natural.width < 16 || natural.height < 16) {
          failures.push(`${viewport.name}: icone personalizado ${index} com tamanho invalido`);
        }
      }
    } else if (!sectionHiddenByTheme) {
      // Tema atual oculta a secao em <=900px; se estiver visivel (CSS dessincronizado),
      // ainda assim valida overflow e altura em vez de falhar so pela visibilidade.
      if (metrics.height < 40 || metrics.width < 40) {
        failures.push(`${viewport.name}: grade visivel sem altura util`);
      }
      if (metrics.pageOverflow > 1) {
        failures.push(`${viewport.name}: overflow horizontal de ${metrics.pageOverflow}px`);
      }
    }

    await page.screenshot({ path: path.join(evidenceDir, `home-${viewport.name}.png`), fullPage: false });
    await page.close();
  }
} finally {
  await browser.close();
}

console.log(JSON.stringify({ failures }, null, 2));
if (failures.length) process.exit(1);
