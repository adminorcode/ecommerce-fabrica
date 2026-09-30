import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('027');
const failures = [];
const browser = await launchBrowser();

const recordFailure = (condition, message) => {
  if (!condition) failures.push(message);
};

try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await routeCanonicalNavigation(page, baseUrl);
  const fixtureResponse = await page.request.get(`${baseUrl}/wp-content/uploads/petshop-gates/027.json`);
  recordFailure(fixtureResponse.ok(), `Fixture 027.json HTTP ${fixtureResponse.status()}`);
  if (!fixtureResponse.ok()) {
    throw new Error('Fixture 027.json ausente. Execute scripts/validate-027-shipping-hub.php antes do gate browser.');
  }

  const fixture = await fixtureResponse.json();
  const response = await page.goto(`${baseUrl}${fixture.productPath}`, { waitUntil: 'networkidle', timeout: 30000 });
  recordFailure(response?.ok() ?? false, `PDP HTTP ${response?.status()}`);

  recordFailure(await page.locator('[data-petshop-shipping-form]').count() === 1, 'PDP sem exatamente uma calculadora Petshop.');
  recordFailure(await page.locator('text=Calcular entrega').count() >= 1, 'Titulo/botao Calcular entrega ausente.');
  recordFailure(await page.locator('#shipping-calc:visible, #woocommerce-correios-calculo-de-frete-na-pagina-do-produto:visible, #melhor-envio-shortcode:visible, .containerCalculator:visible').count() === 0, 'Widget extra de frete visivel na PDP.');

  await page.locator('[data-petshop-shipping-form] input[name="postcode"]').fill(fixture.postcode);
  await page.locator('[data-petshop-shipping-form] button[type="submit"]').click();
  await page.waitForFunction(() => {
    const text = document.querySelector('[data-petshop-shipping-result]')?.textContent || '';
    return text.includes('R$') || text.includes('Nao ha opcao') || text.includes('Não há opção');
  }, null, { timeout: 20000 });

  const resultText = await page.locator('[data-petshop-shipping-result]').innerText();
  const resultHtml = await page.locator('[data-petshop-shipping-result]').evaluate((element) => element.innerHTML);
  recordFailure(!/&#\d+;|&#|&nbsp;/.test(resultText), 'Texto visivel contem entidade HTML.');
  recordFailure(!/&#\d+;|&#|&nbsp;/.test(resultHtml), 'HTML do resultado contem entidade numerica ou nbsp.');
  recordFailure(resultText.includes('R$'), 'Resultado calculado nao mostrou preco em real brasileiro.');

  await page.goto(`${baseUrl}/carrinho/`, { waitUntil: 'networkidle', timeout: 30000 });
  recordFailure(await page.locator('#shipping-calc:visible, #woocommerce-correios-calculo-de-frete-na-pagina-do-produto:visible, #melhor-envio-shortcode:visible, .containerCalculator:visible').count() === 0, 'Widget extra de frete visivel no carrinho.');

  await page.goto(`${baseUrl}/finalizar-compra/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  const persistedPostcode = await page.evaluate(async () => {
    const cart = await (await fetch('/wp-json/wc/store/v1/cart')).json();
    return cart.shipping_address?.postcode || '';
  });
  recordFailure(persistedPostcode === fixture.postcode, `Store API nao preservou CEP da PDP no checkout (${persistedPostcode}).`);
  recordFailure(await page.locator('#shipping-calc:visible, #woocommerce-correios-calculo-de-frete-na-pagina-do-produto:visible, #melhor-envio-shortcode:visible, .containerCalculator:visible').count() === 0, 'Widget extra de frete visivel no checkout.');

  await page.screenshot({ path: path.join(evidenceDir, 'shipping-hub-checkout.png'), fullPage: true });
  await page.close();
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error(JSON.stringify({ failures }, null, 2));
  process.exit(1);
}

console.log(JSON.stringify({ ok: true, evidenceDir }, null, 2));
