import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('026');
const failures = [];
const browser = await launchBrowser();
const createdEmails = [];
const NAV_TIMEOUT = 30000;
const ACTION_TIMEOUT = 10000;
const API_TIMEOUT = 15000;
const CLEANUP_TIMEOUT = 20000;
const DIAG_AUTH = process.env.PETSHOP_DIAG_AUTH === '1';
const DIAG_CHECKOUT = process.env.PETSHOP_DIAG_CHECKOUT === '1';
const DIAG_VIACEP = process.env.PETSHOP_DIAG_VIACEP === '1';

const recordFailure = (condition, message) => {
  if (!condition) failures.push(message);
};

const log = (message) => console.log(`[026] ${message}`);

const withTimeout = (promise, label, timeoutMs) => Promise.race([
  promise,
  new Promise((_, reject) => {
    setTimeout(() => reject(new Error(`${label} excedeu ${timeoutMs}ms`)), timeoutMs);
  }),
]);

const step = async (label, callback) => {
  log(`${label}: inicio`);
  const before = failures.length;
  try {
    const result = await callback();
    if (failures.length > before) {
      log(`${label}: FALHOU`);
    } else {
      log(`${label}: OK`);
    }
    return result;
  } catch (error) {
    log(`${label}: FALHOU - ${error instanceof Error ? error.message : String(error)}`);
    throw error;
  }
};

const printFailuresBeforeCleanup = () => {
  if (failures.length === 0) {
    log('falhas antes cleanup: nenhuma');
    return;
  }

  log('falhas antes cleanup:');
  for (const failure of failures) {
    console.error(`[026] - ${failure}`);
  }
};

const runViaCepSubprocess = () => {
  const scriptPath = fileURLToPath(import.meta.url);
  const result = spawnSync(process.execPath, [scriptPath], {
    env: {
      ...process.env,
      PETSHOP_DIAG_VIACEP: '1',
    },
    stdio: 'inherit',
  });

  if (result.error) {
    recordFailure(false, `ViaCEP subprocesso falhou: ${result.error.message}`);
    return;
  }

  recordFailure(
    result.status === 0,
    `ViaCEP subprocesso terminou com ${result.status === null ? `signal ${result.signal}` : `exit ${result.status}`}`,
  );
};

const visible = async (locator) => (await locator.count()) > 0 && await locator.first().isVisible();
const text = async (locator) => ((await locator.count()) ? await locator.first().textContent() : '') || '';
const valueOf = async (locator) => ((await locator.count()) ? locator.first().inputValue().catch(() => '') : '');
const selectValueOf = async (locator) => ((await locator.count()) ? locator.first().evaluate((field) => field.value).catch(() => '') : '');
const digitsOnly = (value = '') => String(value).replace(/\D/g, '');
const logoutSelectors = 'a.woocommerce-MyAccount-navigation-link--customer-logout, .woocommerce-MyAccount-navigation-link--customer-logout a, a[href*="customer-logout"]';

const firstVisible = async (page, selectors) => {
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if (await visible(locator)) return locator;
  }
  return page.locator(selectors[0]).first();
};

const firstExisting = async (page, selectors) => {
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if (await locator.count()) return locator;
  }
  return page.locator(selectors[0]).first();
};

const seededDigits = (seed, length) => {
  let state = 2166136261;
  for (const char of String(seed)) {
    state ^= char.charCodeAt(0);
    state = Math.imul(state, 16777619) >>> 0;
  }

  const digits = [];
  while (digits.length < length) {
    state = (Math.imul(state, 1664525) + 1013904223) >>> 0;
    digits.push(state % 10);
  }

  if (digits.every((digit) => digit === digits[0])) {
    digits[digits.length - 1] = (digits[digits.length - 1] + 1) % 10;
  }

  return digits;
};

const cpfCheckDigit = (digits, factorStart) => {
  const total = digits.reduce((sum, digit, index) => sum + digit * (factorStart - index), 0);
  const rest = (total * 10) % 11;
  return rest === 10 ? 0 : rest;
};

const generateCpf = (seed) => {
  const digits = seededDigits(`cpf-${seed}`, 9);
  digits.push(cpfCheckDigit(digits, 10));
  digits.push(cpfCheckDigit(digits, 11));
  return digits.join('');
};

const cnpjCheckDigit = (digits, weights) => {
  const total = digits.reduce((sum, digit, index) => sum + digit * weights[index], 0);
  const rest = total % 11;
  return rest < 2 ? 0 : 11 - rest;
};

const generateCnpj = (seed) => {
  const digits = seededDigits(`cnpj-${seed}`, 8).concat([0, 0, 0, 1]);
  digits.push(cnpjCheckDigit(digits, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]));
  digits.push(cnpjCheckDigit(digits, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]));
  return digits.join('');
};

const authEvidence = async (page, context) => {
  const bodyLoggedIn = await page.evaluate(() => document.body.classList.contains('logged-in')).catch(() => false);
  const logoutLinks = await page.locator(logoutSelectors).count();
  const cookieNames = [...new Set((await context.cookies()).map((cookie) => cookie.name))].sort();
  const hasLoggedInCookie = cookieNames.some((name) => name.startsWith('wordpress_logged_in_'));

  return { bodyLoggedIn, logoutLinks, cookieNames, hasLoggedInCookie };
};

