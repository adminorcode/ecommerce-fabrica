import { createEvidenceDirectory, launchBrowser, routeCanonicalNavigation } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const evidenceDir = createEvidenceDirectory('023');
const failures = [];
const browser = await launchBrowser();

const recordFailure = (condition, message) => {
  if (!condition) {
    failures.push(message);
  }
};

try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await routeCanonicalNavigation(page, baseUrl);
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));

  const response = await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle', timeout: 30000 });
  recordFailure(response?.status() === 200, `Home HTTP ${response?.status()}`);

  const footer = page.locator('footer.petshop-institutional-footer');
  const footerCount = await footer.count();
  recordFailure(footerCount === 1, `Quantidade de rodapes institucionais: ${footerCount}`);

  if (footerCount === 1) {
    await footer.scrollIntoViewIfNeeded();
    const snapshot = await footer.evaluate((el) => {
      const style = getComputedStyle(el);
      const match = style.backgroundColor.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
      const r = match ? Number(match[1]) : 255;
      const g = match ? Number(match[2]) : 255;
      const b = match ? Number(match[3]) : 255;
      const grid = el.querySelector('.petshop-institutional-footer__grid');
      const gridStyle = grid ? getComputedStyle(grid) : null;
      const gridColumnCount = gridStyle
        ? gridStyle.gridTemplateColumns.trim().split(/\s+/).filter(Boolean).length
        : 0;
      const social = el.querySelector('.petshop-institutional-footer__social');
      const socialLink = el.querySelector('.petshop-institutional-footer__social-link');
      const socialStyle = socialLink ? getComputedStyle(socialLink) : null;
      const socialSize = socialLink ? socialLink.getBoundingClientRect() : null;
      const socialRadiusPx = socialStyle ? Number.parseFloat(socialStyle.borderRadius) : 0;
      const socialIsCircle = Boolean(
        socialSize
        && Math.abs(socialSize.width - socialSize.height) < 1
        && socialRadiusPx >= socialSize.width / 2 - 1
      );
      const contactIcons = [...el.querySelectorAll('.petshop-institutional-footer__contact-icon')].map((node) => node.getAttribute('data-icon'));
      const trustIcons = [...el.querySelectorAll('.petshop-institutional-footer__trust-icon')].map((node) => node.getAttribute('data-icon'));
      const extras = el.querySelectorAll('.petshop-institutional-footer__extras').length;
      const brandContainsSocial = Boolean(el.querySelector('.petshop-institutional-footer__brand .petshop-institutional-footer__social'));
      return {
        bg: style.backgroundColor,
        dark: r < 90 && g < 90 && b < 90,
        gridColumns: gridStyle?.gridTemplateColumns || '',
        gridColumnCount,
        socialIsCircle,
        hasSocial: Boolean(social),
        brandContainsSocial,
        extras,
        contactIcons,
        trustIcons,
      };
    });

    recordFailure(snapshot.dark, `Fundo do rodape deveria permanecer escuro (recebido: ${snapshot.bg})`);
    recordFailure(snapshot.extras === 0, `Coluna extras deveria estar ausente (encontrada: ${snapshot.extras})`);
    if (snapshot.hasSocial) {
      recordFailure(snapshot.brandContainsSocial, 'Redes sociais deveriam ficar na coluna da marca');
      recordFailure(snapshot.socialIsCircle, 'Icone social deveria ser circular');
    }
    if (snapshot.contactIcons.length > 0) {
      recordFailure(
        snapshot.contactIcons.includes('whatsapp') || snapshot.contactIcons.includes('envelope') || snapshot.contactIcons.includes('headset'),
        `Icones de atendimento inesperados: ${snapshot.contactIcons.join(', ')}`
      );
    }
    if (snapshot.trustIcons.length > 1) {
      recordFailure(
        new Set(snapshot.trustIcons).size === snapshot.trustIcons.length,
        `Selos deveriam ter icones distintos (recebido: ${snapshot.trustIcons.join(', ')})`
      );
    }
    recordFailure(
      snapshot.gridColumnCount >= 3,
      `Grade desktop deveria ter 4 colunas (recebido: ${snapshot.gridColumnCount} — ${snapshot.gridColumns})`
    );

    const overflow = await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth));
    recordFailure(overflow <= 1, `Overflow horizontal de ${overflow}px em 1440`);

    await page.setViewportSize({ width: 390, height: 844 });
    await footer.scrollIntoViewIfNeeded();
    const footerOverflow = await footer.evaluate((el) => Math.max(0, el.scrollWidth - el.clientWidth));
    recordFailure(footerOverflow <= 1, `Overflow horizontal do rodape de ${footerOverflow}px em 390`);
    await footer.screenshot({ path: `${evidenceDir}/footer-home-390.png` });

    await page.setViewportSize({ width: 1440, height: 900 });
    await footer.scrollIntoViewIfNeeded();
    await footer.screenshot({ path: `${evidenceDir}/footer-home.png` });
  }

  recordFailure(errors.length === 0, `${errors.length} erro(s) JavaScript: ${errors.join('; ')}`);
  await page.close();
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error('validate-023-footer-browser falhou:');
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log('validate-023-footer-browser: passed');
