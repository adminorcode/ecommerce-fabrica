import path from 'node:path';
import { canonicalHostHeader, contrast, createEvidenceDirectory, launchBrowser, normalize, routeCanonicalNavigation, withBaseUrl } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('005/session-01');

const expectedMenu = ['Lacos', 'Bandanas', 'Adesivos', 'Gravatas', 'Kits Economicos', 'Colecoes', 'Personalizados'];
const failures = [];
const results = [];
const browser = await launchBrowser();

try {
  for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'desktop-1024', width: 1024, height: 900 },
    { name: 'tablet-768', width: 768, height: 900 },
    { name: 'mobile-390', width: 390, height: 844 },
  ]) {
    const page = await browser.newPage({ viewport });
    await routeCanonicalNavigation(page, baseUrl);
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    const response = await page.goto(baseUrl, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.evaluate(async () => {
      const images = [...document.images];
      await Promise.race([
        Promise.all(images.map((image) => image.complete ? Promise.resolve() : new Promise((resolve) => {
          image.addEventListener('load', resolve, { once: true });
          image.addEventListener('error', resolve, { once: true });
        }))),
        new Promise((resolve) => setTimeout(resolve, 3000)),
      ]);
    });

    const header = page.locator('.petshop-commercial-header');
    const menu = (await header.locator('.petshop-commercial-menu > li > a').allTextContents()).map(normalize);
    const menuUrls = await header.locator('.petshop-commercial-menu > li > a').evaluateAll((links) => links.map((link) => link.href));
    const metrics = await page.evaluate(() => {
      const visible = (element) => !!(element && (element.offsetWidth || element.offsetHeight || element.getClientRects().length));
      const commercialHeader = document.querySelector('.petshop-commercial-header');
      const links = [...commercialHeader.querySelectorAll('a')];
      const searchButton = commercialHeader.querySelector('.woocommerce-product-search button');
      const searchIcon = searchButton?.querySelector('.ct-icon');
      return {
        searchForms: [...commercialHeader.querySelectorAll('form[role="search"], form.woocommerce-product-search')].filter(visible).length,
        searchInputs: [...commercialHeader.querySelectorAll('input[type="search"]')].filter(visible).length,
        miniCarts: [...commercialHeader.querySelectorAll('.wc-block-mini-cart')].filter(visible).length,
        actionLinks: [...commercialHeader.querySelectorAll('.petshop-commercial-header__actions > a')].filter(visible).length,
        textualCarts: links.filter((link) => /^Carrinho$/i.test(link.textContent.trim()) && visible(link)).length,
        siteTitles: [...commercialHeader.querySelectorAll('.site-title')].filter(visible).length,
        nativeHeaders: document.querySelectorAll('#header').length,
        overflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
        overflowElements: [...document.querySelectorAll('body *')]
          .filter((element) => element.getBoundingClientRect().right > document.documentElement.clientWidth + 1)
          .slice(0, 8)
          .map((element) => ({
            tag: element.tagName.toLowerCase(),
            className: typeof element.className === 'string' ? element.className : '',
            right: Math.round(element.getBoundingClientRect().right),
            width: Math.round(element.getBoundingClientRect().width),
          })),
        searchButtonBackground: searchButton ? getComputedStyle(searchButton).backgroundColor : '',
        searchIconFill: searchIcon ? getComputedStyle(searchIcon).fill : '',
      };
    });

    const record = { viewport: viewport.name, status: response?.status(), menu, pageErrors, ...metrics };
    results.push(record);
    if (record.status !== 200) failures.push(`${viewport.name}: HTTP ${record.status}`);
    if (record.searchForms !== 1 || record.searchInputs !== 1) failures.push(`${viewport.name}: busca duplicada ou ausente`);
    if (record.miniCarts !== 1 || record.textualCarts !== 0) failures.push(`${viewport.name}: carrinho duplicado ou ausente`);
    if (record.actionLinks !== 3) failures.push(`${viewport.name}: atendimento, wishlist ou conta ausente`);
    if (record.siteTitles !== 0) failures.push(`${viewport.name}: titulo do site duplicando o logo`);
    if (record.nativeHeaders !== 0) failures.push(`${viewport.name}: estrutura do header nativo ainda presente`);
    if (record.overflow > 1) failures.push(`${viewport.name}: overflow horizontal de ${record.overflow}px`);
    if (contrast(record.searchIconFill, record.searchButtonBackground) < 3) failures.push(`${viewport.name}: icone da busca sem contraste minimo de 3:1`);
    if (pageErrors.length) failures.push(`${viewport.name}: ${pageErrors.length} erro(s) de pagina`);
    if (JSON.stringify(menu) !== JSON.stringify(expectedMenu)) failures.push(`${viewport.name}: menu comercial divergente`);

    if (viewport.name === 'desktop-1440') {
      for (const [index, url] of menuUrls.entries()) {
        const destination = await page.request.get(withBaseUrl(url, baseUrl), {
          headers: canonicalHostHeader(url, baseUrl),
          timeout: 10000,
        });
        if (destination.status() !== 200) failures.push(`${expectedMenu[index]}: destino respondeu HTTP ${destination.status()}`);
      }
    }

    if (viewport.name === 'desktop-1440' || viewport.name === 'mobile-390') {
      await page.screenshot({ path: path.join(evidenceDir, `final-${viewport.name}.png`), fullPage: true });
    }
    await page.close();
  }

  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  await routeCanonicalNavigation(page, baseUrl);
  await page.goto(baseUrl, { waitUntil: 'domcontentloaded', timeout: 15000 });
  const focusable = page.locator('.petshop-commercial-header a, .petshop-commercial-header input, .petshop-commercial-header button');
  const focusIds = await focusable.evaluateAll((elements) => {
    const visible = (element) => !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
    return elements.filter(visible).map((element, index) => {
      const id = `header-focus-${index}`;
      element.dataset.petshopFocusId = id;
      return {
        id,
        name: (element.getAttribute('aria-label') || element.textContent || element.getAttribute('placeholder') || element.querySelector('img')?.alt || '').trim(),
      };
    });
  });
  for (const control of focusIds) {
    if (!control.name) failures.push(`controle sem nome acessivel: ${control.id}`);
  }
  await page.evaluate(() => {
    document.body.tabIndex = -1;
    document.body.focus();
  });
  const tabbed = new Set();
  for (let index = 0; index < focusIds.length + 6; index += 1) {
    await page.keyboard.press('Tab');
    const focused = await page.evaluate(() => {
      const element = document.activeElement;
      return {
        id: element?.dataset?.petshopFocusId || '',
        outline: element ? getComputedStyle(element).outlineStyle : 'none',
      };
    });
    if (focused.id) {
      tabbed.add(focused.id);
      if (focused.outline === 'none') failures.push(`foco invisivel em ${focused.id}`);
    }
  }
  for (const control of focusIds) {
    if (!tabbed.has(control.id)) failures.push(`controle fora da ordem de Tab: ${control.name || control.id}`);
  }

  const keyboardSearch = page.locator('.petshop-commercial-header input[type="search"]');
  await keyboardSearch.locator('xpath=ancestor::form').evaluate((form, targetBaseUrl) => {
    form.action = new URL('/', targetBaseUrl).href;
  }, baseUrl);
  await keyboardSearch.focus();
  await keyboardSearch.fill('C1100046');
  const keyboardRequestPromise = page.waitForRequest((request) => {
    const url = new URL(request.url());
    return url.searchParams.get('s') === 'C1100046' && url.searchParams.get('post_type') === 'product';
  }, { timeout: 15000 });
  await page.keyboard.press('Enter');
  const submittedRequest = await keyboardRequestPromise;
  const submittedResponse = await submittedRequest.response();
  if (!submittedResponse || submittedResponse.status() >= 400) {
    failures.push(`busca por teclado: resposta invalida (${submittedResponse?.status() ?? 'ausente'})`);
  }
  const submittedLocation = submittedResponse?.headers().location || '';
  const submittedBody = submittedResponse?.status() === 200
    ? await submittedResponse.text().catch(() => '')
    : '';
  await page.waitForLoadState('domcontentloaded', { timeout: 15000 }).catch(() => {});
  const keyboardFoundProduct = page.url().includes('/product/conjunto-babador-laco-em-feltro/')
    || await page.locator('a[href*="/product/conjunto-babador-laco-em-feltro/"]').count() > 0
    || submittedLocation.includes('/product/conjunto-babador-laco-em-feltro/')
    || submittedBody.includes('/product/conjunto-babador-laco-em-feltro/');
  if (!keyboardFoundProduct) failures.push('busca por teclado: produto esperado nao foi renderizado');

  for (const query of ['Conjunto Babador', 'C1100046']) {
    const searchUrl = `${baseUrl}/?s=${encodeURIComponent(query)}&post_type=product`;
    const response = await page.request.get(searchUrl, {
      headers: canonicalHostHeader(searchUrl, baseUrl),
      maxRedirects: 0,
      timeout: 15000,
    });
    const location = response.headers().location || '';
    const body = response.status() === 200 ? await response.text() : '';
    if (!location.includes('/product/conjunto-babador-laco-em-feltro/') && !body.includes('/product/conjunto-babador-laco-em-feltro/')) {
      failures.push(`busca ${query}: produto esperado nao encontrado`);
    }
  }

  await page.close();
} finally {
  await browser.close();
}

console.log(JSON.stringify({ results, failures }, null, 2));
if (failures.length) process.exit(1);
