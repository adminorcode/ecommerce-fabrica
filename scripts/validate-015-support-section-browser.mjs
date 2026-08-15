import path from 'node:path';
import { createEvidenceDirectory, launchBrowser } from './lib/browser-helpers.mjs';

const publicUrl = process.env.PETSHOP_PUBLIC_URL || 'http://localhost:8888';
const internalBaseUrl = process.env.PETSHOP_BASE_URL || publicUrl;
const canonicalHost = process.env.PETSHOP_CANONICAL_HOST || new URL(publicUrl).host;
const evidenceDir = createEvidenceDirectory('015');
const failures = [];
const browser = await launchBrowser();

try {
  for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1100 },
    { name: 'desktop-1024', width: 1024, height: 900 },
    { name: 'tablet-768', width: 768, height: 900 },
    { name: 'mobile-390', width: 390, height: 900 },
  ]) {
    const page = await browser.newPage({ viewport });
    await page.route('**/*', async (route) => {
      const request = route.request();
      const url = new URL(request.url());
      const publicOrigin = new URL(publicUrl).origin;
      if (url.origin !== publicOrigin) {
        await route.continue();
        return;
      }

      const internal = new URL(url.pathname + url.search + url.hash, internalBaseUrl);
      await route.continue({
        headers: { ...request.headers(), Host: canonicalHost },
        url: internal.toString(),
      });
    });
    const consoleErrors = [];
    const requestErrors = [];
    page.on('console', (message) => {
      if (message.type() === 'error') {
        consoleErrors.push(message.text());
      }
    });
    page.on('pageerror', (error) => {
      consoleErrors.push(error.message);
    });
    page.on('requestfailed', (request) => {
      const failure = request.failure();
      const url = request.url();
      if (url.startsWith(publicUrl)) {
        requestErrors.push(`${url}: ${failure?.errorText || 'failed'}`);
      }
    });

    const response = await page.goto(publicUrl, { waitUntil: 'domcontentloaded', timeout: 20000 });
    await page.evaluate(async () => {
      await Promise.race([
        Promise.all([...document.images].map((image) => (image.complete ? Promise.resolve() : new Promise((resolve) => {
          image.addEventListener('load', resolve, { once: true });
          image.addEventListener('error', resolve, { once: true });
        })))),
        new Promise((resolve) => setTimeout(resolve, 3000)),
      ]);
    });

    const metrics = await page.evaluate(() => {
      const visible = (element) => !!(element && (element.offsetWidth || element.offsetHeight || element.getClientRects().length));
      const section = document.querySelector('.petshop-support-banner');
      if (!section || !visible(section)) {
        return { exists: false };
      }
      const title = section.querySelector('.petshop-support-banner__title');
      const text = section.querySelector('.petshop-support-banner__text');
      const benefit = section.querySelector('.petshop-support-banner__benefit');
      const buttonLinks = [...section.querySelectorAll('.wp-block-button__link')].filter(visible);
      const desktopImage = section.querySelector('.petshop-support-banner__image--desktop img');
      const mobileImage = section.querySelector('.petshop-support-banner__image--mobile img');
      const visibleImages = [desktopImage, mobileImage].filter(visible);
      const order = [
        section.querySelector('.petshop-support-banner__eyebrow'),
        title,
        text,
        benefit,
        buttonLinks[0],
        section.querySelector('.petshop-support-banner__media'),
      ].map((element) => element ? element.getBoundingClientRect().top : null);
      const styles = title ? getComputedStyle(title) : null;
      const background = section.querySelector('.petshop-support-banner__inner')
        ? getComputedStyle(section.querySelector('.petshop-support-banner__inner')).backgroundColor
        : 'rgb(0, 79, 80)';
      const rgb = (value) => (value.match(/[\d.]+/g) || []).slice(0, 3).map(Number);
      const luminance = (value) => rgb(value).map((channel) => {
        const normalized = channel / 255;

        return normalized <= 0.03928 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
      }).reduce((total, channel, index) => total + channel * [0.2126, 0.7152, 0.0722][index], 0);
      const contrast = (foreground, backgroundColor) => {
        const values = [luminance(foreground), luminance(backgroundColor)].sort((a, b) => b - a);

        return (values[0] + 0.05) / (values[1] + 0.05);
      };

      return {
        exists: true,
        title: title?.textContent?.trim() || '',
        text: text?.textContent?.trim() || '',
        buttonCount: buttonLinks.length,
        buttonHref: buttonLinks[0]?.href || '',
        buttonWidth: buttonLinks[0]?.getBoundingClientRect().width || 0,
        buttonHeight: buttonLinks[0]?.getBoundingClientRect().height || 0,
        desktopVisible: visible(desktopImage),
        mobileVisible: visible(mobileImage),
        visibleImageCount: visibleImages.length,
        imageBroken: [desktopImage, mobileImage].some((image) => image && image.naturalWidth === 0),
        imageHasAlt: [desktopImage, mobileImage].every((image) => image && image.hasAttribute('alt')),
        overflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
        contrast: styles ? Number.parseFloat(contrast(styles.color, background).toFixed(2)) : 0,
        order,
      };
    });

    await page.screenshot({
      path: path.join(evidenceDir, `${viewport.name}.png`),
      fullPage: true,
    });

    if (response?.status() !== 200) {
      failures.push(`${viewport.name}: HTTP ${response?.status()}`);
    }
    if (!metrics.exists) {
      failures.push(`${viewport.name}: secao de atendimento nao encontrada`);
      continue;
    }
    if (metrics.overflow > 1) {
      failures.push(`${viewport.name}: overflow horizontal ${metrics.overflow}px`);
    }
    if (metrics.buttonCount !== 1) {
      failures.push(`${viewport.name}: deveria haver exatamente um CTA visivel`);
    }
    if (
      metrics.buttonCount === 1
      && !metrics.buttonHref.startsWith('https://wa.me/')
      && !metrics.buttonHref.includes('/atendimento/')
    ) {
      failures.push(`${viewport.name}: CTA nao aponta para WhatsApp nem fallback de atendimento`);
    }
    if (metrics.buttonCount === 1 && (metrics.buttonWidth < 44 || metrics.buttonHeight < 44)) {
      failures.push(`${viewport.name}: alvo do CTA abaixo de 44px`);
    }
    if (metrics.imageBroken || !metrics.imageHasAlt) {
      failures.push(`${viewport.name}: imagem quebrada ou sem atributo alt`);
    }
    if (viewport.width < 768 && (!metrics.mobileVisible || metrics.desktopVisible)) {
      failures.push(`${viewport.name}: mobile deveria exibir somente imagem vertical`);
    }
    if (viewport.width >= 768 && (!metrics.desktopVisible || metrics.mobileVisible)) {
      failures.push(`${viewport.name}: desktop/tablet deveria exibir somente imagem horizontal`);
    }
    if (metrics.contrast < 4.5) {
      failures.push(`${viewport.name}: contraste do titulo abaixo de AA (${metrics.contrast})`);
    }
    if (viewport.width < 768 && metrics.order.some((value, index, values) => index > 0 && value !== null && values[index - 1] !== null && value < values[index - 1])) {
      failures.push(`${viewport.name}: ordem visual mobile nao segue copy, CTA e midia`);
    }
    const blockingConsoleErrors = consoleErrors.filter((message) => !message.includes('Failed to load resource'));
    if (blockingConsoleErrors.length > 0) {
      failures.push(`${viewport.name}: console/page errors: ${blockingConsoleErrors.join(' | ')}`);
    }
    if (requestErrors.length > 0) {
      failures.push(`${viewport.name}: request errors: ${requestErrors.join(' | ')}`);
    }
  }
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error(failures.join('\n'));
  process.exit(1);
}

console.log('Validacao browser do Plano 015 aprovada.');
