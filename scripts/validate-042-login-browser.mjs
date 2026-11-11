import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation, contrast } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidence = createEvidenceDirectory('042');
const browser = await launchBrowser();
const fixtures = [];
const results = [];
try {
  for (const width of [1440, 1024, 768, 390, 360]) {
    const context = await browser.newContext({ viewport: { width, height: 1000 } });
    const page = await context.newPage();
    await routeCanonicalNavigation(page, baseUrl);
    await page.goto(baseUrl + '/minha-conta/', { waitUntil: 'networkidle' });
    const localFonts = await page.evaluate(async () => {
      const faces = await Promise.all([
        document.fonts.load('400 16px "Autelie Login Sans"'),
        document.fonts.load('700 34px "Autelie Login Hand"'),
      ]);
      return faces.every(loaded => loaded.length > 0 && loaded.every(face => face.status === 'loaded'));
    });
    assert(localFonts, 'local reference fonts must load');
    const scene = page.locator('.petshop-account-scene');
    assert.equal(await scene.count(), 1);
    assert.equal(await page.locator('#customer_login').count(), 1);
    assert.equal(await page.locator('.petshop-account-create').count(), 1);
    assert.equal((await page.locator('form.login [name="login"]').innerText()).replace(/\s+/g, ' '), 'Acessar →');
    const layout = await scene.evaluate(el => {
      const card = el.querySelector('.petshop-account-card').getBoundingClientRect();
      const editorial = el.querySelector('.petshop-account-editorial').getBoundingClientRect();
      const img = el.querySelector('img');
      const targets = [...el.querySelectorAll('button, .woocommerce-LostPassword a, .woocommerce-form-login__rememberme')]
        .filter(x => x.getBoundingClientRect().height > 0)
        .map(x => ({ text: x.getAttribute('aria-label') || x.textContent.trim(), class: x.className, width: x.getBoundingClientRect().width, height: x.getBoundingClientRect().height }));
      const submit = el.querySelector('[name="login"]');
      const heading = el.querySelector('h1');
      // Resolve modern CSS color-mix into RGB before using the contrast helper.
      const canvas = document.createElement('canvas');
      canvas.width = canvas.height = 1;
      const colorContext = canvas.getContext('2d');
      colorContext.fillStyle = getComputedStyle(heading).color;
      colorContext.fillRect(0, 0, 1, 1);
      const channels = colorContext.getImageData(0, 0, 1, 1).data;
      const headingColor = `rgb(${channels[0]}, ${channels[1]}, ${channels[2]})`;
      return {
        overflow: [...el.querySelectorAll('*')].some(x => x.getBoundingClientRect().width > 0 && x.getBoundingClientRect().right > innerWidth + 1),
        pageOverflow: document.documentElement.scrollWidth > innerWidth,
        overflowElements: [...document.querySelectorAll('body *')].filter(x => x.getBoundingClientRect().right > innerWidth + 1 && x.getBoundingClientRect().width > 0).slice(0, 10).map(x => ({ tag: x.tagName, class: x.className, right: x.getBoundingClientRect().right })),
        columns: editorial.left > card.right - 1, stacked: editorial.top >= card.bottom,
        image: { width: img.width, naturalWidth: img.naturalWidth, loading: img.loading, dimensions: img.hasAttribute('width') && img.hasAttribute('height') },
        targets, button: { foreground: getComputedStyle(submit).color, background: getComputedStyle(submit).backgroundColor },
        heading: { foreground: headingColor, background: getComputedStyle(el.querySelector('.petshop-account-card')).backgroundColor },
      };
    });
    await page.screenshot({ path: path.join(evidence, 'layout-' + width + '.png'), fullPage: true });
    assert.equal(layout.overflow, false, width + ': horizontal overflow ' + JSON.stringify(layout.overflowElements));
    assert.equal(layout.pageOverflow, false, width + ': page horizontal overflow');
    assert(width >= 1024 ? layout.columns : layout.stacked, width + ': composition');
    assert(layout.image.naturalWidth > 0 && layout.image.dimensions && layout.image.loading === 'eager', width + ': image');
    for (const target of layout.targets) assert(target.height >= 44 && target.width >= 44, width + ': small target ' + JSON.stringify(target));
    assert(contrast(layout.button.foreground, layout.button.background) >= 4.5);
    assert(contrast(layout.heading.foreground, layout.heading.background) >= 3);
    await page.locator('#password').fill('Temporary-test-only');
    const toggle = page.getByRole('button', { name: 'Mostrar senha', exact: true });
    await toggle.focus();
    await toggle.press('Space');
    assert.equal(await page.locator('#password').getAttribute('type'), 'text');
    assert.equal(await page.getByRole('button', { name: 'Ocultar senha' }).getAttribute('aria-pressed'), 'true');
    await page.getByRole('button', { name: 'Ocultar senha' }).press('Space');
    await page.locator('#password').fill('');
    assert.equal(await page.locator('#username').getAttribute('autocomplete'), 'username');
    assert(await page.locator('#username').evaluate(el => el.required && el.getAttribute('aria-required') === 'true'));
    assert(await page.locator('#password').evaluate(el => el.required && el.getAttribute('aria-required') === 'true'));
    assert.equal(await page.locator('#password').getAttribute('autocomplete'), 'current-password');
    await page.screenshot({ path: path.join(evidence, 'login-' + width + '.png'), fullPage: true });
    if (width === 1440) await page.screenshot({ path: path.join(evidence, 'login-desktop-preview.png'), fullPage: false });
    await page.locator('.petshop-account-create').click();
    assert.equal(await page.locator('form.register').isVisible(), true);
    assert.equal(await page.locator('.petshop-account-create').getAttribute('aria-expanded'), 'true');
    assert.equal(await page.locator('form.register input:not([type="hidden"])').first().evaluate(el => el === document.activeElement), true);
    assert.equal(await scene.evaluate(el => el.scrollWidth > el.clientWidth), false);
    await page.screenshot({ path: path.join(evidence, 'register-' + width + '.png'), fullPage: true });
    await page.locator('.petshop-account-back').click();
    assert.equal(await page.locator('#username').evaluate(el => el === document.activeElement), true);
    await page.locator('.woocommerce-LostPassword a').click();
    await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('.woocommerce-ResetPassword').count(), 1);
    assert.equal(await page.locator('.petshop-account-scene').count(), 0);
    results.push({ width, layout });
    await context.close();
  }
  const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const fallback = await noJs.newPage();
  await routeCanonicalNavigation(fallback, baseUrl);
  await fallback.goto(baseUrl + '/minha-conta/');
  assert(await fallback.locator('form.login').isVisible());
  assert(await fallback.locator('form.register').isVisible());
  assert.equal(await fallback.locator('.petshop-account-scene').evaluate(el => el.scrollWidth > el.clientWidth), false);
  await noJs.close();

  // Create a disposable customer through the real registration form, then exercise both login identifiers.
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  await routeCanonicalNavigation(page, baseUrl);
  await page.goto(baseUrl + '/minha-conta/', { waitUntil: 'networkidle' });
  // Bypass browser validation only in this test to verify the native server error.
  await page.locator('form.login').evaluate(form => { form.noValidate = true; });
  await page.locator('form.login [name="login"]').click();
  await page.waitForLoadState('networkidle');
  assert(await page.locator('.woocommerce-error, [role="alert"]').first().isVisible(), 'empty required fields');
  assert.equal(await page.locator('.woocommerce-MyAccount-navigation').count(), 0);
  await page.locator('#username').fill('missing-042-account');
  await page.locator('#password').fill('invalid-test-only');
  await page.locator('#password').press('Enter');
  await page.waitForLoadState('networkidle');
  assert(await page.locator('.woocommerce-error, [role="alert"]').first().isVisible());
  await page.locator('.petshop-account-create').click();
  const email = 'petshop042-' + Date.now() + '@example.invalid';
  fixtures.push(email);
  const password = 'Local042-Test!Password98';
  await page.locator('#reg_email').fill(email);
  await page.locator('#reg_password').fill(password);
  await page.locator('#password_confirm').fill(password);
  await page.locator('[name="billing_first_name"]').fill('Teste');
  await page.locator('[name="billing_last_name"]').fill('Plano042');
  await page.locator('form.register [name="register"]').click();
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: path.join(evidence, 'registration-result.png'), fullPage: true });
  assert.equal(await page.locator('.woocommerce-MyAccount-navigation').count(), 1, 'registration authenticates: ' + await page.locator('.woocommerce-error, [role="alert"]').allTextContents());
  assert.equal(await page.locator('.petshop-account-scene').count(), 0, 'logged-in editorial hidden');
  const username = (await page.locator('.woocommerce-MyAccount-content').innerText()).match(/Olá,\s*([^\s(]+)/)?.[1];
  assert(username, 'username greeting');
  await page.screenshot({ path: path.join(evidence, 'authenticated.png'), fullPage: true });
  for (const identifier of [email, username]) {
    await context.clearCookies();
    await page.goto(baseUrl + '/minha-conta/', { waitUntil: 'networkidle' });
    await page.locator('#username').fill(identifier);
    await page.locator('#password').fill(password);
    if (identifier === email) await page.locator('form.login [name="login"]').click();
    else await page.locator('#password').press('Enter');
    await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('.woocommerce-MyAccount-navigation').count(), 1, 'login ' + (identifier === email ? 'email' : 'username'));
  }
  await context.close();
  console.log('042 browser: 5 breakpoints, keyboard, contrast, no-JS, errors, registration and username/email login passed.');
} finally {
  fs.writeFileSync(path.join(evidence, 'report.json'), JSON.stringify({ results, fixtureEmails: fixtures }, null, 2));
  await browser.close();
}
