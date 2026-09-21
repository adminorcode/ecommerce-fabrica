import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('030');
const failures = [];
const browser = await launchBrowser();

const recordFailure = (condition, message) => {
  if (!condition) {
    failures.push(message);
  }
};

const isIgnorablePageError = (message) => /crypto\.randomUUID is not a function/i.test(message);

try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await routeCanonicalNavigation(page, baseUrl);
  const pageErrors = [];
  page.on('pageerror', (error) => {
    if (!isIgnorablePageError(error.message)) {
      pageErrors.push(error.message);
    }
  });

  const fixtureResponse = await page.request.get(`${baseUrl}/wp-content/uploads/petshop-gates/030.json`);
  recordFailure(fixtureResponse.ok(), `Fixture 030.json HTTP ${fixtureResponse.status()}`);
  if (!fixtureResponse.ok()) {
    throw new Error('Fixture 030.json ausente. Execute scripts/validate-030.php antes do gate browser.');
  }

  const fixture = await fixtureResponse.json();
  const receivedUrl = `${baseUrl}${fixture.path}${fixture.query ? `?${fixture.query}` : ''}`;
  const receivedResponse = await page.goto(receivedUrl, {
    waitUntil: 'domcontentloaded',
    timeout: 30000,
  });
  recordFailure(receivedResponse?.ok() ?? false, `Pedido recebido HTTP ${receivedResponse?.status()}`);
  const html = await page.content();
  const bodyText = await page.locator('body').innerText();
  recordFailure(
    /woocommerce-thankyou-order-received|wp-block-woocommerce-order-confirmation|wp-block-woocommerce-checkout/i.test(html),
    'Confirmacao sem markup classico nem Checkout Block'
  );
  recordFailure(
    bodyText.includes(fixture.phrase),
    `Confirmacao nao mostrou “${fixture.phrase}”`
  );
  recordFailure(
    !bodyText.includes(fixture.native),
    'Confirmacao ainda mostra “Obrigado. Seu pedido foi recebido.”'
  );
  await page.screenshot({ path: `${evidenceDir}/order-received.png`, fullPage: true });

  recordFailure(pageErrors.length === 0, `${pageErrors.length} erro(s) JavaScript: ${pageErrors.join('; ')}`);
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error(JSON.stringify({ failures }, null, 2));
  process.exit(1);
}

console.log(JSON.stringify({ ok: true, evidenceDir }, null, 2));
