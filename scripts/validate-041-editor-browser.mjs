import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { launchBrowser, routeCanonicalNavigation, createEvidenceDirectory } from './lib/browser-helpers.mjs';
const base = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';
const fixture = JSON.parse(fs.readFileSync(path.resolve('.local/041-editor-fixture.json'), 'utf8'));
const evidence = createEvidenceDirectory('041-editor');
const browser = await launchBrowser();
try {
 const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
 await routeCanonicalNavigation(page, base);
 await page.goto(`${base}/wp-login.php`);
 await page.locator('#user_login').fill(fixture.login);
 await page.locator('#user_pass').fill(fixture.password);
 await Promise.all([page.waitForNavigation(), page.locator('#wp-submit').click()]);
 await page.goto(`${base}/wp-admin/post.php?post=${fixture.pageId}&action=edit`, { waitUntil: 'domcontentloaded' });
 await page.waitForFunction((id) => window.wp?.data?.select('core/editor')?.getCurrentPostId() === id
   && window.wp.data.select('core/block-editor').getBlocks().length > 0, fixture.pageId);
 await page.waitForFunction(() => {
   const canvas = document.querySelector('iframe[name="editor-canvas"]')?.contentDocument || document;
   const preview = canvas.querySelector('.petshop-cart-shipping.is-editor-preview');
   return preview?.getBoundingClientRect().height > 0 && preview.textContent.includes('Calcular entrega por CEP');
 });
 const changed = await page.evaluate(async (fixture) => {
   const store = wp.data.select('core/block-editor');
   const actions = wp.data.dispatch('core/block-editor');
   const flatten = (blocks) => blocks.flatMap((block) => [block, ...flatten(block.innerBlocks || [])]);
   const blocks = flatten(store.getBlocks());
   const quote = blocks.find((block) => block.name === 'petshop/cart-shipping-quote');
   if (!quote) throw Error('Visible CEP block missing from editor');
   const paragraph = blocks.find((block) => String(block.attributes.content || '').includes('Synthetic editable paragraph 041'));
   actions.updateBlockAttributes(paragraph.clientId, { content: 'Synthetic client revision 041' });
   const image = wp.blocks.createBlock('core/image', { id: fixture.media[0].id, url: fixture.media[0].url, alt: 'Synthetic initial alt' });
   actions.insertBlocks(image);
   actions.updateBlockAttributes(image.clientId, { id: fixture.media[1].id, url: fixture.media[1].url, alt: 'Synthetic client alt 041' });
   const root = store.getBlockRootClientId(quote.clientId);
   actions.moveBlocksToPosition([quote.clientId], root, root, 0);
   await wp.data.dispatch('core/editor').savePost();
   return { quoteCount: flatten(store.getBlocks()).filter((block) => block.name === quote.name).length,
     movedFirst: store.getBlockOrder(root)[0] === quote.clientId };
 }, fixture);
 assert.equal(changed.quoteCount, 1); assert.equal(changed.movedFirst, true);
 await page.reload({ waitUntil: 'domcontentloaded' });
 await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks().length > 0);
 const persisted = await page.evaluate(async () => {
   const flatten = (blocks) => blocks.flatMap((block) => [block, ...flatten(block.innerBlocks || [])]);
   const store = wp.data.select('core/block-editor');
   const blocks = flatten(store.getBlocks());
   const quote = blocks.find((block) => block.name === 'petshop/cart-shipping-quote');
   const image = blocks.find((block) => block.name === 'core/image' && block.attributes.alt === 'Synthetic client alt 041');
   const root = store.getBlockRootClientId(quote.clientId);
   const result = { text: blocks.some((block) => String(block.attributes.content || '') === 'Synthetic client revision 041'),
     image: image?.attributes, movedFirst: store.getBlockOrder(root)[0] === quote.clientId };
   wp.data.dispatch('core/block-editor').removeBlocks([quote.clientId], false);
   await wp.data.dispatch('core/editor').savePost();
   return result;
 });
 fs.writeFileSync(path.join(evidence, 'persistence.json'), JSON.stringify(persisted, null, 2));
 assert.equal(persisted.text, true, 'Edited paragraph must persist after reload');
 assert.equal(persisted.movedFirst, true, 'Reordered CEP block must persist after reload');
 assert.equal(persisted.image.id, fixture.media[1].id);
 await page.reload({ waitUntil: 'domcontentloaded' });
 await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks().length > 0);
 const content = await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent());
 assert(!content.includes('wp:petshop/cart-shipping-quote'), 'Client removal must persist');
 assert(content.includes('Synthetic client revision 041') && content.includes('Synthetic client alt 041'));
 await page.screenshot({ path: path.join(evidence, 'editor-client-revision.png'), fullPage: true });
 fs.writeFileSync(path.join(evidence, 'results.json'), JSON.stringify({ text: true, imageReplacement: true, alt: true, reorder: true, removal: true, scope: 'synthetic draft; migration persistence covered by PHP gate' }, null, 2));
 await page.unrouteAll({ behavior: 'ignoreErrors' });
 console.log('041 Gutenberg: text, image/alt, reorder, save/reload and removal passed');
} finally { await browser.close(); }
