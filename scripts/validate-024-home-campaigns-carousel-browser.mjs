import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('024');

const failures = [];
const browser = await launchBrowser();

try {
  for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1100 },
    { name: 'laptop-1024', width: 1024, height: 1000 },
    { name: 'tablet-768', width: 768, height: 900 },
    { name: 'mobile-390', width: 390, height: 900 },
  ]) {
    const page = await browser.newPage({ viewport });
    await routeCanonicalNavigation(page, baseUrl);
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
      const track = campaigns?.querySelector('.petshop-home-campaigns__track');
      const trackRect = track?.getBoundingClientRect();
      const prev = campaigns?.querySelector('.petshop-home-campaigns__prev');
      const next = campaigns?.querySelector('.petshop-home-campaigns__next');
      const dots = campaigns?.querySelector('.petshop-home-campaigns__dots');
      const buttons = campaigns
        ? [...campaigns.querySelectorAll('.petshop-home-campaigns__prev, .petshop-home-campaigns__next, .petshop-home-campaigns__dot')]
        : [];
      const slides = campaigns ? [...campaigns.querySelectorAll('.petshop-home-campaigns__slide')] : [];
      const visibleSlides = slides.filter((slide) => !slide.hidden && visible(slide));
      const isCarousel = campaigns?.classList.contains('is-carousel') && visible(prev) && visible(next);
      const minButtonSize = buttons.length
        ? Math.min(...buttons.map((button) => Math.min(button.getBoundingClientRect().width, button.getBoundingClientRect().height)))
        : 0;
      const durations = slides.map((slide) => Number.parseInt(slide.dataset.durationSeconds || '', 10));
      const prevRect = prev?.getBoundingClientRect();
      const nextRect = next?.getBoundingClientRect();
      const dotsRect = dots?.getBoundingClientRect();
      const overlay = isCarousel && trackRect && prevRect && nextRect && dotsRect
        ? {
            prevOverTrack: prevRect.right > trackRect.left && prevRect.left < trackRect.right,
            nextOverTrack: nextRect.left < trackRect.right && nextRect.right > trackRect.left,
            dotsOverTrack: dotsRect.top < trackRect.bottom && dotsRect.bottom > trackRect.top,
            prevLeftOfNext: prevRect.left < nextRect.left,
            dotsCentered: Math.abs(((dotsRect.left + dotsRect.right) / 2) - ((trackRect.left + trackRect.right) / 2)) < 48,
          }
        : null;

      const campaignOverflowRight = campaignRect
        ? Math.max(0, Math.ceil(campaignRect.right) - document.documentElement.clientWidth)
        : 0;
      const campaignOverflowLeft = campaignRect
        ? Math.max(0, -Math.floor(campaignRect.left))
        : 0;

      return {
        hasCampaigns: visible(campaigns),
        isCarousel,
        slideCount: slides.length,
        visibleSlideCount: visibleSlides.length,
        minButtonSize,
        durations,
        overlay,
        heroBeforeCampaigns: !!(heroRect && campaignRect && heroRect.top <= campaignRect.top),
        campaignOverflow: Math.max(campaignOverflowLeft, campaignOverflowRight),
      };
    });

    await page.screenshot({
      path: path.join(evidenceDir, `${viewport.name}.png`),
      fullPage: true,
    });

    if (response?.status() !== 200) {
      failures.push(`${viewport.name}: HTTP ${response?.status()}`);
    }
    if (metrics.hasCampaigns && metrics.campaignOverflow > 1) {
      failures.push(`${viewport.name}: faixa de campanhas com overflow ${metrics.campaignOverflow}px`);
    }
    if (metrics.hasCampaigns && !metrics.heroBeforeCampaigns) {
      failures.push(`${viewport.name}: hero institucional deveria preceder o banner promocional`);
    }
    if (metrics.hasCampaigns && metrics.slideCount > 3) {
      failures.push(`${viewport.name}: a loja publicou mais de 3 banners`);
    }
    if (metrics.hasCampaigns && metrics.durations.some((value) => !Number.isFinite(value) || value < 3 || value > 60)) {
      failures.push(`${viewport.name}: tempo de visualizacao ausente ou fora de 3-60s`);
    }
    if (metrics.isCarousel && metrics.visibleSlideCount !== 1) {
      failures.push(`${viewport.name}: carrossel deveria exibir exatamente um slide por vez`);
    }
    if (metrics.isCarousel && metrics.minButtonSize < 44) {
      failures.push(`${viewport.name}: controle de carrossel abaixo de 44px (${metrics.minButtonSize})`);
    }
    if (metrics.isCarousel && metrics.overlay && (!metrics.overlay.prevOverTrack || !metrics.overlay.nextOverTrack || !metrics.overlay.dotsOverTrack)) {
      failures.push(`${viewport.name}: setas ou indicadores nao estao sobrepostos a arte`);
    }
    if (metrics.isCarousel && metrics.overlay && !metrics.overlay.prevLeftOfNext) {
      failures.push(`${viewport.name}: seta anterior deveria ficar a esquerda da seta seguinte`);
    }
    if (metrics.isCarousel && metrics.overlay && !metrics.overlay.dotsCentered) {
      failures.push(`${viewport.name}: indicadores deveriam ficar centralizados na base da arte`);
    }

    if (metrics.isCarousel) {
      const afterClick = await page.evaluate(() => {
        const next = document.querySelector('.petshop-home-campaigns__next');
        const first = document.querySelector('.petshop-home-campaigns__slide');
        next?.click();
        const slides = [...document.querySelectorAll('.petshop-home-campaigns__slide')];
        const visibleIndex = slides.findIndex((slide) => !slide.hidden);

        return {
          firstHidden: first instanceof HTMLElement ? first.hidden : false,
          visibleIndex,
        };
      });
      if (!afterClick.firstHidden || afterClick.visibleIndex !== 1) {
        failures.push(`${viewport.name}: clique em proximo nao avançou o carrossel`);
      }
    }
  }
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error(failures.join('\n'));
  process.exit(1);
}

console.log('Validacao browser do Plano 024 aprovada.');
