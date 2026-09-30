import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('035');
const browser = await launchBrowser();
const failures = [];

const recordFailure = (condition, message) => {
  if (!condition) {
    failures.push(message);
  }
};

const submenuState = async (page) => page.evaluate(() => {
  const visible = (element) => {
    if (!(element instanceof HTMLElement)) {
      return false;
    }
    const style = getComputedStyle(element);
    const box = element.getBoundingClientRect();
    return style.display !== 'none'
      && style.visibility !== 'hidden'
      && Number(style.opacity) > 0
      && box.width > 0
      && box.height > 0;
  };

  return [...document.querySelectorAll('.petshop-commercial-menu > .menu-item')].map((item) => {
    const link = item.querySelector(':scope > a');
    const submenu = item.querySelector(':scope > .sub-menu');
    const toggle = item.querySelector(':scope > .petshop-commercial-menu__submenu-toggle');
    return {
      title: (link?.textContent || '').trim(),
      hasChildren: item.classList.contains('menu-item-has-children'),
      hasToggle: toggle instanceof HTMLElement,
      submenuVisible: visible(submenu),
      childLabels: submenu
        ? [...submenu.querySelectorAll(':scope > li > a')].map((child) => (child.textContent || '').trim())
        : [],
    };
  });
});

try {
  const desktop = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  await routeCanonicalNavigation(desktop, baseUrl);
  const desktopErrors = [];
  desktop.on('pageerror', (error) => desktopErrors.push(error.message));
  const home = await desktop.goto(`${baseUrl}/`, { waitUntil: 'networkidle', timeout: 30000 });
  recordFailure(home?.ok() === true, `desktop-1440: Home HTTP ${home?.status()}`);

  const items = await submenuState(desktop);
  const parents = items.filter((item) => item.hasChildren);
  const leaves = items.filter((item) => !item.hasChildren);
  recordFailure(parents.length >= 2, 'desktop-1440: menos de dois pais com filhos');
  recordFailure(leaves.length >= 1, 'desktop-1440: nenhum item de primeiro nivel sem filhos');
  recordFailure(items.every((item) => !item.submenuVisible), 'desktop-1440: submenu visivel sem hover');
  recordFailure(
    leaves.every((item) => !item.hasToggle && item.childLabels.length === 0),
    'desktop-1440: item sem filhos ganhou chevron ou caixa vazia'
  );

  const firstParent = desktop.locator('.petshop-commercial-menu > .menu-item-has-children').nth(0);
  const secondParent = desktop.locator('.petshop-commercial-menu > .menu-item-has-children').nth(1);
  const leaf = desktop.locator('.petshop-commercial-menu > .menu-item:not(.menu-item-has-children)').first();

  await firstParent.hover();
  const afterFirstHover = await submenuState(desktop);
  const openAfterFirst = afterFirstHover.filter((item) => item.submenuVisible);
  recordFailure(openAfterFirst.length === 1, 'desktop-1440: hover nao abriu exatamente um dropdown');
  recordFailure(openAfterFirst[0]?.hasChildren === true, 'desktop-1440: hover abriu item sem filhos');
  recordFailure(
    (openAfterFirst[0]?.childLabels.length || 0) > 0,
    'desktop-1440: dropdown aberto sem nomes de filhas'
  );
  await desktop.screenshot({ path: path.join(evidenceDir, 'desktop-1440-first-parent.png') });

  await secondParent.hover();
  const afterSecondHover = await submenuState(desktop);
  const openAfterSecond = afterSecondHover.filter((item) => item.submenuVisible);
  recordFailure(openAfterSecond.length === 1, 'desktop-1440: segundo pai nao fechou o primeiro dropdown');
  recordFailure(
    openAfterSecond[0]?.title !== openAfterFirst[0]?.title,
    'desktop-1440: segundo pai nao abriu o dropdown proprio'
  );
  await desktop.screenshot({ path: path.join(evidenceDir, 'desktop-1440-second-parent.png') });

  await leaf.hover();
  const afterLeafHover = await submenuState(desktop);
  recordFailure(
    afterLeafHover.filter((item) => item.submenuVisible).length === 0,
    'desktop-1440: hover em item sem filhos abriu caixa vazia'
  );

  await firstParent.locator(':scope > a').focus();
  const afterFocus = await submenuState(desktop);
  recordFailure(
    afterFocus.filter((item) => item.submenuVisible).length === 1,
    'desktop-1440: foco no pai nao abriu o dropdown'
  );
  await desktop.keyboard.press('Tab');
  const afterFirstTab = await desktop.evaluate(() => ({
    inSubmenu: document.activeElement?.closest('.petshop-commercial-menu .sub-menu') instanceof HTMLElement,
    isToggle: document.activeElement?.classList.contains('petshop-commercial-menu__submenu-toggle') === true,
  }));
  if (afterFirstTab.isToggle) {
    await desktop.keyboard.press('Tab');
  }
  const focusedInSubmenu = afterFirstTab.inSubmenu
    || await desktop.evaluate(() => document.activeElement?.closest('.petshop-commercial-menu .sub-menu') instanceof HTMLElement);
  recordFailure(focusedInSubmenu, 'desktop-1440: Tab nao entrou nas filhas do dropdown aberto');
  await desktop.keyboard.press('Escape');
  const afterEscape = await submenuState(desktop);
  recordFailure(
    afterEscape.every((item) => !item.submenuVisible),
    'desktop-1440: Escape nao fechou o dropdown'
  );

  await firstParent.hover();
  const child = firstParent.locator(':scope > .sub-menu > .menu-item > a').first();
  await child.evaluate((link, internalBaseUrl) => {
    const target = new URL(link.href);
    link.setAttribute('href', `${internalBaseUrl}${target.pathname}${target.search}`);
  }, baseUrl);
  const childHref = await child.getAttribute('href');
  await Promise.all([
    desktop.waitForURL((url) => url.pathname === new URL(childHref || '/', baseUrl).pathname, { timeout: 30000 }),
    child.click(),
  ]);
  recordFailure(new URL(desktop.url()).pathname === new URL(childHref || '/', baseUrl).pathname, 'desktop-1440: clique na filha nao navegou');
  recordFailure(desktopErrors.length === 0, `desktop-1440: ${desktopErrors.length} erro(s) de pagina`);

  const mobile = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await routeCanonicalNavigation(mobile, baseUrl);
  const mobileErrors = [];
  mobile.on('pageerror', (error) => mobileErrors.push(error.message));
  await mobile.goto(`${baseUrl}/`, { waitUntil: 'networkidle', timeout: 30000 });
  await mobile.locator('.petshop-commercial-header__menu-toggle').click();
  const drawer = mobile.locator('#petshop-commercial-menu-panel');
  recordFailure(await drawer.isVisible(), 'mobile-390: drawer nao abriu');

  const mobileParents = drawer.locator('.petshop-commercial-menu > .menu-item-has-children');
  recordFailure(await mobileParents.count() >= 2, 'mobile-390: menos de dois pais com filhos');

  const firstMobileParent = mobileParents.nth(0);
  const firstChevron = firstMobileParent.locator(':scope > .petshop-commercial-menu__submenu-toggle');
  const firstSubmenu = firstMobileParent.locator(':scope > .sub-menu');
  recordFailure(await firstChevron.isVisible(), 'mobile-390: chevron ausente');
  const chevronBox = await firstChevron.boundingBox();
  recordFailure((chevronBox?.width || 0) >= 44 && (chevronBox?.height || 0) >= 44, 'mobile-390: chevron menor que 44x44');
  recordFailure(await firstSubmenu.isHidden(), 'mobile-390: filhas visiveis antes do chevron');

  const urlBeforeToggle = mobile.url();
  await firstChevron.click();
  recordFailure(mobile.url() === urlBeforeToggle, 'mobile-390: chevron navegou');
  recordFailure(await drawer.isVisible(), 'mobile-390: chevron fechou o drawer');
  recordFailure(await firstSubmenu.isVisible(), 'mobile-390: chevron nao revelou as filhas');

  const secondMobileParent = mobileParents.nth(1);
  const secondChevron = secondMobileParent.locator(':scope > .petshop-commercial-menu__submenu-toggle');
  const secondSubmenu = secondMobileParent.locator(':scope > .sub-menu');
  await secondChevron.click();
  recordFailure(await secondSubmenu.isVisible(), 'mobile-390: segundo pai nao abriu accordion');
  recordFailure(await firstSubmenu.isHidden(), 'mobile-390: segundo pai nao fechou o primeiro accordion');
  await mobile.screenshot({ path: path.join(evidenceDir, 'mobile-390-accordion.png') });

  await mobile.keyboard.press('Escape');
  recordFailure(
    !(await mobile.locator('.petshop-commercial-header').evaluate((header) => header.classList.contains('is-menu-open'))),
    'mobile-390: Escape nao fechou o drawer'
  );
  await mobile.locator('.petshop-commercial-header__menu-toggle').click();
  recordFailure(await drawer.isVisible(), 'mobile-390: drawer nao reabriu apos Escape');
  await secondChevron.click();
  recordFailure(await secondSubmenu.isVisible(), 'mobile-390: accordion nao reabriu apos Escape');

  const mobileChild = secondMobileParent.locator(':scope > .sub-menu > .menu-item > a').first();
  await mobileChild.evaluate((link, internalBaseUrl) => {
    const target = new URL(link.href);
    link.setAttribute('href', `${internalBaseUrl}${target.pathname}${target.search}`);
  }, baseUrl);
  const mobileChildHref = await mobileChild.getAttribute('href');
  await Promise.all([
    mobile.waitForURL((url) => url.pathname === new URL(mobileChildHref || '/', baseUrl).pathname, { timeout: 30000 }),
    mobileChild.click(),
  ]);
  recordFailure(!(await mobile.locator('.petshop-commercial-header').evaluate((header) => header.classList.contains('is-menu-open'))), 'mobile-390: clique na filha nao fechou o drawer');
  recordFailure(mobileErrors.length === 0, `mobile-390: ${mobileErrors.length} erro(s) de pagina`);
} catch (error) {
  failures.push(error instanceof Error ? error.message : String(error));
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error(`Gate 035 browser falhou:\n- ${failures.join('\n- ')}`);
  process.exit(1);
}

console.log('Gate 035 browser: dropdown 1440, segundo pai, item sem filhos, teclado e accordion 390 aprovados.');
