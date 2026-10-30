import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('043-payment-discount');
const failures = [];
const keepFixture = process.env.PETSHOP_043_KEEP_FIXTURE === '1';

const log = (message) => console.log(`[043-browser] ${message}`);
const fail = (message) => {
  failures.push(message);
  console.error(`[043-browser] FAIL ${message}`);
};

const runWp = (script) => {
  const result = spawnSync(
    'docker',
    ['compose', '--profile', 'tools', 'run', '--rm', '--no-deps', 'cli', 'wp', 'eval-file', `/var/www/html/scripts/${script}`],
    { encoding: 'utf8', cwd: path.resolve('.'), maxBuffer: 10 * 1024 * 1024 },
  );
  if ((result.status ?? 1) !== 0) {
    throw new Error(`${script} falhou: ${result.stderr || result.stdout || result.status}`);
  }
  return (result.stdout || '').trim();
};

const parseFixture = (stdout) => {
  const lines = stdout.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
  for (let index = lines.length - 1; index >= 0; index -= 1) {
    try {
      const parsed = JSON.parse(lines[index]);
      if (parsed?.products) return parsed;
    } catch {
      // keep scanning
    }
  }
  throw new Error('Fixture 043 sem JSON válido na saída do setup.');
};

const parseMoney = (text) => {
  const match = String(text || '').replace(/\u00a0/g, ' ').match(/(\d{1,3}(?:\.\d{3})*,\d{2}|\d+[.,]\d{2}|\d+)/);
  if (!match) return null;
  const raw = match[1];
  const normalized = raw.includes(',')
    ? raw.replace(/\./g, '').replace(',', '.')
    : raw;
  const value = Number.parseFloat(normalized);
  return Number.isFinite(value) ? value : null;
};

const closeEnough = (actual, expected) => Number.isFinite(actual) && Math.abs(actual - expected) < 0.02;

const readPdpDiscount = async (page) => page.evaluate(() => {
  const clean = (value) => (value || '').replace(/\s+/g, ' ').trim();
  const hosts = [
    ...document.querySelectorAll('.woocommerce-variation-price .petshop-payment-discount'),
    ...document.querySelectorAll('.summary .petshop-payment-discount, .entry-summary .petshop-payment-discount'),
    ...document.querySelectorAll('.petshop-payment-discount[data-petshop-payment-discount-live]'),
  ];
  const visible = hosts.filter((node) => {
    if (node.classList.contains('is-retired')) return false;
    const style = window.getComputedStyle(node);
    return style.display !== 'none' && style.visibility !== 'hidden' && !!(node.offsetParent || node.getClientRects().length);
  });
  const fromSlots = [...document.querySelectorAll('[data-petshop-payment-discount-from]:not(.is-retired)')]
    .filter((node) => !!(node.offsetParent || node.getClientRects().length))
    .map((node) => clean(node.innerText));
  const texts = visible.map((node) => clean(node.innerText));
  const quantityTotal = document.querySelector('[data-petshop-quantity-total]');
  if (quantityTotal && !quantityTotal.hidden) {
    const amount = clean(quantityTotal.querySelector('[data-petshop-quantity-total-value]')?.innerText);
    const label = clean(quantityTotal.querySelector('[data-petshop-quantity-total-payment]')?.innerText);
    if (amount && label) {
      texts.push(`${amount} ${label}`);
    }
  }
  return {
    texts,
    fromTexts: fromSlots,
    summaryPrice: clean(document.querySelector('.entry-summary > .price, .summary > .price, .woocommerce-variation-price .price')?.innerText),
  };
});

const selectOption = async (page, label) => {
  const select = page.locator('form.variations_form select').first();
  await select.waitFor({ state: 'visible', timeout: 15000 });
  await select.selectOption({ label });
  await page.waitForFunction((expected) => {
    const selected = document.querySelector('form.variations_form select')?.selectedOptions?.[0]?.textContent?.trim();
    const id = document.querySelector('[name="variation_id"]')?.value || '';
    return selected === expected && id !== '' && id !== '0';
  }, label, { timeout: 10000 });
  await page.waitForTimeout(400);
};