const logAuthDiagnostic = async (page, context, customer) => {
  const evidence = await authEvidence(page, context);
  const cartResponse = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`, { timeout: API_TIMEOUT });
  const cart = cartResponse.ok() ? await cartResponse.json().catch(() => ({})) : {};
  const billing = cart.billing_address || {};

  console.log(`[026-DIAG] kind=${customer.kind}`);
  console.log(`[026-DIAG] email=${customer.email}`);
  console.log(`[026-DIAG] document=****${customer.document.slice(-4)}`);
  console.log(`[026-DIAG] body-logged-in=${evidence.bodyLoggedIn}`);
  console.log(`[026-DIAG] logout-links=${evidence.logoutLinks}`);
  console.log(`[026-DIAG] has-login-cookie=${evidence.hasLoggedInCookie}`);
  console.log(`[026-DIAG] cart-email=${billing.email || ''}`);
  console.log(`[026-DIAG] cart-first-name=${billing.first_name || ''}`);
  console.log(`[026-DIAG] cart-postcode=${billing.postcode || ''}`);
};

const routeRegistrationCepUnavailable = async (page) => {
  await page.route('**/wp-admin/admin-ajax.php', async (route) => {
    const request = route.request();
    const body = request.postData() || '';
    if (request.method() !== 'POST' || !body.includes('action=petshop_lookup_cep')) {
      await route.continue();
      return;
    }

    await route.fulfill({
      status: 503,
      contentType: 'application/json; charset=utf-8',
      body: JSON.stringify({
        success: false,
        data: {
          message: 'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.',
        },
      }),
    });
  });
};

const registerCustomer = async (context, kind) => {
  const page = await context.newPage({ viewport: { width: 1440, height: 900 } });
  await routeCanonicalNavigation(page, baseUrl);
  await routeRegistrationCepUnavailable(page);
  const id = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  const email = `ticket026-${kind.toLowerCase()}-${id}@example.com`;
  const password = `Teste026@${kind}${id.slice(0, 4)}`;
  const isPf = kind === 'PF';
  const customer = {
    kind,
    email,
    password,
    firstName: 'Cliente',
    lastName: kind,
    phone: '(11) 98888-7777',
    personType: kind,
    document: isPf ? generateCpf(id) : generateCnpj(id),
    postcode: isPf ? '01001000' : '01310930',
    address1: isPf ? 'Praca da Se' : 'Avenida Paulista',
    number: isPf ? '123' : '987',
    address2: isPf ? 'lado impar' : 'conjunto 42',
    neighborhood: isPf ? 'Se' : 'Bela Vista',
    city: 'Sao Paulo',
    state: 'SP',
  };

  const response = await page.goto(`${baseUrl}/minha-conta/`, { waitUntil: 'networkidle', timeout: NAV_TIMEOUT });
  recordFailure(response?.status() === 200, `${kind}: /minha-conta/ HTTP ${response?.status()}`);

  const create = page.locator('.petshop-account-create').first();
  if (await create.count()) await create.click({ timeout: ACTION_TIMEOUT });

  const form = page.locator('form.register').first();
  await form.waitFor({ state: 'visible', timeout: ACTION_TIMEOUT }).catch(() => {});
  recordFailure(await visible(form), `${kind}: formulario de cadastro ausente`);

  const fillByName = async (name, value) => {
    const field = form.locator(`[name="${name}"]`).first();
    recordFailure(await field.count() === 1, `${kind}: campo ${name} ausente`);
    if (await field.count()) await field.fill(value, { timeout: ACTION_TIMEOUT });
  };

  await fillByName('email', email);
  await fillByName('password', password);
  await fillByName('password_confirm', password);
  await fillByName('billing_first_name', customer.firstName);
  await fillByName('billing_last_name', customer.lastName);
  await fillByName('billing_phone', customer.phone);
  const type = form.locator('[name="petshop_person_type"]').first();
  if (await type.count()) await type.selectOption(kind, { timeout: ACTION_TIMEOUT });
  await fillByName('petshop_document', customer.document);
  await fillByName('billing_postcode', customer.postcode);
  await fillByName('billing_address_1', customer.address1);
  await fillByName('billing_number', customer.number);
  await fillByName('billing_address_2', customer.address2);
  await fillByName('billing_neighborhood', customer.neighborhood);
  await fillByName('billing_city', customer.city);
  const state = form.locator('[name="billing_state"]').first();
  if (await state.count()) {
    const tagName = await state.evaluate((field) => field.tagName.toLowerCase()).catch(() => '');
    if (tagName === 'select') {
      await state.selectOption(customer.state, { timeout: ACTION_TIMEOUT });
    } else {
      await state.fill(customer.state, { timeout: ACTION_TIMEOUT });
    }
  }

  await Promise.all([
    page.waitForLoadState('networkidle', { timeout: NAV_TIMEOUT }).catch(() => {}),
    form.locator('button[type="submit"], button[name="register"]').first().click({ timeout: ACTION_TIMEOUT }),
  ]);

  const evidence = await authEvidence(page, context);
  const authenticated = evidence.bodyLoggedIn || evidence.logoutLinks > 0 || evidence.hasLoggedInCookie;
  recordFailure(authenticated, `${kind}: cadastro nao concluiu/autenticou`);
  if (authenticated) {
    createdEmails.push(email);
  }
  if (DIAG_AUTH) {
    await logAuthDiagnostic(page, context, customer);
  }
  await page.close();

  return customer;
};

const prepareCart = async (page) => {
  const productsResponse = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/products?per_page=100`, { timeout: API_TIMEOUT });
  const products = productsResponse.ok() ? await productsResponse.json() : [];
  const product = products.find((item) => (
    item?.type === 'simple'
    && item?.is_purchasable !== false
    && item?.is_in_stock !== false
  )) || products.find((item) => item?.type === 'simple');
  const productId = Number(product?.id || 0);
  recordFailure(productId > 0, 'checkout: nenhum produto simples disponivel para montar carrinho');
  if (productId <= 0) return;

  const cartResponse = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`, { timeout: API_TIMEOUT });
  const nonce = cartResponse.headers().nonce || '';
  const addResponse = await page.request.post(`${baseUrl}/wp-json/wc/store/v1/cart/add-item`, {
    headers: { Nonce: nonce },
    data: { id: productId, quantity: 1 },
    timeout: API_TIMEOUT,
  });
  recordFailure(addResponse.ok(), `checkout: fixture nao adicionada ao carrinho (HTTP ${addResponse.status()})`);
};

const readCartCustomer = async (page) => {
  const response = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`, { timeout: API_TIMEOUT });
  recordFailure(response.ok(), `Store API cart HTTP ${response.status()}`);
  return response.ok()
    ? withTimeout(response.json(), 'Store API cart JSON', API_TIMEOUT).catch((error) => {
      recordFailure(false, `Store API cart JSON: ${error.message}`);
      return {};
    })
    : {};
};

