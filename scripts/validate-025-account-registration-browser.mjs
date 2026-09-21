import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('025');
const failures = [];
const browser = await launchBrowser();

const recordFailure = (condition, message) => {
  if (!condition) failures.push(message);
};

const ownPageErrors = (errors) => errors.filter((message) => (
  !/crypto\.randomUUID is not a function/i.test(message)
  && !/Melidata client load timed out/i.test(message)
));

const visible = async (locator) => (await locator.count()) > 0 && await locator.first().isVisible();

try {
  for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'mobile-390', width: 390, height: 844 },
  ]) {
    const page = await browser.newPage({ viewport });
    await routeCanonicalNavigation(page, baseUrl);
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));

    const response = await page.goto(`${baseUrl}/minha-conta/`, { waitUntil: 'networkidle', timeout: 30000 });
    const wrapper = page.locator('#customer_login');
    const login = page.locator('#customer_login .u-column1.col-1');
    const register = page.locator('#customer_login .u-column2.col-2');
    const create = page.locator('.petshop-account-create');
    const back = page.locator('.petshop-account-back');

    recordFailure(response?.status() === 200, `${viewport.name}: /minha-conta/ HTTP ${response?.status()}`);
    recordFailure(await wrapper.count() === 1, `${viewport.name}: wrapper #customer_login ausente`);
    recordFailure(await visible(login), `${viewport.name}: login deveria estar visivel inicialmente`);
    recordFailure(!(await visible(register)), `${viewport.name}: cadastro deveria iniciar oculto`);
    recordFailure(await visible(create), `${viewport.name}: botao Criar conta ausente`);

    if (await create.count()) {
      await create.click();
      recordFailure(await visible(register), `${viewport.name}: cadastro nao abriu apos Criar conta`);
      recordFailure(!(await visible(login)), `${viewport.name}: login deveria ocultar ao abrir cadastro`);
      recordFailure(await register.locator('input[name="password"]').count() === 1, `${viewport.name}: senha ausente no cadastro`);
      recordFailure(await register.locator('input[name="password_confirm"]').count() === 1, `${viewport.name}: confirmacao de senha ausente no cadastro`);
      recordFailure(await register.locator('input[name="billing_first_name"]').count() === 1, `${viewport.name}: nome ausente no cadastro`);
      recordFailure(await register.locator('input[name="billing_last_name"]').count() === 1, `${viewport.name}: sobrenome ausente no cadastro`);
      recordFailure(await register.locator('input[name="billing_phone"]').count() === 0, `${viewport.name}: telefone nao deveria aparecer no cadastro inicial`);
      recordFailure(await register.locator('select[name="petshop_person_type"]').count() === 0, `${viewport.name}: tipo PF/PJ nao deveria aparecer no cadastro inicial`);
      recordFailure(await register.locator('input[name="petshop_document"]').count() === 0, `${viewport.name}: CPF/CNPJ nao deveria aparecer no cadastro inicial`);
      recordFailure(await register.locator('input[name="billing_postcode"]').count() === 0, `${viewport.name}: CEP nao deveria aparecer no cadastro inicial`);

      const passwordPair = await page.evaluate(() => {
        const registerForm = document.querySelector('#customer_login form.register');
        const passwordRow = registerForm?.querySelector('#reg_password, input[name="password"]')?.closest('p, .form-row');
        const confirmRow = registerForm?.querySelector('#password_confirm, input[name="password_confirm"]')?.closest('p, .form-row');
        const nameRow = registerForm?.querySelector('input[name="billing_first_name"]')?.closest('p, .form-row');
        const lastRow = registerForm?.querySelector('input[name="billing_last_name"]')?.closest('p, .form-row');

        if (!passwordRow || !confirmRow || !nameRow || !lastRow) {
          return { found: false, passwordSameRow: false, nameSameRow: false };
        }

        const sameRow = (left, right) => Math.abs(left.getBoundingClientRect().top - right.getBoundingClientRect().top) < 12;

        return {
          found: true,
          passwordSameRow: sameRow(passwordRow, confirmRow),
          nameSameRow: sameRow(nameRow, lastRow),
        };
      });

      recordFailure(passwordPair.found, `${viewport.name}: pares de campo do cadastro ausentes para validar o grid`);
      if (viewport.name === 'desktop-1440') {
        recordFailure(passwordPair.passwordSameRow, `${viewport.name}: senha e confirmacao deveriam ficar na mesma linha`);
        recordFailure(passwordPair.nameSameRow, `${viewport.name}: nome e sobrenome deveriam ficar na mesma linha`);
      }
      if (viewport.name === 'mobile-390') {
        recordFailure(!passwordPair.passwordSameRow, `${viewport.name}: senha e confirmacao deveriam empilhar`);
        recordFailure(!passwordPair.nameSameRow, `${viewport.name}: nome e sobrenome deveriam empilhar`);
      }

      const layout = await page.evaluate(() => {
        const container = document.querySelector('#customer_login .u-column2.col-2');
        const viewportWidth = document.documentElement.clientWidth;

        if (!container) {
          return { found: false, viewportWidth, overflow: 0, offenders: [] };
        }

        const candidates = [container, ...container.querySelectorAll('*')];
        const offenders = candidates
          .map((element) => {
            const rect = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            return {
              tag: element.tagName.toLowerCase(),
              id: element.id || '',
              className: typeof element.className === 'string' ? element.className : '',
              left: Math.round(rect.left),
              right: Math.round(rect.right),
              width: Math.round(rect.width),
              height: Math.round(rect.height),
              position: style.position,
              display: style.display,
              visibility: style.visibility,
            };
          })
          .filter((item) => (
            item.display !== 'none'
            && item.visibility !== 'hidden'
            && item.width > 0
            && item.height > 0
            && item.position !== 'fixed'
            && (item.left < -1 || item.right > viewportWidth + 1)
          ))
          .slice(0, 10);

        const overflow = offenders.reduce((max, item) => Math.max(
          max,
          Math.max(0, -item.left),
          Math.max(0, item.right - viewportWidth),
        ), 0);

        return { found: true, viewportWidth, overflow, offenders };
      });

      recordFailure(layout.found, `${viewport.name}: container de cadastro ausente para validar layout`);
      if (layout.overflow > 1) {
        failures.push(`${viewport.name}: cadastro com overflow horizontal de ${layout.overflow}px; diagnostico=${JSON.stringify(layout)}`);
      }

      if (viewport.name === 'desktop-1440' || viewport.name === 'mobile-390') {
        await page.screenshot({ path: path.join(evidenceDir, `${viewport.name}-minha-conta-cadastro.png`), fullPage: true });
      }

      recordFailure(await visible(back), `${viewport.name}: botao Voltar para entrar ausente`);
      if (await back.count()) {
        await back.click();
        recordFailure(await visible(login), `${viewport.name}: login nao voltou apos Voltar para entrar`);
        recordFailure(!(await visible(register)), `${viewport.name}: cadastro deveria ocultar apos Voltar para entrar`);
      }
    }

    const pageErrors = ownPageErrors(errors);
    recordFailure(pageErrors.length === 0, `${viewport.name}: ${pageErrors.length} erro(s) JavaScript: ${pageErrors.join(' | ')}`);
    await page.close();
  }

  const happyContext = await browser.newContext();
  const happyPage = await happyContext.newPage();
  await happyPage.setViewportSize({ width: 1440, height: 1000 });
  await routeCanonicalNavigation(happyPage, baseUrl);
  const happyErrors = [];
  happyPage.on('pageerror', (error) => happyErrors.push(error.message));

  const happyResponse = await happyPage.goto(`${baseUrl}/minha-conta/`, { waitUntil: 'networkidle', timeout: 30000 });
  recordFailure(happyResponse?.status() === 200, `happy path: /minha-conta/ HTTP ${happyResponse?.status()}`);

  const happyCreate = happyPage.locator('.petshop-account-create');
  const happyRegister = happyPage.locator('#customer_login .u-column2.col-2');

  if (await happyCreate.count()) {
    await happyCreate.click();
  }

  const suffix = String(Date.now());
  const happyEmail = `ticket025-browser-${suffix}@example.com`;

  await happyRegister.locator('input[name="email"]').fill(happyEmail);
  await happyRegister.locator('input[name="password"]').fill('Senha025@Forte');
  await happyRegister.locator('input[name="password_confirm"]').fill('Senha025@Forte');
  await happyRegister.locator('input[name="billing_first_name"]').fill('Maria');
  await happyRegister.locator('input[name="billing_last_name"]').fill('Silva');
  const privacy = happyRegister.locator('input[name="privacy_policy"], input[name="terms"]');
  if (await privacy.count()) {
    await privacy.first().check();
  }
  await happyRegister.locator('button[name="register"], input[name="register"]').click();

  const accountNav = happyPage.locator('.woocommerce-MyAccount-navigation');
  await Promise.race([
    accountNav.waitFor({ state: 'visible', timeout: 20000 }),
    happyPage.locator('.woocommerce-error').first().waitFor({ state: 'visible', timeout: 20000 }),
  ]).catch(() => {});
  const noticeText = await happyPage.locator('.woocommerce-error, .woocommerce-notices-wrapper').textContent().catch(() => '');
  recordFailure(
    await accountNav.count() === 1 && await accountNav.first().isVisible(),
    `happy path: cadastro valido nao autenticou no painel; url=${happyPage.url()} aviso="${(noticeText || '').trim()}"`
  );

  const bodyText = await happyPage.locator('body').innerText();
  recordFailure(
    !/temporary password|senha tempor[aá]ria|\bResend\b/i.test(bodyText),
    'happy path: banner de senha temporaria ainda aparece apos cadastro'
  );
  const happyPageErrors = ownPageErrors(happyErrors);
  recordFailure(happyPageErrors.length === 0, `happy path: ${happyPageErrors.length} erro(s) JavaScript: ${happyPageErrors.join(' | ')}`);
  await happyPage.screenshot({ path: path.join(evidenceDir, 'desktop-1440-minha-conta-cadastro-sucesso.png'), fullPage: true });
  await happyContext.close();

  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  await routeCanonicalNavigation(page, baseUrl);
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));

  const fixtureResponse = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/products?per_page=100`);
  const fixtureProducts = fixtureResponse.ok() ? await fixtureResponse.json() : [];
  const simpleProduct = fixtureProducts.find((product) => (
    product?.type === 'simple'
    && product?.is_purchasable !== false
    && product?.is_in_stock !== false
  )) || fixtureProducts.find((product) => product?.type === 'simple');
  const productId = Number(simpleProduct?.id || 0);
  recordFailure(productId > 0, 'checkout: nenhum produto simples disponivel para montar o carrinho de teste');

  if (productId > 0) {
    const initialCartResponse = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`);
    const nonce = initialCartResponse.headers().nonce || '';
    const addResponse = await page.request.post(`${baseUrl}/wp-json/wc/store/v1/cart/add-item`, {
      headers: { Nonce: nonce },
      data: { id: productId, quantity: 1 },
    });
    recordFailure(addResponse.ok(), `checkout: fixture nao adicionada ao carrinho (HTTP ${addResponse.status()})`);
  }

  const checkoutResponse = await page.goto(`${baseUrl}/finalizar-compra/`, { waitUntil: 'networkidle', timeout: 30000 });
  recordFailure(checkoutResponse?.status() === 200, `checkout: HTTP ${checkoutResponse?.status()}`);

  const accountCheckbox = page.getByRole('checkbox', { name: /crie uma conta|criar uma conta/i }).first();
  recordFailure(await accountCheckbox.count() === 1, 'checkout: opcao Criar uma conta ausente');

  if (await accountCheckbox.count()) {
    if (!(await accountCheckbox.isChecked())) await accountCheckbox.check();

    const nativePassword = page.locator('input[type="password"]').filter({ hasNot: page.locator('#petshop-checkout-password-confirmation') }).first();
    const confirmation = page.locator('#petshop-checkout-password-confirmation');

    await confirmation.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
    recordFailure(await nativePassword.count() === 1, 'checkout: campo nativo Criar uma senha ausente');
    recordFailure(await confirmation.count() === 1 && await confirmation.isVisible(), 'checkout: Confirmar senha ausente');

    if (await nativePassword.count() && await confirmation.count()) {
      await nativePassword.fill('Teste025@2026');
      await confirmation.fill('Teste025@ERRADA');
      await confirmation.blur();
      const mismatch = page.locator('#petshop-checkout-password-confirmation-error');
      await mismatch.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
      recordFailure(/As senhas não coincidem\./.test(await mismatch.textContent() || ''), 'checkout: senhas diferentes sem erro');

      await confirmation.fill('Teste025@2026');
      await confirmation.blur();
      await page.waitForFunction(() => {
        const error = document.getElementById('petshop-checkout-password-confirmation-error');
        return !error || error.hidden || (error.textContent || '').trim() === '';
      }, null, { timeout: 3000 }).catch(() => {});
      recordFailure((await mismatch.textContent() || '').trim() === '', 'checkout: erro nao sumiu com senhas iguais');
    }

    await page.screenshot({ path: path.join(evidenceDir, 'desktop-1440-checkout-conta.png'), fullPage: true });

    await accountCheckbox.uncheck();
    await confirmation.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {});
    recordFailure(await confirmation.count() === 0 || !(await confirmation.isVisible()), 'checkout: Confirmar senha deveria desaparecer ao desmarcar Criar conta');
  }

  const checkoutErrors = ownPageErrors(errors);
  recordFailure(checkoutErrors.length === 0, `checkout: ${checkoutErrors.length} erro(s) JavaScript: ${checkoutErrors.join(' | ')}`);
  await page.close();
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error(failures.join('\n'));
  process.exit(1);
}

console.log('Validacao browser do Plano 025 aprovada.');
