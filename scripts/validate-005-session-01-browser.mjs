import path from 'node:path';
import { contrast, createEvidenceDirectory, launchBrowser, normalize } from './lib/browser-helpers.mjs';

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
        const destination = await page.request.get(url, { timeout: 10000 });
        if (destination.status() !== 200) failures.push(`${expectedMenu[index]}: destino respondeu HTTP ${destination.status()}`);
      }
    }

    if (viewport.name === 'desktop-1440' || viewport.name === 'mobile-390') {
      await page.screenshot({ path: path.join(evidenceDir, `final-${viewport.name}.png`), fullPage: true });
    }
    await page.close();
  }

  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
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
  await keyboardSearch.focus();
  await keyboardSearch.fill('C1100046');
  await Promise.all([
    page.waitForURL(/\/product\/conjunto-babador-laco-em-feltro\/?$/, { timeout: 15000 }),
    page.keyboard.press('Enter'),
  ]);
  if (!normalize(await page.locator('h1').first().innerText()).includes('Conjunto Babador + Laco em Feltro')) {
    failures.push('busca por teclado nao abriu o produto esperado');
  }

  for (const query of ['Conjunto Babador', 'C1100046']) {
    await page.goto(`${baseUrl}/?s=${encodeURIComponent(query)}&post_type=product`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    if (!normalize(await page.locator('h1').first().innerText()).includes('Conjunto Babador + Laco em Feltro')) {
      failures.push(`busca ${query}: produto esperado nao encontrado`);
    }
  }

  await page.goto(`${baseUrl}/product/conjunto-babador-laco-em-feltro/`, { waitUntil: 'domcontentloaded', timeout: 15000 });
  const cartButton = page.locator('button.single_add_to_cart_button');
  if (await cartButton.count()) {
    await cartButton.click();
    await page.waitForTimeout(1000);
    const label = await page.locator('.wc-block-mini-cart__button').getAttribute('aria-label');
    if (!label || !/1/.test(label)) failures.push('contador do minicarrinho nao mudou apos adicionar produto');
  } else {
    failures.push('botao de adicionar ao carrinho ausente no produto de teste');
  }
  await page.close();
} finally {
  await browser.close();
}

console.log(JSON.stringify({ results, failures }, null, 2));
if (failures.length) process.exit(1);
