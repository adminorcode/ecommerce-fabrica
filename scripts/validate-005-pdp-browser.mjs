import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('005/pdp');
const browser = await launchBrowser();
const failures = [];

try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  await routeCanonicalNavigation(page, baseUrl);
  const response = await page.goto(`${baseUrl}/product/conjunto-babador-laco-em-feltro/`, {
    waitUntil: 'domcontentloaded',
    timeout: 20000,
  });
  const price = page.locator('.summary .price').first();
  const addToCart = page.locator('.summary .single_add_to_cart_button').first();
  const assurance = page.locator('.petshop-product-assurance').first();

  if (response?.status() !== 200) failures.push(`PDP respondeu HTTP ${response?.status()}`);
  if (!(await price.isVisible())) failures.push('preço da PDP ausente');
  if (!(await addToCart.isVisible())) failures.push('CTA de compra ausente');
  if (!(await assurance.isVisible())) failures.push('aviso administrável da PDP ausente');

  if (await page.locator('.hero-section:visible').count() > 0) failures.push('hero-section padrao do tema visivel na PDP');

  await page.screenshot({ path: `${evidenceDir}/pdp.png`, fullPage: true });
  await page.close();
} finally {
  await browser.close();
}

if (failures.length) throw new Error(failures.join('\n'));
console.log('Gate PDP aprovado.');