const logCheckoutDiagnostic = async (page, kind, cart) => {
  const billing = cart.billing_address || {};
  const shipping = cart.shipping_address || {};
  const read = async (selector) => valueOf(page.locator(selector).first());
  const firstNameField = await firstExisting(page, ['#shipping-first_name', '#billing-first_name', '#shipping_first_name', '#billing_first_name']);
  const lastNameField = await firstExisting(page, ['#shipping-last_name', '#billing-last_name', '#shipping_last_name', '#billing_last_name']);
  const phoneField = await firstExisting(page, ['#shipping-phone', '#billing-phone', '#shipping_phone', '#billing_phone', 'input[type="tel"]']);
  const numberFields = page.locator('#shipping-petshop-number, #billing-petshop-number, #billing-number, #shipping-number, #billing_number, #shipping_number, input[name="petshop/number"], input[name="billing_number"], input[name="shipping_number"]');
  const neighborhoodFields = page.locator('#shipping-petshop-neighborhood, #billing-petshop-neighborhood, #billing-neighborhood, #shipping-neighborhood, #billing_neighborhood, #shipping_neighborhood, input[name="petshop/neighborhood"], input[name="billing_neighborhood"], input[name="shipping_neighborhood"]');
  const number = numberFields.first();
  const neighborhood = neighborhoodFields.first();

  console.log(`[026-CHECKOUT] kind=${kind}`);
  console.log(`[026-CHECKOUT] cart-email=${billing.email || ''}`);
  console.log(`[026-CHECKOUT] cart-first-name=${billing.first_name || ''}`);
  console.log(`[026-CHECKOUT] cart-last-name=${billing.last_name || ''}`);
  console.log(`[026-CHECKOUT] cart-phone=${billing.phone || ''}`);
  console.log(`[026-CHECKOUT] cart-postcode=${billing.postcode || ''}`);
  console.log(`[026-CHECKOUT] cart-address1=${billing.address_1 || ''}`);
  console.log(`[026-CHECKOUT] cart-address2=${billing.address_2 || ''}`);
  console.log(`[026-CHECKOUT] cart-city=${billing.city || ''}`);
  console.log(`[026-CHECKOUT] cart-state=${billing.state || ''}`);
  console.log(`[026-CHECKOUT] cart-number=${billing['petshop/number'] || ''}`);
  console.log(`[026-CHECKOUT] cart-neighborhood=${billing['petshop/neighborhood'] || ''}`);
  console.log(`[026-CHECKOUT] shipping-postcode=${shipping.postcode || ''}`);
  console.log(`[026-CHECKOUT] shipping-address1=${shipping.address_1 || ''}`);
  console.log(`[026-CHECKOUT] shipping-city=${shipping.city || ''}`);
  console.log(`[026-CHECKOUT] shipping-state=${shipping.state || ''}`);
  console.log(`[026-CHECKOUT] shipping-number=${shipping['petshop/number'] || ''}`);
  console.log(`[026-CHECKOUT] shipping-neighborhood=${shipping['petshop/neighborhood'] || ''}`);
  console.log(`[026-CHECKOUT] dom-email=${await read('#email, #billing-email, #billing_email, input[name="email"], input[name="billing_email"], input[autocomplete="email"]')}`);
  console.log(`[026-CHECKOUT] dom-first-name=${await valueOf(firstNameField)}`);
  console.log(`[026-CHECKOUT] dom-last-name=${await valueOf(lastNameField)}`);
  console.log(`[026-CHECKOUT] dom-phone=${await valueOf(phoneField)}`);
  console.log(`[026-CHECKOUT] dom-postcode=${await read('#shipping-postcode, #billing-postcode, #shipping_postcode, #billing_postcode, input[autocomplete="postal-code"]')}`);
  console.log(`[026-CHECKOUT] dom-address1=${await read('#shipping-address_1, #billing-address_1, #shipping_address_1, #billing_address_1, input[autocomplete="address-line1"]')}`);
  console.log(`[026-CHECKOUT] dom-address2=${await read('#shipping-address_2, #billing-address_2, #shipping_address_2, #billing_address_2, input[autocomplete="address-line2"]')}`);
  console.log(`[026-CHECKOUT] dom-city=${await read('#shipping-city, #billing-city, #shipping_city, #billing_city, input[autocomplete="address-level2"]')}`);
  console.log(`[026-CHECKOUT] dom-state=${await selectValueOf(page.locator('#shipping-state, #billing-state, #shipping_state, #billing_state, select[autocomplete="address-level1"]').first())}`);
  console.log(`[026-CHECKOUT] dom-number-count=${await numberFields.count()}`);
  console.log(`[026-CHECKOUT] dom-number=${await valueOf(number)}`);
  console.log(`[026-CHECKOUT] dom-neighborhood-count=${await neighborhoodFields.count()}`);
  console.log(`[026-CHECKOUT] dom-neighborhood=${await valueOf(neighborhood)}`);
};

