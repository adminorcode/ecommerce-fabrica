import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('theme-hero');
const browser = await launchBrowser();
const failures = [];

const targets = [
  { slug: 'shop', url: '/loja/' },
  { slug: 'category-bandanas', url: '/product-category/bandanas/' },
  { slug: 'pdp', url: '/product/conjunto-babador-laco-em-feltro/' },
  { slug: 'product-search', url: '/?s=laco&post_type=product' },
  { slug: 'commercial-page', url: '/animal-republik/', expectsPetshopHero: true },
];

try {
  for (const viewport of [
    { name: 'mobile-390', width: 390, height: 844 },
    { name: 'desktop-1440', width: 1440, height: 1000 },
  ]) {
    for (const target of targets) {
      const page = await browser.newPage({ viewport });
      await routeCanonicalNavigation(page, baseUrl);
      const response = await page.goto(`${baseUrl}${target.url}`, {
        waitUntil: 'networkidle',
        timeout: 30000,
      });

      const metrics = await page.evaluate(() => ({
        visibleThemeHeroes: [...document.querySelectorAll('.hero-section')]
          .filter((element) => !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length)).length,
        visiblePetshopHeroes: [...document.querySelectorAll('.petshop-hero')]
          .filter((element) => !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length)).length,
      }));

      await page.screenshot({
        path: path.join(evidenceDir, `${target.slug}-${viewport.name}.png`),
        fullPage: true,
      });

      if (response?.status() !== 200) {
        failures.push(`${target.slug} ${viewport.name}: HTTP ${response?.status()}`);
      }
      if (metrics.visibleThemeHeroes > 0) {
        failures.push(`${target.slug} ${viewport.name}: hero-section padrao do tema visivel`);
      }
      if (target.expectsPetshopHero && metrics.visiblePetshopHeroes < 1) {
        failures.push(`${target.slug} ${viewport.name}: hero Gutenberg petshop ausente`);
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

console.log('Validacao de remocao do hero-section padrao aprovada.');
