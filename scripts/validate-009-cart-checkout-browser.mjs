import path from 'node:path';
import { contrast, createEvidenceDirectory, launchBrowser } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('009');

const failures = [];
const results = [];
const browser = await launchBrowser();

try {
  const context = await browser.newContext();
  const setupPage = await context.newPage();
  const productId = Number(process.env.PETSHOP_TEST_PRODUCT_ID || '95');
  const addResponse = await setupPage.request.post(`${baseUrl}/wp-json/wc/store/v1/cart/add-item`, {
    data: { id: productId, quantity: 1 },
  });
  if (!addResponse.ok()) {
    await setupPage.goto(`${baseUrl}/shop/`, { waitUntil: 'domcontentloaded', timeout: 20000 });
    const addButton = setupPage.locator('a.add_to_cart_button, button.add_to_cart_button').first();
    if ((await addButton.count()) > 0) {
      await addButton.click();
      await setupPage.waitForTimeout(2000);
    }
  }
  await setupPage.close();

  for (const route of [
    { name: 'cart', path: '/cart/' },
    { name: 'checkout', path: '/checkout/' },
  ]) {
    for (const viewport of [
      { name: 'desktop-1440', width: 1440, height: 1000 },
      { name: 'mobile-390', width: 390, height: 844 },
    ]) {
      const page = await context.newPage({ viewport });
      const response = await page.goto(`${baseUrl}${route.path}`, { waitUntil: 'domcontentloaded', timeout: 20000 });
      const status = response?.status() ?? 0;
      const overflow = await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth));
      const primaryButton = page.locator(
        route.name === 'cart'
          ? '.wc-block-cart .wc-block-components-button:not(.is-link):not(.is-secondary)'
          : '.wc-block-checkout .wc-block-components-checkout-place-order-button'
      ).first();
      const hasPrimary = (await primaryButton.count()) > 0;
      let primaryStyles = null;
      if (hasPrimary) {
        primaryStyles = await primaryButton.evaluate((element) => {
          const styles = getComputedStyle(element);
          return {
            background: styles.backgroundColor,
            color: styles.color,
            minHeight: styles.minHeight,
          };
        });
      }
      const searchInput = page.locator('.petshop-commercial-header__search .search-field').first();
      const searchAriaLabel = await searchInput.getAttribute('aria-label');
      const searchId = await searchInput.getAttribute('id');
      const associatedLabelCount = searchId
        ? await page.locator(`label[for="${searchId}"]`).count()
        : 0;
      const record = {
        route: route.name,
        viewport: viewport.name,
        status,
        overflow,
        hasPrimary,
        primaryStyles,
        searchAccessible: Boolean(searchAriaLabel?.trim()) || associatedLabelCount > 0,
      };
      results.push(record);

      if (status !== 200) failures.push(`${route.name}/${viewport.name}: HTTP ${status}`);
      if (overflow > 1) failures.push(`${route.name}/${viewport.name}: overflow ${overflow}px`);
      if (viewport.name === 'mobile-390' && !record.searchAccessible) {
        failures.push(`${route.name}/mobile-390: busca sem label associado ou aria-label`);
      }
      if (hasPrimary && primaryStyles) {
        const ratio = contrast(primaryStyles.color, primaryStyles.background);
        if (ratio < 4.5) failures.push(`${route.name}/${viewport.name}: contraste CTA ${ratio.toFixed(2)}`);
        const minHeight = Number.parseFloat(primaryStyles.minHeight);
        if (Number.isFinite(minHeight) && minHeight < 44) {
          failures.push(`${route.name}/${viewport.name}: CTA altura ${primaryStyles.minHeight}`);
        }
      } else if (route.name === 'cart' && viewport.name === 'desktop-1440') {
        failures.push('cart/desktop-1440: CTA primario ausente com carrinho provisionado');
      }
      await page.screenshot({ path: path.join(evidenceDir, `${route.name}-${viewport.name}.png`), fullPage: true });
      await page.close();
    }
  }
} finally {
  await browser.close();
}

console.log(JSON.stringify({ results, failures }, null, 2));
if (failures.length) process.exit(1);