const assertHasDiscount = async (page, expectedAmount, label) => {
  const state = await readPdpDiscount(page);
  const amounts = state.texts.map((text) => parseMoney(text)).filter((value) => value !== null);
  if (!amounts.some((amount) => closeEnough(amount, expectedAmount))) {
    fail(`${label}: esperado Pix ~${expectedAmount.toFixed(2)}, veio ${JSON.stringify(state)}`);
  }
  if (!state.texts.some((text) => /no Pix/i.test(text))) {
    fail(`${label}: rótulo “no Pix” ausente em ${JSON.stringify(state.texts)}`);
  }
};

const assertNoDiscount = async (page, label) => {
  const state = await readPdpDiscount(page);
  if (state.texts.length > 0) {
    fail(`${label}: não deveria mostrar Pix, veio ${JSON.stringify(state.texts)}`);
  }
};

const clearCart = async (page) => {
  const cart = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`);
  const nonce = cart.headers().nonce || '';
  const body = cart.ok() ? await cart.json() : { items: [] };
  for (const item of body.items || []) {
    await page.request.post(`${baseUrl}/wp-json/wc/store/v1/cart/remove-item`, {
      headers: { Nonce: nonce },
      data: { key: item.key },
    });
  }
};

const addSimpleToCart = async (page, productId) => {
  const cart = await page.request.get(`${baseUrl}/wp-json/wc/store/v1/cart`);
  const nonce = cart.headers().nonce || '';
  const response = await page.request.post(`${baseUrl}/wp-json/wc/store/v1/cart/add-item`, {
    headers: { Nonce: nonce },
    data: { id: productId, quantity: 1 },
  });
  if (!response.ok()) {
    throw new Error(`add-item ${productId} falhou: ${response.status()} ${await response.text()}`);
  }
};

const addVariationToCart = async (page, variationId) => {
  await addSimpleToCart(page, variationId);
};

const readCheckoutTotals = async (page) => page.evaluate(() => {
  const clean = (value) => (value || '').replace(/\s+/g, ' ').trim();
  const parse = (text) => {
    const match = String(text || '').replace(/\u00a0/g, ' ').match(/(\d{1,3}(?:\.\d{3})*,\d{2}|\d+[.,]\d{2}|\d+)/);
    if (!match) return null;
    const raw = match[1];
    const normalized = raw.includes(',') ? raw.replace(/\./g, '').replace(',', '.') : raw;
    const value = Number.parseFloat(normalized);
    return Number.isFinite(value) ? value : null;
  };
  const footer = clean(document.querySelector(
    '.wc-block-components-totals-footer-item .wc-block-components-totals-item__value, .wc-block-components-totals-footer-item-tax-value, .order-total .amount',
  )?.innerText);
  const summary = clean(document.querySelector(
    '.wp-block-woocommerce-checkout-order-summary-block, .wc-block-components-order-summary',
  )?.innerText);
  const itemPrices = [...document.querySelectorAll(
    '.wc-block-components-order-summary-item__total-price, .wc-block-components-product-price__value, .wc-block-components-order-summary-item .wc-block-components-product-price',
  )].map((node) => parse(clean(node.innerText))).filter((value) => value !== null);
  const subtotalNode = [...document.querySelectorAll('.wc-block-components-totals-item')]
    .find((node) => /subtotal|sub-total|produtos/i.test(node.innerText));
  const subtotal = subtotalNode
    ? parse(clean(subtotalNode.querySelector('.wc-block-components-totals-item__value')?.innerText || subtotalNode.innerText))
    : null;
  const methods = [...document.querySelectorAll(
    'input[name="radio-control-wc-payment-method-options"], input[name="payment_method"]',
  )].map((input) => ({
    value: input.value,
    checked: input.checked,
    label: clean(input.closest('label')?.innerText || input.parentElement?.innerText || ''),
  }));
  return {
    footer,
    footerAmount: parse(footer),
    summary,
    itemPrices,
    subtotal,
    methods,
  };
});

const selectPayment = async (page, method) => {
  const radio = page.locator(
    `input[name="radio-control-wc-payment-method-options"][value="${method}"], input[name="payment_method"][value="${method}"]`,
  ).first();
  await radio.waitFor({ state: 'attached', timeout: 20000 });
  await radio.check({ force: true });
  await page.waitForTimeout(2500);
};

const merchandiseAmount = (totals) => {
  if (Number.isFinite(totals.subtotal)) return totals.subtotal;
  if (totals.itemPrices?.length) return totals.itemPrices[0];
  return null;
};

const assertCheckoutMerchandise = async (page, expected, label) => {
  await page.waitForFunction((amount) => {
    const parse = (text) => {
      const match = String(text || '').replace(/\u00a0/g, ' ').match(/(\d{1,3}(?:\.\d{3})*,\d{2}|\d+[.,]\d{2}|\d+)/);
      if (!match) return null;
      const raw = match[1];
      const normalized = raw.includes(',') ? raw.replace(/\./g, '').replace(',', '.') : raw;
      const value = Number.parseFloat(normalized);
      return Number.isFinite(value) ? value : null;
    };
    const item = document.querySelector(
      '.wc-block-components-order-summary-item__total-price, .wc-block-components-product-price__value, .wc-block-components-order-summary-item .wc-block-components-product-price',
    );
    const subtotalNode = [...document.querySelectorAll('.wc-block-components-totals-item')]
      .find((node) => /subtotal|sub-total|produtos/i.test(node.innerText));
    const candidates = [
      item ? parse(item.innerText) : null,
      subtotalNode ? parse(subtotalNode.querySelector('.wc-block-components-totals-item__value')?.innerText || subtotalNode.innerText) : null,
    ].filter((value) => value !== null);
    return candidates.some((value) => Math.abs(value - amount) < 0.05);
  }, expected, { timeout: 15000 }).catch(() => null);

  const totals = await readCheckoutTotals(page);
  const actual = merchandiseAmount(totals);
  if (!closeEnough(actual, expected)) {
    fail(`${label}: mercadoria ${actual ?? '(vazia)'} (itens=${JSON.stringify(totals.itemPrices)} subtotal=${totals.subtotal} footer=${totals.footer}); esperado ~${expected.toFixed(2)}`);
  }
};

let fixture;
const browser = await launchBrowser();

try {
  log('provisionando fixtures');
  fixture = parseFixture(runWp('setup-043-payment-discount-fixture.php'));
  fs.mkdirSync(path.resolve('.local'), { recursive: true });
  fs.writeFileSync(path.resolve('.local/043-browser-fixture.json'), JSON.stringify(fixture, null, 2));

  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  await routeCanonicalNavigation(page, baseUrl);
  const products = fixture.products;

  // 1. P1 — só uma filha com desconto
  await page.goto(products.p1.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await selectOption(page, 'Com desconto');
  await assertHasDiscount(page, 90, 'P1 Com desconto');
  await page.screenshot({ path: path.join(evidenceDir, 'p1-com-desconto.png') });
  await selectOption(page, 'Sem A');
  await assertNoDiscount(page, 'P1 Sem A');

  // 2. P1B — pai 7% + override 10%
  await page.goto(products.p1b.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await selectOption(page, 'Override');
  await assertHasDiscount(page, 90, 'P1B Override');
  await selectOption(page, 'Herdada vazia');
  await assertHasDiscount(page, 93, 'P1B Herdada vazia');
  await selectOption(page, 'Herdada zero');
  await assertHasDiscount(page, 93, 'P1B Herdada zero');

  // 3. P2 — só pai, preços iguais, sem “a partir de”
  await page.goto(products.p2.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  const p2Initial = await readPdpDiscount(page);
  if (p2Initial.fromTexts.some((text) => /a partir/i.test(text))) {
    fail(`P2: preços iguais não deveriam ter “a partir de”: ${JSON.stringify(p2Initial.fromTexts)}`);
  }
  if (!p2Initial.texts.some((text) => closeEnough(parseMoney(text), 90))) {
    fail(`P2: pai deveria anunciar R$ 90,00 no Pix: ${JSON.stringify(p2Initial)}`);
  }
  await selectOption(page, 'B');
  await assertHasDiscount(page, 90, 'P2 variação B');

  // 4. P3 — sem desconto
  await page.goto(products.p3.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  const p3Initial = await readPdpDiscount(page);
  if (p3Initial.texts.length > 0) {
    fail(`P3: pai sem % não deveria mostrar Pix: ${JSON.stringify(p3Initial.texts)}`);
  }
  await selectOption(page, 'G');
  await assertNoDiscount(page, 'P3 variação G');

  // 5. P4 — simples 5%
  await page.goto(products.p4.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(500);
  await assertHasDiscount(page, 95, 'P4 simples');
  await page.screenshot({ path: path.join(evidenceDir, 'p4-simples.png') });

  // 6. P5 — três sem desconto
  await page.goto(products.p5.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await selectOption(page, 'M');
  await assertNoDiscount(page, 'P5 variação M');

  // 7. E2 — a partir de
  await page.goto(products.e2.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  const e2Initial = await readPdpDiscount(page);
  if (!e2Initial.fromTexts.some((text) => /a partir/i.test(text)) && !e2Initial.texts.some((text) => /a partir/i.test(text))) {
    fail(`E2: deveria mostrar “a partir de”: ${JSON.stringify(e2Initial)}`);
  }
  if (![...e2Initial.texts, ...e2Initial.fromTexts].some((text) => closeEnough(parseMoney(text), 90))) {
    fail(`E2: menor Pix deveria ser R$ 90,00: ${JSON.stringify(e2Initial)}`);
  }
  await selectOption(page, 'Cara');
  await assertHasDiscount(page, 180, 'E2 Cara');

  // 8. E4 — promoção + Pix usa preço promocional
  await page.goto(products.e4.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(500);
  await assertHasDiscount(page, 90, 'E4 promoção');
  const e4 = await readPdpDiscount(page);
  if (e4.texts.some((text) => closeEnough(parseMoney(text), 135))) {
    fail(`E4: não deveria usar regular R$ 150 → 135: ${JSON.stringify(e4.texts)}`);
  }

  // 9. E5 — variação R$ 0 não mostra Pix 0
  await page.goto(products.e5.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await selectOption(page, 'Gratis');
  const e5Free = await readPdpDiscount(page);
  if (e5Free.texts.some((text) => closeEnough(parseMoney(text), 0) && /no Pix/i.test(text))) {
    fail(`E5: variação grátis não pode mostrar R$ 0,00 no Pix: ${JSON.stringify(e5Free.texts)}`);
  }
  await selectOption(page, 'Paga');
  await assertHasDiscount(page, 90, 'E5 Paga');

  // 10–11. Checkout: meio com desconto vs sem desconto (simples 5%)
  await clearCart(page);
  await addSimpleToCart(page, products.p4.id);
  await page.goto(`${baseUrl}/finalizar-compra/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(2500);
  await selectPayment(page, fixture.payment_with_discount);
  await assertCheckoutMerchandise(page, 95, 'Checkout P4 com cheque');
  await page.screenshot({ path: path.join(evidenceDir, 'checkout-p4-cheque.png') });
  await selectPayment(page, fixture.payment_without_discount);
  await assertCheckoutMerchandise(page, 100, 'Checkout P4 com COD');
  await page.screenshot({ path: path.join(evidenceDir, 'checkout-p4-cod.png') });

  // 12. Checkout variação com override 10%
  await clearCart(page);
  await addVariationToCart(page, products.p1b.children.Override.id);
  await page.goto(`${baseUrl}/finalizar-compra/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(2500);
  await selectPayment(page, fixture.payment_with_discount);
  await assertCheckoutMerchandise(page, 90, 'Checkout P1B Override com cheque');
  await selectPayment(page, fixture.payment_without_discount);
  await assertCheckoutMerchandise(page, 100, 'Checkout P1B Override com COD');

  // 13. Checkout variação sem % própria (pai 0) não desconta
  await clearCart(page);
  await addVariationToCart(page, products.p1.children['Sem A'].id);
  await page.goto(`${baseUrl}/finalizar-compra/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(2500);
  await selectPayment(page, fixture.payment_with_discount);
  await assertCheckoutMerchandise(page, 100, 'Checkout P1 Sem A com cheque (sem desconto)');

  if (failures.length > 0) {
    throw new Error(`${failures.length} falha(s) no browser 043`);
  }
  log('aprovado');
} finally {
  await browser.close();
  if (!keepFixture) {
    try {
      runWp('cleanup-043-payment-discount-fixture.php');
      log('fixture removida');
    } catch (error) {
      console.error(`[043-browser] cleanup falhou: ${error instanceof Error ? error.message : String(error)}`);
    }
  } else {
    log('PETSHOP_043_KEEP_FIXTURE=1 — produtos mantidos para inspeção manual');
  }
}

if (failures.length > 0) {
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log('Validacao browser do Plano 043 aprovada.');