const confirmAuthenticatedContext = async (context, kind) => {
  const page = await context.newPage({ viewport: { width: 1440, height: 900 } });
  await routeCanonicalNavigation(page, baseUrl);
  await page.goto(`${baseUrl}/minha-conta/`, { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
  const evidence = await authEvidence(page, context);
  recordFailure(
    evidence.bodyLoggedIn || evidence.logoutLinks > 0 || evidence.hasLoggedInCookie,
    `${kind}: contexto nao permaneceu autenticado antes do checkout`,
  );
  await page.close();
};

const assertLoggedCheckout = async (context, customer, viewport) => {
  const page = await context.newPage({ viewport });
  await routeCanonicalNavigation(page, baseUrl);
  await prepareCart(page);
  const response = await page.goto(`${baseUrl}/finalizar-compra/`, { waitUntil: 'networkidle', timeout: NAV_TIMEOUT });
  recordFailure(response?.status() === 200, `${customer.kind} ${viewport.name}: checkout HTTP ${response?.status()}`);

  const cart = await readCartCustomer(page);
  if (DIAG_CHECKOUT) {
    await logCheckoutDiagnostic(page, customer.kind, cart);
  }
  const assertionFailureStart = failures.length;
  const billing = cart.billing_address || {};
  const shipping = cart.shipping_address || {};
  recordFailure(billing.email === customer.email, `${customer.kind}: e-mail salvo nao chegou ao checkout`);
  recordFailure(billing.first_name === customer.firstName, `${customer.kind}: nome salvo nao chegou ao checkout`);
  recordFailure(billing.last_name === customer.lastName, `${customer.kind}: sobrenome salvo nao chegou ao checkout`);
  recordFailure(billing.phone === customer.phone, `${customer.kind}: telefone salvo nao chegou ao checkout`);
  recordFailure(digitsOnly(billing.postcode) === customer.postcode, `${customer.kind}: CEP salvo nao chegou ao checkout`);
  recordFailure(billing.address_1 === customer.address1, `${customer.kind}: rua salva nao chegou ao checkout`);
  recordFailure(billing.address_2 === customer.address2, `${customer.kind}: complemento salvo nao chegou ao checkout`);
  recordFailure(billing.city === customer.city, `${customer.kind}: cidade salva nao chegou ao checkout`);
  recordFailure(billing.state === customer.state, `${customer.kind}: estado salvo nao chegou ao checkout`);
  recordFailure(digitsOnly(shipping.postcode) === customer.postcode, `${customer.kind}: shipping nao herdou CEP de billing`);
  recordFailure(shipping.address_1 === customer.address1, `${customer.kind}: shipping nao herdou rua de billing`);

  const firstNameField = await firstExisting(page, ['#shipping-first_name', '#billing-first_name', '#shipping_first_name', '#billing_first_name']);
  recordFailure(await valueOf(firstNameField) === customer.firstName, `${customer.kind}: nome preenchido nao apareceu no Checkout Block`);
  const lastNameField = await firstExisting(page, ['#shipping-last_name', '#billing-last_name', '#shipping_last_name', '#billing_last_name']);
  recordFailure(await valueOf(lastNameField) === customer.lastName, `${customer.kind}: sobrenome preenchido nao apareceu no Checkout Block`);
  const phoneField = await firstExisting(page, ['#shipping-phone', '#billing-phone', '#shipping_phone', '#billing_phone', 'input[type="tel"]']);
  recordFailure(await valueOf(phoneField) === customer.phone, `${customer.kind}: telefone preenchido nao apareceu no Checkout Block`);

  const personType = page.locator('#contact-petshop-person-type, [id$="petshop-person-type"], select[name="petshop/person-type"]').first();
  recordFailure(await personType.count() > 0, `${customer.kind}: campo PF/PJ nao apareceu no checkout`);
  if (await personType.count()) recordFailure(await selectValueOf(personType) === customer.kind, `${customer.kind}: PF/PJ salvo nao apareceu no checkout`);
  const documentField = page.locator('#contact-petshop-document, [id$="petshop-document"], input[name="petshop/document"]').first();
  recordFailure(await documentField.count() > 0, `${customer.kind}: campo CPF/CNPJ nao apareceu no checkout`);
  if (await documentField.count()) {
    recordFailure((await valueOf(documentField)).replace(/\D/g, '').includes(customer.document.slice(0, 8)), `${customer.kind}: CPF/CNPJ salvo nao apareceu no checkout`);
  }
  const number = page.locator('#shipping-petshop-number, #billing-petshop-number, #billing-number, #shipping-number, #billing_number, #shipping_number, input[name="petshop/number"], input[name="billing_number"], input[name="shipping_number"]').first();
  recordFailure(await number.count() > 0, `${customer.kind}: campo numero nao apareceu no checkout`);
  if (await number.count()) recordFailure(await valueOf(number) === customer.number, `${customer.kind}: numero salvo nao apareceu no checkout`);
  const neighborhood = page.locator('#shipping-petshop-neighborhood, #billing-petshop-neighborhood, #billing-neighborhood, #shipping-neighborhood, #billing_neighborhood, #shipping_neighborhood, input[name="petshop/neighborhood"], input[name="billing_neighborhood"], input[name="shipping_neighborhood"]').first();
  recordFailure(await neighborhood.count() > 0, `${customer.kind}: campo bairro nao apareceu no checkout`);
  if (await neighborhood.count()) recordFailure(await valueOf(neighborhood) === customer.neighborhood, `${customer.kind}: bairro salvo nao apareceu no checkout`);

  if (DIAG_CHECKOUT) {
    for (const failure of failures.slice(assertionFailureStart)) {
      console.log(`[026-CHECKOUT-FAIL] ${failure}`);
    }
  }

  await page.screenshot({ path: path.join(evidenceDir, `${viewport.name}-${customer.kind.toLowerCase()}-checkout-prefill.png`), fullPage: true });
  await page.close();
};

const routeViaCep = async (page) => {
  await page.route('**/wp-admin/admin-ajax.php', async (route) => {
    const request = route.request();
    const body = request.postData() || '';
    if (request.method() !== 'POST' || !body.includes('action=petshop_lookup_cep')) {
      await route.continue();
      return;
    }

    const cep = new URLSearchParams(body).get('cep') || '';
    const fixtures = {
      '01001000': { logradouro: 'Praca da Se', bairro: 'Se', localidade: 'Sao Paulo', uf: 'SP', complemento: 'lado impar' },
      '01310930': { logradouro: 'Avenida Paulista', bairro: 'Bela Vista', localidade: 'Sao Paulo', uf: 'SP', complemento: '' },
      '04004000': { logradouro: 'Rua Vergueiro', bairro: 'Vila Mariana', localidade: 'Sao Paulo', uf: 'SP', complemento: '' },
    };

    if (cep === '00000000') {
      await route.fulfill({
        status: 400,
        contentType: 'application/json; charset=utf-8',
        body: JSON.stringify({ success: false, data: { message: 'CEP não encontrado. Confira o número informado ou preencha o endereço manualmente.' } }),
      });
      return;
    }

    if (cep === '88888888') {
      await route.abort('failed');
      return;
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json; charset=utf-8',
      body: JSON.stringify({ success: true, data: fixtures[cep] || fixtures['01001000'] }),
    });
  });
};

const assertViaCep = async (viaCepBrowser = browser) => {
  const context = await viaCepBrowser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const viaCepFailureStart = failures.length;
  try {
    await routeCanonicalNavigation(page, baseUrl);
    await routeViaCep(page);
    const updateResponses = [];
    const readUpdatePayload = (request) => {
      const raw = request.postData() || '{}';
      try {
        return JSON.parse(raw);
      } catch {
        return Object.fromEntries(new URLSearchParams(raw));
      }
    };
    const logUpdateAddress = (prefix, address = {}) => {
      console.log(`[026-VIACEP-REQUEST] ${prefix}-postcode=${address.postcode || ''}`);
      console.log(`[026-VIACEP-REQUEST] ${prefix}-address1=${address.address_1 || ''}`);
      console.log(`[026-VIACEP-REQUEST] ${prefix}-city=${address.city || ''}`);
      console.log(`[026-VIACEP-REQUEST] ${prefix}-state=${address.state || ''}`);
      console.log(`[026-VIACEP-REQUEST] ${prefix}-number=${address['petshop/number'] || ''}`);
      console.log(`[026-VIACEP-REQUEST] ${prefix}-neighborhood=${address['petshop/neighborhood'] || ''}`);
    };
    if (DIAG_VIACEP) {
      page.on('request', (request) => {
        if (!request.url().includes('/wc/store/v1/cart/update-customer')) return;
        const payload = readUpdatePayload(request);
        console.log(`[026-VIACEP-REQUEST] method=${request.method()}`);
        console.log(`[026-VIACEP-REQUEST] has-billing-address=${Boolean(payload.billing_address)}`);
        console.log(`[026-VIACEP-REQUEST] has-shipping-address=${Boolean(payload.shipping_address)}`);
        logUpdateAddress('billing', payload.billing_address || {});
        logUpdateAddress('shipping', payload.shipping_address || {});
        console.log(`[026-VIACEP-REQUEST] has-top-level-additional-fields=${Boolean(payload.additional_fields)}`);
      });
    }
    page.on('response', async (response) => {
      if (response.url().includes('/wc/store/v1/cart/update-customer')) {
        updateResponses.push(response);
        if (DIAG_VIACEP) {
          console.log(`[026-VIACEP-RESPONSE] status=${response.status()}`);
          console.log(`[026-VIACEP-RESPONSE] ok=${response.ok()}`);
        }
      }
    });

    const waitForUpdateCustomer = (cep) => page.waitForResponse(
      (response) => {
        const body = response.request().postData() || '';
        return response.url().includes('/wc/store/v1/cart/update-customer')
          && response.ok()
          && body.includes(cep);
      },
      { timeout: API_TIMEOUT },
    ).catch(() => null);
    const waitForLookup = (cep) => page.waitForResponse((response) => {
      const body = response.request().postData() || '';
      return response.url().includes('/wp-admin/admin-ajax.php')
        && body.includes('action=petshop_lookup_cep')
        && body.includes(`cep=${cep}`);
    }, { timeout: API_TIMEOUT }).catch(() => null);
    const triggerCep = async (cep, waitForStoreApi = true) => {
      const lookupPromise = waitForLookup(cep);
      const updatePromise = waitForStoreApi ? waitForUpdateCustomer(cep) : Promise.resolve(null);
      await postcode.fill(cep, { timeout: ACTION_TIMEOUT });
      await postcode.blur({ timeout: ACTION_TIMEOUT });
      return {
        lookup: await lookupPromise,
        update: await updatePromise,
      };
    };
    const boundedText = (locator, label) => withTimeout(text(locator), label, ACTION_TIMEOUT).catch(() => '');
    const boundedValue = (locator, label, read = valueOf) => withTimeout(read(locator), label, ACTION_TIMEOUT).catch(() => '');
    const waitForValue = async (locator, expected, label, read = valueOf) => {
      const matched = await withTimeout((async () => {
        await locator.waitFor({ state: 'visible', timeout: ACTION_TIMEOUT });
        const handle = await locator.elementHandle({ timeout: ACTION_TIMEOUT });
        if (!handle) return false;
        return await page.waitForFunction(
          ([field, wanted]) => field.value === wanted,
          [handle, expected],
          { timeout: ACTION_TIMEOUT },
        ).then(() => true).catch(async () => await read(locator) === expected);
      })(), label, ACTION_TIMEOUT).catch(() => false);
      recordFailure(matched, label);
    };
    const postcodeSelectors = ['#shipping-postcode', '#billing-postcode', '#shipping_postcode', '#billing_postcode', 'input[autocomplete="postal-code"]'];
    const locateViaCepFields = async () => ({
      postcode: await firstVisible(page, postcodeSelectors),
      number: await firstVisible(page, ['#shipping-petshop-number', '#billing-petshop-number', '#shipping-number', '#billing-number', '#shipping_number', '#billing_number', 'input[name="petshop/number"]', 'input[name="shipping_number"]', 'input[name="billing_number"]']),
      address: await firstVisible(page, ['#shipping-address_1', '#billing-address_1', '#shipping_address_1', '#billing_address_1', 'input[autocomplete="address-line1"]']),
      complement: await firstVisible(page, ['#shipping-address_2', '#billing-address_2', '#shipping_address_2', '#billing_address_2', 'input[autocomplete="address-line2"]']),
      neighborhood: await firstVisible(page, ['#shipping-petshop-neighborhood', '#billing-petshop-neighborhood', '#shipping-neighborhood', '#billing-neighborhood', '#shipping_neighborhood', '#billing_neighborhood', 'input[name="petshop/neighborhood"]']),
      city: await firstVisible(page, ['#shipping-city', '#billing-city', '#shipping_city', '#billing_city', 'input[autocomplete="address-level2"]']),
      state: await firstVisible(page, ['#shipping-state', '#billing-state', '#shipping_state', '#billing_state', 'select[autocomplete="address-level1"]']),
    });
    const locateViaCepFieldsExisting = async () => ({
      postcode: await firstExisting(page, postcodeSelectors),
      number: await firstExisting(page, ['#shipping-petshop-number', '#billing-petshop-number', '#shipping-number', '#billing-number', '#shipping_number', '#billing_number', 'input[name="petshop/number"]', 'input[name="shipping_number"]', 'input[name="billing_number"]']),
      address: await firstExisting(page, ['#shipping-address_1', '#billing-address_1', '#shipping_address_1', '#billing_address_1', 'input[autocomplete="address-line1"]']),
      complement: await firstExisting(page, ['#shipping-address_2', '#billing-address_2', '#shipping_address_2', '#billing_address_2', 'input[autocomplete="address-line2"]']),
      neighborhood: await firstExisting(page, ['#shipping-petshop-neighborhood', '#billing-petshop-neighborhood', '#shipping-neighborhood', '#billing-neighborhood', '#shipping_neighborhood', '#billing_neighborhood', 'input[name="petshop/neighborhood"]']),
      city: await firstExisting(page, ['#shipping-city', '#billing-city', '#shipping_city', '#billing_city', 'input[autocomplete="address-level2"]']),
      state: await firstExisting(page, ['#shipping-state', '#billing-state', '#shipping_state', '#billing_state', 'select[autocomplete="address-level1"]']),
    });
    const hasAddressData = (addressData = {}) => Boolean(
      addressData.postcode
        || addressData.address_1
        || addressData.city,
    );
    const selectAcceptedAddress = (cart) => {
      const shippingAddress = cart.shipping_address || {};
      const billingAddress = cart.billing_address || {};
      if (hasAddressData(shippingAddress)) return shippingAddress;
      if (hasAddressData(billingAddress)) return billingAddress;
      return {};
    };
    const logAddressState = (prefix, addressData) => {
      console.log(`[026-VIACEP-${prefix}] postcode=${addressData.postcode || ''}`);
      console.log(`[026-VIACEP-${prefix}] address1=${addressData.address_1 || ''}`);
      console.log(`[026-VIACEP-${prefix}] address2=${addressData.address_2 || ''}`);
      console.log(`[026-VIACEP-${prefix}] neighborhood=${addressData['petshop/neighborhood'] || ''}`);
      console.log(`[026-VIACEP-${prefix}] city=${addressData.city || ''}`);
      console.log(`[026-VIACEP-${prefix}] state=${addressData.state || ''}`);
      console.log(`[026-VIACEP-${prefix}] number=${addressData['petshop/number'] || ''}`);
    };
    const logDomState = async (prefix, fields) => {
      console.log(`[026-VIACEP-${prefix}] postcode=${await boundedValue(fields.postcode, `${prefix}: leitura CEP excedeu timeout`)}`);
      console.log(`[026-VIACEP-${prefix}] address1=${await boundedValue(fields.address, `${prefix}: leitura rua excedeu timeout`)}`);
      console.log(`[026-VIACEP-${prefix}] address2=${await boundedValue(fields.complement, `${prefix}: leitura complemento excedeu timeout`)}`);
      console.log(`[026-VIACEP-${prefix}] neighborhood=${await boundedValue(fields.neighborhood, `${prefix}: leitura bairro excedeu timeout`)}`);
      console.log(`[026-VIACEP-${prefix}] city=${await boundedValue(fields.city, `${prefix}: leitura cidade excedeu timeout`)}`);
      console.log(`[026-VIACEP-${prefix}] state=${await boundedValue(fields.state, `${prefix}: leitura UF excedeu timeout`, selectValueOf)}`);
      console.log(`[026-VIACEP-${prefix}] number=${await boundedValue(fields.number, `${prefix}: leitura numero excedeu timeout`)}`);
    };
    const logAddressSummary = async () => {
      const summary = await page.evaluate(() => {
        const visible = (element) => {
          const style = window.getComputedStyle(element);
          return style.visibility !== 'hidden'
            && style.display !== 'none'
            && element.getClientRects().length > 0;
        };
        const candidates = Array.from(document.querySelectorAll('[class*="address"], [class*="shipping"], [class*="billing"]'))
          .filter((element) => visible(element))
          .map((element) => (element.textContent || '').replace(/\s+/g, ' ').trim())
          .filter((value) => /Praca da Se|Avenida Paulista|Sao Paulo|01001000|01310930/i.test(value));
        return candidates[0] || '';
      }).catch(() => '');
      console.log(`[026-VIACEP] address-summary=${summary}`);
    };
    const ensurePostcodeVisibleForEdit = async () => {
      fields = await locateViaCepFieldsExisting();
      ({ postcode, number, address, complement, neighborhood, city, state } = fields);
      const exists = await postcode.count() > 0;
      const isVisible = exists && await postcode.first().isVisible().catch(() => false);
      if (DIAG_VIACEP) {
        console.log(`[026-VIACEP] postcode-exists=${exists}`);
        console.log(`[026-VIACEP] postcode-visible=${isVisible}`);
      }
      if (exists && isVisible) return;

      const editButton = page.getByRole('button', { name: /editar|edit/i }).first();
      if (await editButton.count()) {
        await editButton.click({ timeout: ACTION_TIMEOUT });
        await page.locator(postcodeSelectors.join(', ')).first().waitFor({ state: 'visible', timeout: ACTION_TIMEOUT });
      }

      fields = await locateViaCepFields();
      ({ postcode, number, address, complement, neighborhood, city, state } = fields);
    };

    await prepareCart(page);
    log('ViaCEP: carrinho preparado');
    await page.goto(`${baseUrl}/finalizar-compra/`, { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
    await page.locator(postcodeSelectors.join(', ')).first().waitFor({ state: 'visible', timeout: ACTION_TIMEOUT });
    log('ViaCEP: checkout carregado');
    let fields = await locateViaCepFields();
    let { postcode, number, address, complement, neighborhood, city, state } = fields;
    log('ViaCEP: campos encontrados');

    recordFailure(await number.count() > 0, 'ViaCEP: campo numero ausente no checkout');
    recordFailure(await neighborhood.count() > 0, 'ViaCEP: campo bairro ausente no checkout');
    if (DIAG_VIACEP) {
      console.log(`[026-VIACEP] initial-postcode=${await boundedValue(postcode, 'ViaCEP: leitura CEP inicial excedeu timeout')}`);
      console.log(`[026-VIACEP] initial-number=${await boundedValue(number, 'ViaCEP: leitura numero inicial excedeu timeout')}`);
      console.log(`[026-VIACEP] initial-address1=${await boundedValue(address, 'ViaCEP: leitura rua inicial excedeu timeout')}`);
      console.log(`[026-VIACEP] initial-neighborhood=${await boundedValue(neighborhood, 'ViaCEP: leitura bairro inicial excedeu timeout')}`);
      console.log(`[026-VIACEP] initial-city=${await boundedValue(city, 'ViaCEP: leitura cidade inicial excedeu timeout')}`);
      console.log(`[026-VIACEP] initial-state=${await boundedValue(state, 'ViaCEP: leitura UF inicial excedeu timeout', selectValueOf)}`);
      console.log(`[026-VIACEP] initial-address2=${await boundedValue(complement, 'ViaCEP: leitura complemento inicial excedeu timeout')}`);
    }
    if (await number.count()) await number.fill('555', { timeout: ACTION_TIMEOUT });
    const firstCep = await triggerCep('01001000');
    log('ViaCEP: primeiro CEP disparado');
    recordFailure(firstCep.update !== null, 'ViaCEP: Store API update-customer nao retornou HTTP de sucesso');
    log('ViaCEP: resposta Store API recebida');
    const cartAfterFirstCep = await readCartCustomer(page);
    log('ViaCEP: estado Store API lido');
    const acceptedAddress = selectAcceptedAddress(cartAfterFirstCep);
    if (DIAG_VIACEP) {
      logAddressState('CART1-BILLING', cartAfterFirstCep.billing_address || {});
      logAddressState('CART1-SHIPPING', cartAfterFirstCep.shipping_address || {});
    }
    recordFailure(digitsOnly(acceptedAddress.postcode) === '01001000', 'Store API nao persistiu postcode do primeiro CEP');
    recordFailure(acceptedAddress.city === 'Sao Paulo', 'Store API nao persistiu cidade do primeiro CEP');
    recordFailure(acceptedAddress.state === 'SP', 'Store API nao persistiu estado do primeiro CEP');
    recordFailure(acceptedAddress['petshop/number'] === '555', 'Store API nao persistiu numero adicional');
    recordFailure(acceptedAddress['petshop/neighborhood'] === 'Se', 'Store API nao persistiu bairro adicional');
    log('ViaCEP: validando campos primeiro CEP');
    await Promise.all([
      waitForValue(address, 'Praca da Se', 'ViaCEP valido nao preencheu rua'),
      waitForValue(neighborhood, 'Se', 'ViaCEP valido nao preencheu bairro'),
      waitForValue(city, 'Sao Paulo', 'ViaCEP valido nao preencheu cidade'),
      waitForValue(state, 'SP', 'ViaCEP valido nao preencheu UF', selectValueOf),
      waitForValue(complement, 'lado impar', 'ViaCEP valido nao preencheu complemento'),
    ]);
    log('ViaCEP: campos primeiro CEP finalizados');
    if (DIAG_VIACEP) {
      fields = await locateViaCepFieldsExisting();
      ({ postcode, number, address, complement, neighborhood, city, state } = fields);
      await logAddressSummary();
      console.log(`[026-VIACEP] postcode-exists=${await postcode.count() > 0}`);
      console.log(`[026-VIACEP] postcode-visible=${await postcode.first().isVisible().catch(() => false)}`);
      await logDomState('DOM1', fields);
    }
    if (await number.count()) recordFailure(await boundedValue(number, 'ViaCEP: leitura do numero excedeu timeout') === '555', 'ViaCEP alterou numero');
    log('ViaCEP: primeiro CEP validado');

    await ensurePostcodeVisibleForEdit();
    await triggerCep('01310930');
    fields = await locateViaCepFieldsExisting();
    ({ postcode, number, address, complement, neighborhood, city, state } = fields);
    const cartAfterSecondCep = await readCartCustomer(page);
    const acceptedSecondAddress = selectAcceptedAddress(cartAfterSecondCep);
    if (DIAG_VIACEP) {
      logAddressState('CART2-BILLING', cartAfterSecondCep.billing_address || {});
      logAddressState('CART2-SHIPPING', cartAfterSecondCep.shipping_address || {});
    }
    await waitForValue(address, 'Avenida Paulista', 'Segundo CEP nao atualizou rua');
    await waitForValue(neighborhood, 'Bela Vista', 'Segundo CEP nao atualizou bairro');
    await waitForValue(city, 'Sao Paulo', 'Segundo CEP nao atualizou cidade');
    await waitForValue(state, 'SP', 'Segundo CEP nao atualizou UF', selectValueOf);
    await waitForValue(complement, '', 'Segundo CEP sem complemento manteve complemento automatico antigo');
    if (DIAG_VIACEP) {
      await logDomState('DOM2', fields);
    }
    if (await number.count()) recordFailure(await boundedValue(number, 'ViaCEP: leitura do numero no segundo CEP excedeu timeout') === '555', 'Segundo CEP alterou numero');
    log('ViaCEP: segundo CEP validado');

    await complement.fill('Complemento manual', { timeout: ACTION_TIMEOUT });
    await triggerCep('04004000');
    await waitForValue(complement, 'Complemento manual', 'CEP sem complemento apagou complemento manual do cliente');
    log('ViaCEP: complemento manual validado');

    await triggerCep('00000000', false);
    await page.locator('.petshop-cep-message').last().waitFor({ state: 'visible', timeout: ACTION_TIMEOUT }).catch(() => {});
    recordFailure(/CEP não encontrado|preencha o endereço manualmente/i.test(await boundedText(page.locator('.petshop-cep-message').last(), 'ViaCEP: leitura da mensagem de CEP inexistente excedeu timeout')), 'CEP inexistente sem mensagem pt-BR');
    log('ViaCEP: CEP inexistente validado');

    await address.fill('', { timeout: ACTION_TIMEOUT });
    await neighborhood.fill('', { timeout: ACTION_TIMEOUT });
    await city.fill('', { timeout: ACTION_TIMEOUT });
    await triggerCep('88888888', false);
    await page.locator('.petshop-cep-message').last().waitFor({ state: 'visible', timeout: ACTION_TIMEOUT }).catch(() => {});
    recordFailure(/Não foi possível consultar o CEP agora|Preencha o endereço manualmente/i.test(await boundedText(page.locator('.petshop-cep-message').last(), 'ViaCEP: leitura da mensagem de indisponibilidade excedeu timeout')), 'ViaCEP indisponivel sem mensagem pt-BR');
    await address.fill('Rua Manual', { timeout: ACTION_TIMEOUT });
    await neighborhood.fill('Bairro Manual', { timeout: ACTION_TIMEOUT });
    await city.fill('Cidade Manual', { timeout: ACTION_TIMEOUT });
    recordFailure(await boundedValue(address, 'ViaCEP: leitura da rua manual excedeu timeout') === 'Rua Manual', 'Campo rua nao ficou editavel apos falha ViaCEP');
    recordFailure(updateResponses.some((response) => response.ok()), 'Nenhuma resposta aceita da Store API foi capturada');
    log('ViaCEP: indisponibilidade validada');
    await page.screenshot({ path: path.join(evidenceDir, 'desktop-1440-viacep-store-api.png'), fullPage: true, timeout: ACTION_TIMEOUT });
    log('ViaCEP: screenshot OK');
    if (DIAG_VIACEP) {
      for (const failure of failures.slice(viaCepFailureStart)) {
        console.log(`[026-VIACEP-FAIL] ${failure}`);
      }
    }
  } finally {
    await context.close().catch(() => {});
  }
};

const adminCleanup = async () => {
  if (createdEmails.length === 0) {
    log('cleanup: OK');
    return true;
  }
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await routeCanonicalNavigation(page, baseUrl);
  try {
    await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
    await page.locator('#user_login').fill(process.env.PETSHOP_ADMIN_USER || 'admin', { timeout: ACTION_TIMEOUT });
    await page.locator('#user_pass').fill(process.env.PETSHOP_ADMIN_PASSWORD || 'admin', { timeout: ACTION_TIMEOUT });
    await page.locator('#wp-submit').click({ timeout: ACTION_TIMEOUT, noWaitAfter: true });
    await Promise.race([
      page.waitForURL(/\/wp-admin\/?/, { timeout: NAV_TIMEOUT }).catch(() => null),
      page.locator('#wpadminbar').waitFor({ state: 'visible', timeout: NAV_TIMEOUT }).catch(() => null),
    ]);
    const isAdmin = await page.locator('#wpadminbar').count();
    if (!isAdmin) {
      log('cleanup: AVISO - fixtures temporarias nao foram removidas');
      return false;
    }
    await page.goto(`${baseUrl}/wp-admin/users.php`, { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT });
    let cleanupOk = true;
    for (const email of createdEmails) {
      await withTimeout(
        page.evaluate(async (targetEmail) => {
          const users = await window.wp.apiFetch({ path: `/wp/v2/users?search=${encodeURIComponent(targetEmail)}&context=edit` });
          await Promise.all(users.map((user) => window.wp.apiFetch({ path: `/wp/v2/users/${user.id}?force=true&reassign=1`, method: 'DELETE' })));
        }, email),
        `cleanup ${email}`,
        CLEANUP_TIMEOUT,
      ).catch((error) => {
        cleanupOk = false;
        log(`cleanup: AVISO - falha ao remover ${email}: ${error.message}`);
      });
    }
    if (cleanupOk) {
      log('cleanup: OK');
    }
    return cleanupOk;
  } catch (error) {
    log(`cleanup: AVISO - fixtures temporarias nao foram removidas: ${error instanceof Error ? error.message : String(error)}`);
    return false;
  } finally {
    await page.close().catch(() => {});
  }
};

if (DIAG_AUTH) {
  try {
    for (const kind of ['PF', 'PJ']) {
      const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
      await step(`${kind} cadastro diagnostico`, () => registerCustomer(context, kind));
      await context.close();
    }
  } finally {
    await browser.close();
  }

  process.exit(failures.length > 0 ? 1 : 0);
}

if (DIAG_CHECKOUT) {
  try {
    const pfContext = await browser.newContext();
    const pf = await step('PF cadastro diagnostico checkout', () => registerCustomer(pfContext, 'PF'));
    await step('PF autenticacao diagnostico checkout', () => confirmAuthenticatedContext(pfContext, 'PF'));
    await step('PF checkout desktop diagnostico', () => assertLoggedCheckout(pfContext, pf, { name: 'desktop-1440', width: 1440, height: 900 }));
    await pfContext.close();

    const pjContext = await browser.newContext();
    const pj = await step('PJ cadastro diagnostico checkout', () => registerCustomer(pjContext, 'PJ'));
    await step('PJ autenticacao diagnostico checkout', () => confirmAuthenticatedContext(pjContext, 'PJ'));
    await step('PJ checkout mobile diagnostico', () => assertLoggedCheckout(pjContext, pj, { name: 'mobile-390', width: 390, height: 844 }));
    await pjContext.close();
  } finally {
    await browser.close();
  }

  process.exit(failures.length > 0 ? 1 : 0);
}

if (DIAG_VIACEP) {
  try {
    await step('ViaCEP diagnostico', () => assertViaCep());
  } finally {
    await browser.close();
  }

  process.exit(failures.length > 0 ? 1 : 0);
}

try {
  const pfContext = await browser.newContext();
  const pf = await step('PF cadastro', () => registerCustomer(pfContext, 'PF'));
  await step('PF checkout desktop', () => assertLoggedCheckout(pfContext, pf, { name: 'desktop-1440', width: 1440, height: 900 }));
  await pfContext.close();

  const pjContext = await browser.newContext();
  const pj = await step('PJ cadastro', () => registerCustomer(pjContext, 'PJ'));
  await step('PJ checkout mobile', () => assertLoggedCheckout(pjContext, pj, { name: 'mobile-390', width: 390, height: 844 }));
  await pjContext.close();

  await step('visitante', async () => {
    const visitorContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const visitor = await visitorContext.newPage();
    await routeCanonicalNavigation(visitor, baseUrl);
    await prepareCart(visitor);
    await visitor.goto(`${baseUrl}/finalizar-compra/`, { waitUntil: 'networkidle', timeout: NAV_TIMEOUT });
    const cart = await readCartCustomer(visitor);
    recordFailure((cart.billing_address?.email || '') === '', 'Visitante recebeu e-mail de usuario anterior');
    recordFailure(digitsOnly(cart.billing_address?.postcode || '') !== pf.postcode && digitsOnly(cart.billing_address?.postcode || '') !== pj.postcode, 'Visitante recebeu CEP de conta anterior');
    await visitor.screenshot({ path: path.join(evidenceDir, 'mobile-390-visitor-empty.png'), fullPage: true });
    await visitorContext.close();
  });

  await step('ViaCEP', () => runViaCepSubprocess());
} finally {
  printFailuresBeforeCleanup();
  await withTimeout(adminCleanup(), 'adminCleanup', CLEANUP_TIMEOUT * Math.max(1, createdEmails.length + 2)).catch((error) => {
    log(`cleanup: AVISO - fixtures temporarias nao foram removidas: ${error instanceof Error ? error.message : String(error)}`);
  });
  await browser.close();
}

if (failures.length > 0) {
  log('resultado final: FALHOU');
  console.error(failures.join('\n'));
  process.exit(1);
}

log('resultado final: OK');
console.log('Validacao browser do Plano 026 aprovada.');
