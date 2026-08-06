import path from 'node:path';
import { createEvidenceDirectory, launchBrowser } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('010/session-03');

const failures = [];
const browser = await launchBrowser();

try {
  for (const viewport of [
    { name: 'desktop-1280', width: 1280, height: 1100 },
    { name: 'mobile-390', width: 390, height: 900 },
  ]) {
    const page = await browser.newPage({ viewport });
    const response = await page.goto(baseUrl, { waitUntil: 'domcontentloaded', timeout: 20000 });
    await page.evaluate(async () => {
      const images = [...document.images];
      await Promise.race([
        Promise.all(images.map((image) => (image.complete ? Promise.resolve() : new Promise((resolve) => {
          image.addEventListener('load', resolve, { once: true });
          image.addEventListener('error', resolve, { once: true });
        })))),
        new Promise((resolve) => setTimeout(resolve, 3000)),
      ]);
    });

    const metrics = await page.evaluate(() => {
      const visible = (element) => !!(element && (element.offsetWidth || element.offsetHeight || element.getClientRects().length));
      const showcases = [...document.querySelectorAll('.petshop-product-showcase')].filter(visible);
      const heads = showcases.map((section) => section.querySelector('.petshop-section-head')).filter(visible);
      const links = heads.map((head) => head?.querySelector('.petshop-section-head__link')).filter(visible);
      const firstGrid = showcases[0]?.querySelector('ul.products.columns-4');
      const gridStyle = firstGrid ? getComputedStyle(firstGrid) : null;
      const columns = gridStyle?.gridTemplateColumns?.split(' ').filter(Boolean).length ?? 0;
      const orphanSeasonal = [...document.querySelectorAll('.petshop-seasonal h2')].filter(visible);
      return {
        showcaseCount: showcases.length,
        headCount: heads.length,
        linkCount: links.length,
        columns,
        overflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
        orphanSeasonalHeadings: orphanSeasonal.length,
        minLinkHeight: links.length
          ? Math.min(...links.map((link) => link.getBoundingClientRect().height))
          : 0,
      };
    });

    await page.screenshot({
      path: path.join(evidenceDir, `${viewport.name}.png`),
      fullPage: true,
    });

    if (response?.status() !== 200) {
      failures.push(`${viewport.name}: HTTP ${response?.status()}`);
    }
    if (metrics.showcaseCount < 2) {
      failures.push(`${viewport.name}: vitrines insuficientes (${metrics.showcaseCount})`);
    }
    if (metrics.headCount < metrics.showcaseCount) {
      failures.push(`${viewport.name}: cabecalho unificado ausente em alguma vitrine`);
    }
    if (metrics.linkCount < metrics.showcaseCount) {
      failures.push(`${viewport.name}: link Ver todos ausente em alguma vitrine`);
    }
    if (viewport.width >= 1280 && metrics.columns !== 4) {
      failures.push(`${viewport.name}: grade deveria ter 4 colunas (${metrics.columns})`);
    }
    if (viewport.width <= 390 && metrics.columns !== 2) {
      failures.push(`${viewport.name}: grade mobile deveria ter 2 colunas (${metrics.columns})`);
    }
    if (metrics.overflow > 1) {
      failures.push(`${viewport.name}: overflow horizontal ${metrics.overflow}px`);
    }
    if (metrics.orphanSeasonalHeadings > 0) {
      failures.push(`${viewport.name}: heading orfao na secao sazonal legada`);
    }
    if (viewport.name === 'desktop-1280') {
      const cartResult = await page.evaluate(async () => {
        const button = document.querySelector('.petshop-product-showcase .add_to_cart_button');
        if (!(button instanceof HTMLButtonElement || button instanceof HTMLAnchorElement)) {
          return { tested: false, reason: 'botao ausente' };
        }
        button.focus();
        button.click();
        await new Promise((resolve) => setTimeout(resolve, 1500));
        const count = document.querySelector('.wc-block-mini-cart__badge')?.textContent?.trim() ?? '';
        return { tested: true, count };
      });
      if (!cartResult.tested) {
        failures.push(`${viewport.name}: ${cartResult.reason}`);
      } else if (!cartResult.count || cartResult.count === '0') {
        failures.push(`${viewport.name}: minicarrinho nao atualizou apos adicionar produto`);
      }
    }
    if (viewport.width <= 390 && metrics.minLinkHeight > 0 && metrics.minLinkHeight < 44) {
      failures.push(`${viewport.name}: alvo de toque do link abaixo de 44px`);
    }
  }
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error(failures.join('\n'));
  process.exit(1);
}

console.log('Validacao browser do Plano 010 aprovada.');
