import path from 'node:path';
import { contrast, createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('009');

const failures = [];
const results = [];
const browser = await launchBrowser();
const elementRect = (locator) => locator.evaluate((element) => {
  const rect = element.getBoundingClientRect();
  return {
    top: rect.top,
    left: rect.left,
    width: rect.width,
    height: rect.height,
  };
});
const maxRectDelta = (samples) => samples.reduce((max, rect, index) => {
  if (index === 0) return max;
  const previous = samples[index - 1];
  return Math.max(
    max,
    Math.abs(rect.top - previous.top),
    Math.abs(rect.left - previous.left),
    Math.abs(rect.width - previous.width),
    Math.abs(rect.height - previous.height)
  );
}, 0);

try {
  const context = await browser.newContext();
  const setupPage = await context.newPage();
  await routeCanonicalNavigation(setupPage, baseUrl);
  let productId = Number(process.env.PETSHOP_TEST_PRODUCT_ID || '0');
  if (!productId) {
    const productsResponse = await setupPage.request.get(`${baseUrl}/wp-json/wc/store/v1/products?sku=PLAN013-SIMPLE`);
    const products = productsResponse.ok() ? await productsResponse.json() : [];
    productId = Number(products[0]?.id || 95);
  }
  const cartResponse = await setupPage.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`);
  const addResponse = await setupPage.request.post(`${baseUrl}/wp-json/wc/store/v1/cart/add-item`, {
    headers: { Nonce: cartResponse.headers().nonce || '' },
    data: { id: productId, quantity: 1 },
  });
  if (!addResponse.ok()) {
    await setupPage.goto(`${baseUrl}/loja/`, { waitUntil: 'domcontentloaded', timeout: 20000 });
    const addButton = setupPage.locator('a.add_to_cart_button, button.add_to_cart_button').first();
    if ((await addButton.count()) > 0) {
      await addButton.click();
      await setupPage.waitForTimeout(2000);
    }
  }
  const sessionCookies = await context.cookies(baseUrl);
  await context.addCookies(sessionCookies.map(({ domain, path: cookiePath, ...cookie }) => ({
    ...cookie,
    url: 'http://localhost:8888',
  })));
  await setupPage.close();

  for (const route of [
    { name: 'cart', path: '/carrinho/' },
    { name: 'checkout', path: '/finalizar-compra/' },
  ]) {
    for (const viewport of [
      { name: 'wide-1850', width: 1850, height: 1100 },
      { name: 'desktop-1440', width: 1440, height: 1000 },
      { name: 'desktop-1024', width: 1024, height: 900 },
      { name: 'tablet-768', width: 768, height: 900 },
      { name: 'mobile-390', width: 390, height: 844 },
    ]) {
      const page = await context.newPage({ viewport });
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await routeCanonicalNavigation(page, baseUrl);
      const response = await page.goto(`${baseUrl}${route.path}`, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(2500);
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

      if (route.name === 'checkout') {
        const headers = page.locator('.petshop-commercial-header');
        const header = headers.first();
        const headerBox = await header.boundingBox();
        const assuranceItems = page.locator('[data-petshop-checkout-assurance]');
        const assurance = assuranceItems.first();
        const supportItems = page.locator('.petshop-header-action--support');
        const support = supportItems.first();
        const commercialItems = await page.locator([
          '.petshop-commercial-header__search:visible',
          '.petshop-commercial-header__navigation:visible',
          '.petshop-header-action--wishlist:visible',
          '.petshop-header-action--account:visible',
          '.wc-block-mini-cart:visible',
        ].join(',')).count();
        const headerSamples = [await elementRect(header)];
        const sidebar = page.locator('.wc-block-checkout__sidebar, .wc-block-components-sidebar').first();
        const sidebarSamples = [await elementRect(sidebar).catch(() => ({ top: 0, left: 0, width: 0, height: 0 }))];
        const headerState = {
          header: await headers.count(),
          logo: await page.locator('.petshop-commercial-header__brand a').count(),
          assurance: await assuranceItems.count(),
          assuranceText: (await assurance.innerText().catch(() => '')).trim(),
          support: await supportItems.count(),
          commercialItems,
          headerHeight: headerBox?.height || 0,
        };
        for (let sample = 0; sample < 5; sample += 1) {
          await page.waitForTimeout(1000);
          headerSamples.push(await elementRect(header));
          sidebarSamples.push(await elementRect(sidebar).catch(() => ({ top: 0, left: 0, width: 0, height: 0 })));
        }
        const headerAfter = await header.boundingBox();
        headerState.headerShift = Math.abs((headerAfter?.height || 0) - headerState.headerHeight);
        headerState.headerMovement = maxRectDelta(headerSamples);
        headerState.sidebarMovement = maxRectDelta(sidebarSamples);
        const focusTrail = [];
        await page.keyboard.press('Home');
        for (let step = 0; step < 18; step += 1) {
          await page.keyboard.press('Tab');
          focusTrail.push(await page.evaluate(() => {
            const active = document.activeElement;
            if (!active) return '';
            const className = typeof active.className === 'string' ? active.className : '';
            return [
              active.tagName.toLowerCase(),
              active.getAttribute('href') || active.getAttribute('name') || active.getAttribute('aria-label') || '',
              className,
              active.textContent?.trim().slice(0, 40) || '',
            ].join('|');
          }));
        }
        headerState.keyboard = {
          header: focusTrail.some((entry) => entry.includes('petshop-commercial-header') || entry.includes('petshop-header-action') || entry.includes('custom-logo-link')),
          return: focusTrail.some((entry) => entry.includes('petshop-checkout-return') || entry.includes('/carrinho/')),
          checkout: focusTrail.some((entry) => /wc-block|billing|email|place-order|Finalizar pedido/i.test(entry)),
        };
        record.headerState = headerState;
      }
      results.push(record);

      if (status !== 200) failures.push(`${route.name}/${viewport.name}: HTTP ${status}`);
      if (overflow > 1) failures.push(`${route.name}/${viewport.name}: overflow ${overflow}px`);
      if (route.name === 'checkout') {
        if (record.headerState.header !== 1) failures.push(`${route.name}/${viewport.name}: header ausente ou duplicado`);
        if (record.headerState.logo < 1) failures.push(`${route.name}/${viewport.name}: logo sem area clicavel`);
        if (record.headerState.assurance !== 1 || record.headerState.assuranceText.length === 0) {
          failures.push(`${route.name}/${viewport.name}: mensagem de seguranca ausente`);
        }
        if (record.headerState.support !== 1) failures.push(`${route.name}/${viewport.name}: atendimento ausente`);
        if (record.headerState.commercialItems !== 0) failures.push(`${route.name}/${viewport.name}: itens comerciais visiveis no checkout`);
        if (record.headerState.headerShift > 1) failures.push(`${route.name}/${viewport.name}: header mudou ${record.headerState.headerShift}px apos hidratacao`);
        if (record.headerState.headerMovement > 1) failures.push(`${route.name}/${viewport.name}: header moveu ${record.headerState.headerMovement}px durante 5s`);
        if (record.headerState.sidebarMovement > 1) failures.push(`${route.name}/${viewport.name}: resumo moveu ${record.headerState.sidebarMovement}px apos 5s`);
        if (!record.headerState.keyboard.header) failures.push(`${route.name}/${viewport.name}: teclado nao alcancou header`);
        if (!record.headerState.keyboard.return) failures.push(`${route.name}/${viewport.name}: teclado nao alcancou retorno ao carrinho`);
        if (!record.headerState.keyboard.checkout) failures.push(`${route.name}/${viewport.name}: teclado nao alcancou checkout`);
        if (viewport.width >= 768 && record.headerState.headerHeight > 80) {
          failures.push(`${route.name}/${viewport.name}: header desktop alto demais (${record.headerState.headerHeight}px)`);
        }
        if (viewport.width < 768 && record.headerState.headerHeight > 124) {
          failures.push(`${route.name}/${viewport.name}: header mobile alto demais (${record.headerState.headerHeight}px)`);
        }
      }
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
