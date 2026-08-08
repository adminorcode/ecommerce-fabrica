import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const baseOrigin = new URL(baseUrl).origin;
const evidenceDir = createEvidenceDirectory('005/cart');
const browser = await launchBrowser();
const failures = [];

try {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  await routeCanonicalNavigation(page, baseUrl);
  await page.goto(`${baseUrl}/product/conjunto-babador-laco-em-feltro/`, { waitUntil: 'networkidle', timeout: 20000 });
  const addToCart = page.locator('.single_add_to_cart_button').first();
  const cartButton = page.locator('.wc-block-mini-cart__button').first();
  await cartButton.waitFor({ state: 'visible', timeout: 15000 });
  const before = await cartButton.getAttribute('aria-label');

  if (!(await addToCart.isVisible())) {
    failures.push('CTA de compra ausente');
  } else {
    await addToCart.evaluate((button, origin) => {
      const form = button.closest('form.cart');
      if (form) form.action = `${origin}${new URL(form.action).pathname}`;
    }, baseUrl);
    await Promise.all([
      page.waitForRequest((request) => request.method() === 'POST' && request.url().startsWith(baseOrigin), { timeout: 15000 }),
      addToCart.click({ noWaitAfter: true }),
    ]);
    const confirmation = await context.newPage();
    await confirmation.goto(`${baseUrl}/product/conjunto-babador-laco-em-feltro/`, { waitUntil: 'networkidle', timeout: 20000 });
    const after = await confirmation.locator('.wc-block-mini-cart__button').first().getAttribute('aria-label');
    await confirmation.close();
    if (!after || after === before || /0 item/i.test(after)) failures.push('contador do minicarrinho não incrementou');
  }

  await page.screenshot({ path: `${evidenceDir}/cart.png`, fullPage: true });
  await page.close();
  await context.close();
} finally {
  await browser.close();
}

if (failures.length) throw new Error(failures.join('\n'));
console.log('Gate carrinho aprovado.');
