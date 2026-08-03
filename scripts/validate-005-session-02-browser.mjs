import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const executablePath = process.env.PETSHOP_CHROME || 'C:/Users/lucas/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe';
const rgb = (value) => (value.match(/[\d.]+/g) || []).slice(0, 3).map(Number);
const luminance = (value) => rgb(value).map((channel) => {
  const normalized = channel / 255;
  return normalized <= .03928 ? normalized / 12.92 : ((normalized + .055) / 1.055) ** 2.4;
}).reduce((total, channel, index) => total + channel * [.2126, .7152, .0722][index], 0);
const contrast = (foreground, background) => {
  const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
  return (values[0] + .05) / (values[1] + .05);
};
const evidenceDir = path.resolve('.local/evidence/005/session-02');
fs.mkdirSync(evidenceDir, { recursive: true });
const failures = [];
const results = [];
const browser = await chromium.launch({ headless: true, executablePath });

try {
  for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'desktop-1024', width: 1024, height: 900 },
    { name: 'tablet-768', width: 768, height: 900 },
    { name: 'mobile-390', width: 390, height: 844 },
  ]) {
    const page = await browser.newPage({ viewport });
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    const response = await page.goto(baseUrl, { waitUntil: 'networkidle', timeout: 20000 });
    const hero = page.locator('.petshop-hero');
    const box = await hero.boundingBox();
    const title = (await hero.locator('h1').innerText()).trim();
    const lineCounts = await hero.locator('h1').evaluate((heading) => {
      const lines = new Map();
      const walker = document.createTreeWalker(heading, NodeFilter.SHOW_TEXT);
      for (let text = walker.nextNode(); text; text = walker.nextNode()) {
        const expression = /\S+/g;
        for (let match = expression.exec(text.textContent); match; match = expression.exec(text.textContent)) {
          const range = document.createRange();
          range.setStart(text, match.index);
          range.setEnd(text, match.index + match[0].length);
          const top = Math.round(range.getBoundingClientRect().top);
          lines.set(top, (lines.get(top) || 0) + 1);
        }
      }
      return [...lines.values()];
    });
    const benefits = (await page.locator('.petshop-benefits__title').allTextContents()).map((value) => value.trim());
    const ctas = page.locator('.petshop-hero .wp-block-button__link');
    const ctaUrls = await ctas.evaluateAll((links) => links.map((link) => link.href));
    const alt = await hero.locator('img.wp-block-cover__image-background').getAttribute('alt');
    const contrastSurface = await hero.evaluate((element) => {
      const overlay = element.querySelector('.wp-block-cover__background');
      const heading = element.querySelector('h1');
      return {
        overlayOpacity: overlay ? getComputedStyle(overlay).opacity : '',
        overlayImage: overlay ? getComputedStyle(overlay).backgroundImage : '',
        headingColor: heading ? getComputedStyle(heading).color : '',
      };
    });
    const contentAxis = await hero.evaluate((element) => {
      const selectors = ['.petshop-eyebrow', 'h1', 'p:not(.petshop-eyebrow)', '.wp-block-buttons'];
      return selectors.map((selector) => ({ selector, x: element.querySelector(selector)?.getBoundingClientRect().x ?? null }));
    });
    const overflow = await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth));
    const record = { viewport: viewport.name, status: response?.status(), box, title, lineCounts, benefits, ctas: await ctas.count(), alt, overflow, pageErrors, contrastSurface, contentAxis };
    results.push(record);

    if (record.status !== 200) failures.push(`${viewport.name}: HTTP ${record.status}`);
    if (!title || /dia dos pais/i.test(title)) failures.push(`${viewport.name}: H1 institucional ausente ou sazonal`);
    if (await hero.locator('h1 br').count()) failures.push(`${viewport.name}: H1 contem quebra artificial`);
    if (!lineCounts.length || lineCounts.some((count) => count < 2)) failures.push(`${viewport.name}: H1 possui palavra isolada ou nao foi medido`);
    if (record.ctas !== 2) failures.push(`${viewport.name}: quantidade de CTAs divergente`);
    if (benefits.length !== 3) failures.push(`${viewport.name}: faixa de beneficios divergente`);
    if (!alt) failures.push(`${viewport.name}: alt do hero vazio`);
    if (overflow > 1) failures.push(`${viewport.name}: overflow horizontal de ${overflow}px`);
    if (pageErrors.length) failures.push(`${viewport.name}: erros de pagina ${pageErrors.length}`);
    if (contrastSurface.overlayOpacity !== '1' || contrastSurface.overlayImage === 'none') failures.push(`${viewport.name}: superficie de contraste do hero inativa`);
    if (contrast(contrastSurface.headingColor, 'rgb(255, 255, 255)') < 4.5) failures.push(`${viewport.name}: texto principal sem contraste AA sobre a superficie clara`);
    const axisValues = contentAxis.map(({ x }) => x).filter(Number.isFinite);
    if (axisValues.length !== 4 || Math.max(...axisValues) - Math.min(...axisValues) > 1) failures.push(`${viewport.name}: conteudo do hero nao compartilha o mesmo eixo esquerdo`);
    if (viewport.name === 'desktop-1440' && box) {
      const ratio = box.width / box.height;
      if (ratio < 2.4 || ratio > 3.3) failures.push(`desktop-1440: proporcao ${ratio.toFixed(2)} fora do gate`);
    }
    if (viewport.name === 'desktop-1440') {
      for (const url of ctaUrls) {
        const destination = await page.request.get(url, { timeout: 10000 });
        if (destination.status() !== 200) failures.push(`CTA respondeu HTTP ${destination.status()}: ${url}`);
      }
      await ctas.evaluateAll((links) => links.forEach((link, index) => { link.dataset.session02Cta = String(index); }));
      await page.evaluate(() => {
        document.body.tabIndex = -1;
        document.body.focus();
      });
      const reached = new Set();
      for (let index = 0; index < 30 && reached.size < 2; index += 1) {
        await page.keyboard.press('Tab');
        const focused = await page.evaluate(() => {
          const element = document.activeElement;
          return {
            id: element?.dataset?.session02Cta ?? '',
            outline: element ? getComputedStyle(element).outlineStyle : 'none',
          };
        });
        if (focused.id !== '') {
          reached.add(focused.id);
          if (focused.outline === 'none') failures.push(`CTA ${focused.id}: foco invisivel`);
        }
      }
      if (reached.size !== 2) failures.push('CTAs nao foram alcancados por Tab');
      const seasonalText = (await page.locator('.petshop-seasonal').innerText()).trim();
      if (!/dia dos pais/i.test(seasonalText)) failures.push('campanha Dia dos Pais nao aparece como conteudo secundario');
    }
    if (viewport.name === 'desktop-1440' || viewport.name === 'mobile-390') {
      await page.screenshot({ path: path.join(evidenceDir, `final-${viewport.name}.png`), fullPage: true });
    }
    await page.close();
  }
} finally {
  await browser.close();
}

console.log(JSON.stringify({ results, failures }, null, 2));
if (failures.length) process.exit(1);
