import { launchBrowser } from './lib/browser-helpers.mjs';

const baseUrl = process.env.PETSHOP_BASE_URL || 'http://localhost:8888';

let pageId = 0;
const browser = await launchBrowser();

try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().includes('Failed to load resource')) {
      errors.push(message.text());
    }
  });

  await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(process.env.PETSHOP_ADMIN_USER || 'admin');
  await page.locator('#user_pass').fill(process.env.PETSHOP_ADMIN_PASSWORD || 'password');
  await Promise.all([
    page.waitForURL(/wp-admin/, { timeout: 20000 }),
    page.locator('#wp-submit').click(),
  ]);

  await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'domcontentloaded' });
  const setup = await page.evaluate(async () => {
    const productRoutes = ['/wp/v2/product?status=publish&per_page=2', '/wp/v2/products?status=publish&per_page=2'];
    let products = [];
    for (const path of productRoutes) {
      try {
        products = await window.wp.apiFetch({ path });
        if (Array.isArray(products) && products.length >= 2) break;
      } catch (error) {
        products = [];
      }
    }
    const categories = await window.wp.apiFetch({ path: '/wp/v2/product_cat?per_page=1&hide_empty=false' });
    const tempPage = await window.wp.apiFetch({
      path: '/wp/v2/pages',
      method: 'POST',
      data: {
        title: 'Teste Gutenberg Plano 016',
        status: 'publish',
        content: '<!-- wp:paragraph --><p>Teste 016</p><!-- /wp:paragraph -->',
      },
    });

    return {
      pageId: tempPage.id,
      productIds: products.slice(0, 2).map((product) => product.id),
      categoryIds: categories.slice(0, 1).map((category) => category.id),
    };
  });

  pageId = Number(setup.pageId);
  if (!pageId) throw new Error('Pagina temporaria do editor 016 nao foi criada.');
  if (setup.productIds.length < 2) throw new Error('Produtos publicados insuficientes para teste do editor 016.');
  if (setup.categoryIds.length < 1) throw new Error('Categorias insuficientes para teste do editor 016.');

  await page.goto(`${baseUrl}/wp-admin/post.php?post=${pageId}&action=edit`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks()?.length > 0, null, { timeout: 30000 });

  const productIds = [setup.productIds[1], setup.productIds[0]];
  const insertResult = await page.evaluate(({ productIds, categoryIds }) => {
    const { createBlock } = window.wp.blocks;
    const editorDispatch = window.wp.data.dispatch('core/block-editor');
    const editorSelect = window.wp.data.select('core/block-editor');
    const productGrid = createBlock('petshop/product-grid', {
      selectionMode: 'manual',
      productIds,
      categoryIds,
      limit: 2,
      columns: 3,
      orderby: 'menu_order',
      order: 'ASC',
    });
    editorDispatch.insertBlocks(productGrid);
    const blocks = editorSelect.getBlocks();
    const inserted = blocks.find((block) => block.name === 'petshop/product-grid');
    const invalid = blocks.filter((block) => !editorSelect.isBlockValid(block.clientId)).map((block) => block.name);
    return { inserted: !!inserted, invalid, attributes: inserted?.attributes || {} };
  }, { productIds, categoryIds: setup.categoryIds });

  if (!insertResult.inserted || insertResult.invalid.length > 0) {
    throw new Error(`Bloco product-grid invalido no editor: ${JSON.stringify(insertResult)}`);
  }

  const updateButton = page.locator('.editor-post-publish-button').last();
  await updateButton.waitFor({ state: 'visible', timeout: 15000 });
  await Promise.all([
    page.waitForResponse((response) => response.url().includes(`/wp-json/wp/v2/pages/${pageId}`) && response.request().method() === 'POST', { timeout: 30000 }),
    updateButton.click(),
  ]);

  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks()?.length > 0, null, { timeout: 30000 });

  const persisted = await page.evaluate(() => {
    const select = window.wp.data.select('core/block-editor');
    const blocks = select.getBlocks();
    const block = blocks.find((item) => item.name === 'petshop/product-grid');
    return {
      found: !!block,
      invalid: blocks.filter((item) => !select.isBlockValid(item.clientId)).map((item) => item.name),
      attributes: block?.attributes || {},
    };
  });

  if (!persisted.found || persisted.invalid.length > 0) {
    throw new Error(`Bloco product-grid nao persistiu valido: ${JSON.stringify(persisted)}`);
  }
  if (persisted.attributes.selectionMode !== 'manual') throw new Error('Modo manual nao persistiu.');
  if (JSON.stringify(persisted.attributes.productIds) !== JSON.stringify(productIds)) {
    throw new Error(`Ordem manual nao persistiu: ${JSON.stringify(persisted.attributes.productIds)}`);
  }
  if (persisted.attributes.limit !== 2 || persisted.attributes.columns !== 3) {
    throw new Error(`Quantidade/colunas nao persistiram: ${JSON.stringify(persisted.attributes)}`);
  }
  if (JSON.stringify(persisted.attributes.categoryIds) !== JSON.stringify(setup.categoryIds)) {
    throw new Error(`Categorias nao persistiram: ${JSON.stringify(persisted.attributes.categoryIds)}`);
  }
  if (persisted.attributes.orderby !== 'menu_order' || persisted.attributes.order !== 'ASC') {
    throw new Error(`Ordenacao nao persistiu: ${JSON.stringify(persisted.attributes)}`);
  }
  if (errors.length > 0) {
    throw new Error(`Erros no console do editor: ${errors.join(' | ')}`);
  }

  const saved = await page.evaluate(async ({ pageId }) => {
    const record = await window.wp.apiFetch({ path: `/wp/v2/pages/${pageId}?context=edit` });
    return record.content.raw;
  }, { pageId });
  if (!saved.includes('wp:petshop/product-grid') || saved.includes('wp:shortcode')) {
    throw new Error('Conteudo salvo nao contem somente o bloco product-grid esperado.');
  }

  console.log('Editor Gutenberg Plano 016 aprovado: bloco inseriu, salvou, recarregou e preservou atributos.');
} finally {
  if (pageId > 0) {
    const cleanup = await browser.newPage();
    try {
      await cleanup.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'domcontentloaded', timeout: 15000 });
      await cleanup.evaluate(async ({ pageId }) => {
        if (window.wp?.apiFetch) {
          await window.wp.apiFetch({ path: `/wp/v2/pages/${pageId}?force=true`, method: 'DELETE' });
        }
      }, { pageId });
    } catch (error) {
      console.warn(`Nao foi possivel remover a pagina temporaria ${pageId}: ${error.message}`);
    } finally {
      await cleanup.close();
    }
  }
  await browser.close();
}
