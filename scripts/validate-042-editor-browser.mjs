import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { launchBrowser, routeCanonicalNavigation, createEvidenceDirectory } from './lib/browser-helpers.mjs';
const fixture = JSON.parse(fs.readFileSync('.local/042-editor-fixture.json', 'utf8'));
const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const browser = await launchBrowser();
const evidence = createEvidenceDirectory('042');
try {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  await routeCanonicalNavigation(page, baseUrl);
  await page.goto(baseUrl + '/wp-login.php');
  await page.locator('#user_login').fill(fixture.login);
  await page.locator('#user_pass').fill(fixture.password);
  await page.locator('#wp-submit').click();
  await page.waitForURL(/\/wp-admin(?:\/|$)/, { timeout: 30000 });
  await page.goto(baseUrl + '/wp-admin/post.php?post=' + fixture.pageId + '&action=edit');
  await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks()?.length > 0);
  const initial = await page.evaluate(() => {
    const flatten = blocks => blocks.flatMap(b => [b, ...flatten(b.innerBlocks || [])]);
    const blocks = flatten(wp.data.select('core/block-editor').getBlocks());
    return { invalid: blocks.filter(b => b.isValid === false).map(b => b.name), names: blocks.map(b => b.name) };
  });
  assert.deepEqual(initial.invalid, [], 'Gutenberg rejects saved block markup');
  assert(initial.names.includes('core/image') && initial.names.includes('core/heading') && initial.names.includes('core/shortcode'));
  await page.evaluate(() => wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false));
  await page.screenshot({ path: path.join(evidence, 'gutenberg.png'), fullPage: true });
  await page.evaluate(async () => {
    const flatten = blocks => blocks.flatMap(b => [b, ...flatten(b.innerBlocks || [])]);
    const blocks = flatten(wp.data.select('core/block-editor').getBlocks());
    const dispatch = wp.data.dispatch('core/block-editor');
    for (const [className, content] of [
      ['petshop-account-intro', 'Introdução editada no Gutenberg 042.'],
      ['petshop-account-benefit petshop-account-benefit--orders', 'Benefício editado no Gutenberg 042.'],
      ['petshop-account-affection', 'Frase editada no Gutenberg 042.'],
    ]) {
      const block = blocks.find(b => b.attributes.className === className);
      if (!block) throw new Error('Missing editable block ' + className);
      dispatch.updateBlockAttributes(block.clientId, { content });
    }
    const image = blocks.find(b => b.name === 'core/image');
    dispatch.updateBlockAttributes(image.clientId, { alt: 'Alt editado no Gutenberg 042.', url: image.attributes.url + '?editor042=1' });
    await wp.data.dispatch('core/editor').savePost();
  });
  await page.reload();
  await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks()?.length > 0);
  const saved = await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent());
  for (const text of ['Introdução editada no Gutenberg', 'Benefício editado no Gutenberg', 'Frase editada no Gutenberg', 'Alt editado no Gutenberg', '?editor042=1']) assert(saved.includes(text), text + ' did not persist');
  await page.screenshot({ path: path.join(evidence, 'gutenberg-saved.png'), fullPage: true });
  console.log('042 editor: valid native blocks, editable copy, image URL/alt and save/reload passed.');
} finally {
  await browser.close();
}
