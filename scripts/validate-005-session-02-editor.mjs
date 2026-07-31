import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const container = process.env.PETSHOP_CONTAINER || 'petshop-storefront-preview';
const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const executablePath = process.env.PETSHOP_CHROME || 'C:/Users/lucas/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe';
const wpArgs = ['--path=/var/www/html', '--allow-root'];
const wp = (...args) => execFileSync('docker', ['exec', container, 'wp', ...args, ...wpArgs], { encoding: 'utf8' }).trim();

const homeId = wp('option', 'get', 'page_on_front');
const homeContent = wp('post', 'get', homeId, '--field=post_content');
const tailStart = homeContent.indexOf('<!-- wp:group {"className":"petshop-section"');
if (tailStart < 0) throw new Error('Nao foi possivel isolar hero e beneficios para o teste do editor.');
const editorContent = homeContent.slice(0, tailStart);
const currentHeroId = Number(editorContent.match(/<!-- wp:cover \{[^\n]*"id":(\d+)/)?.[1] || 0);
if (!currentHeroId) throw new Error('ID atual do hero ausente para o teste do editor.');
const currentLogo = Number(wp('theme', 'mod', 'get', 'custom_logo', '--field=value')) || 0;
const attachmentJson = wp('post', 'list', '--post_type=attachment', '--post_status=inherit', '--post_mime_type=image', '--posts_per_page=1', `--post__not_in=${currentHeroId},${currentLogo}`, '--fields=ID,guid', '--format=json');
const [attachment] = JSON.parse(attachmentJson);
if (!attachment) throw new Error('Imagem alternativa ausente para o teste do editor.');
if (Number(attachment.ID) === currentHeroId) throw new Error('Teste do editor selecionou novamente a imagem atual do hero.');

let pageId = 0;
const browser = await chromium.launch({ headless: true, executablePath });
try {
  pageId = Number(wp(
    'post', 'create', '--post_type=page', '--post_status=publish',
    '--post_title=Teste Gutenberg Sessao 02', `--post_content=${editorContent}`, '--porcelain'
  ));
  if (!pageId) throw new Error('Pagina temporaria do editor nao foi criada.');

  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(process.env.PETSHOP_ADMIN_USER || 'admin');
  await page.locator('#user_pass').fill(process.env.PETSHOP_ADMIN_PASSWORD || 'password');
  await Promise.all([
    page.waitForURL(/wp-admin/, { timeout: 20000 }),
    page.locator('#wp-submit').click(),
  ]);
  await page.goto(`${baseUrl}/wp-admin/post.php?post=${pageId}&action=edit`, { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks()?.length > 0, null, { timeout: 20000 });

  const editResult = await page.evaluate(({ attachmentId, attachmentUrl }) => {
    const select = window.wp.data.select('core/block-editor');
    const dispatch = window.wp.data.dispatch('core/block-editor');
    const flatten = (blocks) => blocks.flatMap((block) => [block, ...flatten(block.innerBlocks || [])]);
    const blocks = flatten(select.getBlocks());
    const invalidBlocks = blocks.filter((block) => !select.isBlockValid(block.clientId));
    const invalid = invalidBlocks.map((block) => block.name);
    const hero = blocks.find((block) => block.name === 'core/cover' && block.attributes.className?.includes('petshop-hero'));
    const heading = blocks.find((block) => block.name === 'core/heading' && block.attributes.level === 1);
    const buttons = blocks.filter((block) => block.name === 'core/button');
    const benefit = blocks.find((block) => block.name === 'core/paragraph' && String(block.attributes.content) === 'Pronta entrega');
    if (!hero || !heading || buttons.length !== 2 || !benefit) {
      return {
        invalid,
        updated: false,
        summary: blocks.map((block) => ({ name: block.name, level: block.attributes.level, content: String(block.attributes.content || ''), className: block.attributes.className || '' })),
        serializedHero: hero ? window.wp.blocks.serialize(window.wp.blocks.createBlock('core/cover', hero.attributes, hero.innerBlocks)) : '',
      };
    }
    dispatch.updateBlockAttributes(hero.clientId, { id: attachmentId, url: attachmentUrl, alt: 'Alt Gutenberg sentinela' });
    dispatch.updateBlockAttributes(heading.clientId, { content: 'Titulo Gutenberg sentinela' });
    dispatch.updateBlockAttributes(buttons[0].clientId, { url: '/colecoes/' });
    dispatch.updateBlockAttributes(buttons[1].clientId, { url: '/atendimento/' });
    dispatch.updateBlockAttributes(benefit.clientId, { content: 'Beneficio Gutenberg sentinela' });
    return { invalid, updated: true };
  }, { attachmentId: Number(attachment.ID), attachmentUrl: attachment.guid });
  if (editResult.invalid.length || !editResult.updated) {
    throw new Error(`Blocos invalidos ou controles ausentes: ${JSON.stringify(editResult)}`);
  }

  const updateButton = page.locator('.editor-post-publish-button').last();
  await updateButton.waitFor({ state: 'visible', timeout: 15000 });
  await Promise.all([
    page.waitForResponse((response) => response.url().includes(`/wp-json/wp/v2/pages/${pageId}`) && response.request().method() === 'POST', { timeout: 20000 }),
    updateButton.click(),
  ]);

  const saved = wp('post', 'get', String(pageId), '--field=post_content');
  for (const sentinel of [
    'Titulo Gutenberg sentinela', 'Alt Gutenberg sentinela', 'Beneficio Gutenberg sentinela',
    'href="/colecoes/"', 'href="/atendimento/"', `"id":${attachment.ID}`,
  ]) {
    if (!saved.includes(sentinel)) throw new Error(`Edicao Gutenberg nao persistiu: ${sentinel}`);
  }
  console.log('Gutenberg valido: blocos abriram sem invalidacao e titulo, imagem, alt, URLs e beneficio foram salvos.');
} finally {
  await browser.close();
  if (pageId > 0) wp('post', 'delete', String(pageId), '--force');
}
