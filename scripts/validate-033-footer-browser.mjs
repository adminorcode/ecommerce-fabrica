import path from 'node:path';
import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('033');
const browser = await launchBrowser();
const failures = [];

const viewports = [
  { name: 'desktop-1440', width: 1440, height: 1000, grid: 4, trust: 4 },
  { name: 'tablet-1024', width: 1024, height: 900, grid: 2, trust: 2 },
  { name: 'mobile-390', width: 390, height: 844, grid: 1, trust: 1 },
];

const recordFailure = (condition, message) => {
  if (!condition) failures.push(message);
};

const rgb = (value) => (value.match(/[\d.]+/g) || []).slice(0, 3).map(Number);
const closeColor = (left, right, tolerance = 4) => {
  const a = rgb(left);
  const b = rgb(right);

  return a.length === 3 && b.length === 3 && a.every((channel, index) => Math.abs(channel - b[index]) <= tolerance);
};

const isTeal900 = (value) => {
  const [r, g, b] = rgb(value);

  return Math.abs(r - 0) <= 4 && Math.abs(g - 79) <= 4 && Math.abs(b - 80) <= 4;
};

try {
  for (const viewport of viewports) {
    const page = await browser.newPage({ viewport });
    await routeCanonicalNavigation(page, baseUrl);
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));

    const response = await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle', timeout: 30000 });
    recordFailure(response?.status() === 200, `${viewport.name}: Home HTTP ${response?.status()}`);

    const footer = page.locator('footer.petshop-institutional-footer');
    recordFailure(await footer.count() === 1, `${viewport.name}: rodape institucional deveria existir uma vez`);

    if (await footer.count() === 1) {
      await footer.scrollIntoViewIfNeeded();
      const snapshot = await footer.evaluate(() => {
        const footerEl = document.querySelector('footer.petshop-institutional-footer');
        const grid = footerEl.querySelector('.petshop-institutional-footer__grid');
        const trust = footerEl.querySelector('.petshop-institutional-footer__trust');
        const trustGrid = footerEl.querySelector('.petshop-institutional-footer__trust-grid');
        const trustItems = [...footerEl.querySelectorAll('.petshop-institutional-footer__trust-item')];
        const trustTitles = [...footerEl.querySelectorAll('.petshop-institutional-footer__trust-copy h3')];
        const trustIcons = [...footerEl.querySelectorAll('.petshop-institutional-footer__trust-icon')];
        const decoratedHeadings = [
          footerEl.querySelector('.petshop-institutional-footer__contact h2'),
          footerEl.querySelector('.petshop-institutional-footer__categories h2'),
          footerEl.querySelector('.petshop-institutional-footer__policies h2'),
        ].filter(Boolean);
        const socialHeading = footerEl.querySelector('.petshop-institutional-footer__social h2');
        const legal = footerEl.querySelector('.petshop-institutional-footer__legal');
        const legalIcon = footerEl.querySelector('.petshop-institutional-footer__legal-icon');
        const legalParagraphs = [...footerEl.querySelectorAll('.petshop-institutional-footer__legal-copy p')];
        const interactiveTargets = [...footerEl.querySelectorAll('a')].map((link) => {
          const rect = link.getBoundingClientRect();
          return { text: link.textContent.trim(), width: rect.width, height: rect.height };
        });

        const columns = (node) => {
          const template = node ? getComputedStyle(node).gridTemplateColumns : '';
          return template.trim().split(/\s+/).filter(Boolean).length;
        };
        const pseudo = (node, selector) => {
          const style = getComputedStyle(node, selector);
          return {
            content: style.content,
            display: style.display,
            width: style.width,
            height: style.height,
            backgroundColor: style.backgroundColor,
          };
        };

        return {
          footerBg: getComputedStyle(footerEl).backgroundColor,
          trustBg: trust ? getComputedStyle(trust).backgroundColor : '',
          trustBorderTop: trust ? getComputedStyle(trust).borderTopColor : '',
          trustBorderTopWidth: trust ? Number.parseFloat(getComputedStyle(trust).borderTopWidth) : 0,
          trustBorderBottom: trust ? getComputedStyle(trust).borderBottomColor : '',
          trustBorderBottomWidth: trust ? Number.parseFloat(getComputedStyle(trust).borderBottomWidth) : 0,
          gridColumns: columns(grid),
          trustColumns: columns(trustGrid),
          trustIcons: trustIcons.map((node) => ({
            icon: node.getAttribute('data-icon'),
            color: getComputedStyle(node).color,
          })),
          trustTitles: trustTitles.map((node) => getComputedStyle(node).color),
          trustItemBorders: trustItems.map((node) => ({
            leftWidth: Number.parseFloat(getComputedStyle(node).borderLeftWidth),
            leftStyle: getComputedStyle(node).borderLeftStyle,
          })),
          decoratedHeadings: decoratedHeadings.map((node) => ({
            text: node.textContent.trim(),
            color: getComputedStyle(node).color,
            after: pseudo(node, '::after'),
          })),
          socialHeading: socialHeading ? {
            text: socialHeading.textContent.trim(),
            color: getComputedStyle(socialHeading).color,
            after: pseudo(socialHeading, '::after'),
          } : null,
          legalIconColor: legalIcon ? getComputedStyle(legalIcon).color : '',
          legalParagraphCount: legalParagraphs.length,
          legalText: legal ? legal.textContent.replace(/\s+/g, ' ').trim() : '',
          footerOverflow: Math.max(0, footerEl.scrollWidth - footerEl.clientWidth),
          pageOverflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
          smallTargets: interactiveTargets.filter((target) => target.width < 44 || target.height < 44),
        };
      });

      recordFailure(snapshot.gridColumns === viewport.grid, `${viewport.name}: grade principal deveria ter ${viewport.grid} coluna(s), recebeu ${snapshot.gridColumns}`);
      recordFailure(snapshot.trustColumns === viewport.trust, `${viewport.name}: grade de selos deveria ter ${viewport.trust} coluna(s), recebeu ${snapshot.trustColumns}`);
      recordFailure(snapshot.footerOverflow <= 1, `${viewport.name}: overflow horizontal no rodape de ${snapshot.footerOverflow}px`);
      recordFailure(snapshot.pageOverflow <= 1, `${viewport.name}: overflow horizontal na pagina de ${snapshot.pageOverflow}px`);
      recordFailure(closeColor(snapshot.trustBg, snapshot.footerBg), `${viewport.name}: faixa de selos deveria usar o mesmo carvao do rodape (${snapshot.trustBg} vs ${snapshot.footerBg})`);
      recordFailure(!isTeal900(snapshot.trustBg), `${viewport.name}: faixa de selos nao deveria usar teal-900 solido`);
      recordFailure(snapshot.trustBorderTopWidth >= 1, `${viewport.name}: filete superior da faixa de selos ausente`);
      recordFailure(snapshot.trustBorderBottomWidth >= 1, `${viewport.name}: filete inferior da faixa de selos ausente`);
      recordFailure(snapshot.trustBorderTop === snapshot.trustBorderBottom, `${viewport.name}: filetes superior/inferior deveriam ter a mesma cor`);
      recordFailure(JSON.stringify(snapshot.trustIcons.map((item) => item.icon)) === JSON.stringify(['shield', 'award', 'lock', 'truck']), `${viewport.name}: ordem dos selos incorreta (${snapshot.trustIcons.map((item) => item.icon).join(', ')})`);
      recordFailure(snapshot.trustIcons.every((item) => item.color === snapshot.decoratedHeadings[0]?.color), `${viewport.name}: icones dos selos deveriam usar o teal dos titulos`);
      recordFailure(snapshot.trustTitles.every((color) => color === 'rgb(255, 255, 255)' || color === 'rgba(255, 255, 255, 1)'), `${viewport.name}: titulos dos selos deveriam ser claros/brancos`);
      recordFailure(snapshot.decoratedHeadings.length === 3, `${viewport.name}: deveria encontrar 3 titulos de coluna decorados`);
      for (const heading of snapshot.decoratedHeadings) {
        recordFailure(heading.after.content !== 'none' && heading.after.display === 'block', `${viewport.name}: ${heading.text} deveria ter sublinhado`);
        recordFailure(Number.parseFloat(heading.after.height) >= 2, `${viewport.name}: sublinhado de ${heading.text} deveria ser visivel`);
        recordFailure(heading.after.backgroundColor === heading.color, `${viewport.name}: sublinhado de ${heading.text} deveria usar teal`);
      }
      if (snapshot.socialHeading) {
        recordFailure(snapshot.socialHeading.after.content === 'none' || snapshot.socialHeading.after.display === 'inline', `${viewport.name}: Siga-nos nao deveria receber sublinhado de coluna`);
      }
      recordFailure(snapshot.legalIconColor === snapshot.decoratedHeadings[0]?.color, `${viewport.name}: icone legal deveria usar teal`);
      recordFailure(snapshot.legalParagraphCount <= 2, `${viewport.name}: faixa legal deveria ter no maximo 2 paragrafos, recebeu ${snapshot.legalParagraphCount}`);

      if (viewport.width === 1440) {
        const dividers = snapshot.trustItemBorders.slice(1).filter((border) => border.leftWidth >= 1 && border.leftStyle !== 'none').length;
        recordFailure(snapshot.trustItemBorders[0]?.leftWidth === 0, `${viewport.name}: primeiro selo nao deveria ter divisoria antes`);
        recordFailure(dividers === Math.max(0, snapshot.trustItemBorders.length - 1), `${viewport.name}: desktop deveria ter divisorias verticais entre selos`);
      }
      if (viewport.width === 1024) {
        const leftBorders = snapshot.trustItemBorders.map((border) => border.leftWidth >= 1 && border.leftStyle !== 'none');
        recordFailure(leftBorders[0] === false && leftBorders[2] === false, `${viewport.name}: inicio das linhas 2x2 nao deveria ter divisoria vertical`);
        recordFailure(leftBorders[1] === true && leftBorders[3] === true, `${viewport.name}: segundo item de cada linha 2x2 deveria ter divisoria vertical`);
      }
      if (viewport.width === 390) {
        recordFailure(snapshot.trustItemBorders.every((border) => border.leftWidth === 0 || border.leftStyle === 'none'), `${viewport.name}: mobile nao deveria manter divisorias verticais`);
        recordFailure(snapshot.smallTargets.length === 0, `${viewport.name}: alvos interativos menores que 44x44: ${JSON.stringify(snapshot.smallTargets)}`);
      }

      await footer.screenshot({ path: path.join(evidenceDir, `${viewport.name}-footer.png`) });
    }

    recordFailure(errors.length === 0, `${viewport.name}: ${errors.length} erro(s) JavaScript: ${errors.join('; ')}`);
    await page.close();
  }
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error('validate-033-footer-browser falhou:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('validate-033-footer-browser: passed');
