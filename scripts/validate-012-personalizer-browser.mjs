import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('012');
const browser = await launchBrowser();
const failures = [];

const viewports = [
  { name: 'desktop-1440', width: 1440, height: 1000 },
  { name: 'tablet-768', width: 768, height: 1024 },
  { name: 'mobile-390', width: 390, height: 844 },
];

const assertNoCdn = async (page, label) => {
  const cdnHits = await page.evaluate(() =>
    performance.getEntriesByType('resource')
      .map((entry) => entry.name)
      .filter((name) => /cdn\.jsdelivr|unpkg\.com|cdnjs\.cloudflare|fabricjs\.com/i.test(name))
  );
  if (cdnHits.length) {
    failures.push(`${label}: recurso CDN detectado (${cdnHits[0]})`);
  }
};

const visit = async (page, url) => {
  let last = null;
  for (let attempt = 0; attempt < 3; attempt += 1) {
    try {
      last = await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
      if (last?.status() === 200) {
        return last;
      }
    } catch (error) {
      last = null;
    }
    await page.waitForTimeout(1500);
  }

  return last;
};

// Baseline page provisioned before this plan: isolates the personalize route from
// storefront-wide layout issues that are not owned by Plano 012.
const baselineOverflow = async (page) => {
  const response = await visit(page, `${baseUrl}/atendimento/`);
  if (response?.status() !== 200) {
    return 0;
  }

  return page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth));
};

try {
  for (const viewport of viewports) {
    const page = await browser.newPage({ viewport });
    await routeCanonicalNavigation(page, baseUrl);
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    const baseline = await baselineOverflow(page);

    const personalizeResponse = await visit(page, `${baseUrl}/personalize/`);
    if (personalizeResponse?.status() !== 200) {
      failures.push(`${viewport.name}: /personalize/ HTTP ${personalizeResponse?.status()}`);
    }

    const personalizeMetrics = await page.evaluate(() => {
      const heading = document.querySelector('h1, h2');
      const showcase = document.querySelector('.petshop-personalizable-products, .woocommerce ul.products');
      return {
        title: heading ? heading.textContent.trim() : '',
        hasShowcase: Boolean(showcase),
        productCount: document.querySelectorAll('.woocommerce ul.products li.product, .petshop-personalizable-products li.product').length,
        overflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
      };
    });

    if (!personalizeMetrics.title) {
      failures.push(`${viewport.name}: /personalize/ sem título`);
    }
    if (personalizeMetrics.overflow > baseline + 1) {
      failures.push(`${viewport.name}: /personalize/ overflow ${personalizeMetrics.overflow}px (baseline ${baseline}px)`);
    }

    await page.screenshot({
      path: path.join(evidenceDir, `personalize-${viewport.name}.png`),
      fullPage: false,
    });

    // Prefer an enabled product link when the showcase has cards; otherwise open shop and look for CTA.
    let productUrl = null;
    const productLink = page.locator('.petshop-personalizable-products a.woocommerce-LoopProduct-link, .woocommerce ul.products li.product a.woocommerce-LoopProduct-link').first();
    if (await productLink.count()) {
      productUrl = await productLink.getAttribute('href');
    }

    if (!productUrl) {
      const shopResponse = await visit(page, `${baseUrl}/loja/`);
      if (shopResponse?.status() === 200) {
        const cta = page.locator('[data-petshop-personalize-open], a[href*="petshop_personalize=1"]').first();
        if (await cta.count()) {
          const href = await cta.getAttribute('href');
          productUrl = href && href.includes('http') ? href : null;
          if (!productUrl) {
            await cta.click();
            await page.waitForTimeout(500);
            productUrl = page.url();
          }
        }
      }
    }

    if (productUrl) {
      const productResponse = await visit(page, productUrl.includes('petshop_personalize=1')
        ? productUrl
        : `${productUrl}${productUrl.includes('?') ? '&' : '?'}petshop_personalize=1`);
      if (productResponse?.status() !== 200) {
        failures.push(`${viewport.name}: PDP personalizável HTTP ${productResponse?.status()}`);
      }

      const editor = page.locator('[data-petshop-personalizer]');
      const openButton = page.locator('[data-petshop-personalize-open]').first();
      if (await openButton.count()) {
        const hidden = await editor.getAttribute('hidden');
        if (hidden !== null) {
          await openButton.click();
          await page.waitForTimeout(400);
        }
      }

      const editorVisible = await editor.evaluate((node) => !node.hasAttribute('hidden') && getComputedStyle(node).display !== 'none').catch(() => false);
      const fabricLoaded = await page.evaluate(() => typeof window.fabric !== 'undefined');
      const canvasPresent = await page.locator('canvas').count();

      if (!editorVisible) {
        failures.push(`${viewport.name}: editor não abriu na PDP`);
      }
      if (!fabricLoaded) {
        failures.push(`${viewport.name}: Fabric.js não carregou localmente`);
      }
      if (canvasPresent < 1) {
        failures.push(`${viewport.name}: canvas ausente`);
      }

      await assertNoCdn(page, `${viewport.name}:pdp`);
      await page.screenshot({
        path: path.join(evidenceDir, `editor-${viewport.name}.png`),
        fullPage: false,
      });
    } else if (viewport.name === 'desktop-1440') {
      // Without seeded personalizable products the editor surface cannot be exercised.
      failures.push('desktop-1440: nenhum produto personalizável encontrado para abrir o editor');
    }

    if (pageErrors.length) {
      failures.push(`${viewport.name}: ${pageErrors.length} erro(s) de página`);
    }

    await page.close();
  }
} finally {
  await browser.close();
}

console.log(JSON.stringify({ failures, evidenceDir }, null, 2));
if (failures.length) {
  process.exit(1);
}
