import path from 'node:path';
import { createEvidenceDirectory, launchBrowser } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('011');

const failures = [];
const browser = await launchBrowser();

try {
  for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1100 },
    { name: 'tablet-768', width: 768, height: 900 },
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
      const campaigns = document.querySelector('.petshop-home-campaigns');
      const hero = document.querySelector('.petshop-hero');
      const heroRect = hero?.getBoundingClientRect();
      const campaignRect = campaigns?.getBoundingClientRect();
      const controls = campaigns ? [...campaigns.querySelectorAll('.petshop-home-campaigns__controls')].filter(visible) : [];
      const links = campaigns ? [...campaigns.querySelectorAll('.petshop-home-campaigns__link')].filter(visible) : [];
      const pictures = campaigns ? campaigns.querySelectorAll('picture source[media*="767"]') : [];
      const visibleSlides = campaigns
        ? [...campaigns.querySelectorAll('.petshop-home-campaigns__slide')].filter((slide) => !slide.hidden && visible(slide))
        : [];
      const buttons = campaigns
        ? [...campaigns.querySelectorAll('.petshop-home-campaigns__prev, .petshop-home-campaigns__next, .petshop-home-campaigns__dot')]
        : [];
      const minButtonSize = buttons.length
        ? Math.min(...buttons.map((button) => Math.min(button.getBoundingClientRect().width, button.getBoundingClientRect().height)))
        : 0;

      return {
        hasCampaigns: visible(campaigns),
        slideCount: visibleSlides.length,
        controlCount: controls.length,
        linkCount: links.length,
        pictureCount: pictures.length,
        heroBeforeCampaigns: !!(heroRect && campaignRect && heroRect.top <= campaignRect.top),
        overflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
        minButtonSize,
      };
    });

    await page.screenshot({
      path: path.join(evidenceDir, `${viewport.name}.png`),
      fullPage: true,
    });

    if (response?.status() !== 200) {
      failures.push(`${viewport.name}: HTTP ${response?.status()}`);
    }
    if (metrics.hasCampaigns && metrics.overflow > 1) {
      failures.push(`${viewport.name}: overflow horizontal ${metrics.overflow}px`);
    }
    if (metrics.hasCampaigns && !metrics.heroBeforeCampaigns) {
      failures.push(`${viewport.name}: hero institucional deveria preceder banners de campanha`);
    }
    if (metrics.hasCampaigns && metrics.linkCount < 1) {
      failures.push(`${viewport.name}: banner de campanha sem link clicavel visivel`);
    }
    if (metrics.hasCampaigns && metrics.slideCount > 1 && metrics.controlCount !== 1) {
      failures.push(`${viewport.name}: multiplos banners deveriam exibir controles de carrossel`);
    }
    if (metrics.hasCampaigns && metrics.slideCount === 1 && metrics.controlCount > 0) {
      failures.push(`${viewport.name}: banner unico nao deveria exibir controles`);
    }
    if (viewport.width <= 390 && metrics.hasCampaigns && metrics.pictureCount < 1) {
      failures.push(`${viewport.name}: campanha com arte mobile deveria usar picture`);
    }
    if (metrics.hasCampaigns && metrics.minButtonSize > 0 && metrics.minButtonSize < 44) {
      failures.push(`${viewport.name}: controle de carrossel abaixo de 44px`);
    }
  }
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error(failures.join('\n'));
  process.exit(1);
}

console.log('Validacao browser do Plano 011 aprovada (sem banners ou com banners conforme conteudo da Home).');
