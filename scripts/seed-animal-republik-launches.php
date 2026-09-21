<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute este seed com WP-CLI e WooCommerce ativo.');
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$manifestPath = __DIR__ . '/data/animal-republik-lancamentos.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

/**
 * @param array{name: string, slug: string} $category
 */
function petshop_ar_ensure_category(array $category): int
{
    $term = get_term_by('slug', sanitize_title((string) $category['slug']), 'product_cat');
    if (!$term instanceof WP_Term) {
        $created = wp_insert_term(
            sanitize_text_field((string) $category['name']),
            'product_cat',
            ['slug' => sanitize_title((string) $category['slug'])]
        );
        if (is_wp_error($created)) {
            throw new RuntimeException($created->get_error_message());
        }
        $termId = (int) $created['term_id'];
    } else {
        $termId = (int) $term->term_id;
    }

    update_term_meta($termId, 'petshop_visible_in_menu', 0);
    update_term_meta($termId, 'petshop_seasonal', 0);
    update_term_meta($termId, 'petshop_menu_order', 90);

    return $termId;
}

/**
 * @param list<array{name: string, slug: string}> $tags
 * @return list<int>
 */
function petshop_ar_ensure_tags(array $tags): array
{
    $ids = [];
    foreach ($tags as $tag) {
        $term = get_term_by('slug', sanitize_title((string) $tag['slug']), 'product_tag');
        if (!$term instanceof WP_Term) {
            $created = wp_insert_term(
                sanitize_text_field((string) $tag['name']),
                'product_tag',
                ['slug' => sanitize_title((string) $tag['slug'])]
            );
            if (is_wp_error($created)) {
                throw new RuntimeException($created->get_error_message());
            }
            $ids[] = (int) $created['term_id'];
        } else {
            $ids[] = (int) $term->term_id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * @param array{name: string, image: string, sourceId: string, sourceUrl: string} $record
 */
function petshop_ar_ensure_image(array $record): int
{
    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => '_petshop_animal_republik_source_id',
        'meta_value' => sanitize_text_field((string) $record['sourceId']),
    ]);
    if ($existing !== []) {
        return (int) $existing[0];
    }

    $temporary = download_url(esc_url_raw((string) $record['image']), 60);
    if (is_wp_error($temporary)) {
        throw new RuntimeException('Falha ao baixar imagem Animal Republik ' . $record['sourceId'] . ': ' . $temporary->get_error_message());
    }

    $extension = pathinfo((string) parse_url((string) $record['image'], PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
    $file = [
        'name' => 'animal-republik-' . sanitize_file_name((string) $record['sourceId']) . '.' . sanitize_file_name($extension),
        'tmp_name' => $temporary,
    ];
    $attachmentId = media_handle_sideload($file, 0, sanitize_text_field((string) $record['name']));
    if (is_wp_error($attachmentId)) {
        @unlink($temporary);
        throw new RuntimeException('Falha ao importar imagem Animal Republik ' . $record['sourceId'] . ': ' . $attachmentId->get_error_message());
    }

    update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field((string) $record['name']));
    update_post_meta($attachmentId, '_petshop_animal_republik_source_id', sanitize_text_field((string) $record['sourceId']));
    update_post_meta($attachmentId, '_petshop_animal_republik_source_url', esc_url_raw((string) $record['sourceUrl']));
    update_post_meta($attachmentId, '_petshop_image_authorization_note', 'Cliente informou autorização para cadastrar a imagem na aplicação.');

    return (int) $attachmentId;
}

/**
 * @param array{name: string, sku: string, price: string, sourceId: string, sourceUrl: string} $record
 */
function petshop_ar_ensure_product(array $record, int $categoryId, array $tagIds, int $imageId, int $menuOrder): int
{
    $productId = wc_get_product_id_by_sku((string) $record['sku']);
    $product = $productId > 0 ? wc_get_product($productId) : null;
    if ($product instanceof WC_Product && (string) $product->get_meta('_petshop_animal_republik_source_id') !== (string) $record['sourceId']) {
        throw new RuntimeException('SKU Animal Republik conflita com produto existente sem assinatura: ' . $record['sku']);
    }

    if (!$product instanceof WC_Product) {
        $product = new WC_Product_Simple();
        $product->set_name(sanitize_text_field((string) $record['name']));
        $product->set_sku(sanitize_text_field((string) $record['sku']));
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_regular_price(wc_format_decimal((string) $record['price']));
        $product->set_manage_stock(false);
        $product->set_stock_status('instock');
        $product->set_description(
            '<p>' . esc_html__('Produto Animal Republik cadastrado a partir da lista de lançamentos autorizada para uso nesta loja.', 'petshop-core') . '</p>'
        );
        $product->set_short_description(
            '<p>' . esc_html__('Lançamento Animal Republik com título, imagem e preço editáveis no WooCommerce.', 'petshop-core') . '</p>'
        );
    }

    $categoryIds = array_values(array_unique(array_merge($product->get_category_ids(), [$categoryId])));
    $product->set_category_ids($categoryIds);
    if ($product->get_image_id() <= 0 || (string) $product->get_meta('_petshop_animal_republik_source_id') === (string) $record['sourceId']) {
        $product->set_image_id($imageId);
    }
    $product->set_menu_order($menuOrder);
    $product->update_meta_data('_petshop_animal_republik_source_id', sanitize_text_field((string) $record['sourceId']));
    $product->update_meta_data('_petshop_animal_republik_source_url', esc_url_raw((string) $record['sourceUrl']));
    $product->update_meta_data('_petshop_image_authorization_note', 'Cliente informou autorização para cadastrar imagem e título na aplicação.');
    $productId = $product->save();

    wp_set_object_terms((int) $productId, $tagIds, 'product_tag', true);

    return (int) $productId;
}

function petshop_ar_sync_page_grid(int $categoryId): bool
{
    $page = get_page_by_path('animal-republik');
    if (!$page instanceof WP_Post) {
        return false;
    }

    $block = Petshop\Core\ProductGridBlock::blockMarkup([
        'selectionMode' => 'category',
        'categoryIds' => [$categoryId],
        'limit' => 20,
        'columns' => 4,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ]);

    $originalContent = (string) $page->post_content;
    $content = str_replace(
        [
            'A Animal Republik está registrada aqui como fornecedor oficial. A vitrine abaixo é uma seleção inicial administrável; substitua pelos produtos Animal Republik cadastrados no WooCommerce assim que a curadoria final estiver aprovada.',
            'Produtos selecionados',
            'Edite esta vitrine no Gutenberg para adicionar, remover ou reordenar produtos reais do catálogo.',
            'A Animal Republik está registrada aqui como fornecedor oficial. A vitrine abaixo usa produtos cadastrados no WooCommerce na categoria Animal Republik, com imagens, títulos, preços e estoque administráveis no painel.',
        ],
        [
            '',
            'Lançamentos Animal Republik',
            'Edite os produtos no WooCommerce ou ajuste a categoria do bloco no Gutenberg para controlar esta vitrine.',
            '',
        ],
        $originalContent
    );
    $content = preg_replace(
        '/<!-- wp:group \{"tagName":"section","className":"petshop-section petshop-commercial-context".*?<!-- \/wp:group -->/s',
        '',
        (string) $content,
        1
    );
    $content = petshop_ar_sync_view_all_link((string) $content);
    $updated = preg_replace('/<!-- wp:petshop\/product-grid\s+\{.*?\}\s+\/-->/s', $block, $content, 1, $count);
    if (!is_string($updated) || $count < 1) {
        return false;
    }
    if ($updated === $originalContent) {
        return false;
    }

    $result = wp_update_post([
        'ID' => (int) $page->ID,
        'post_content' => wp_slash($updated),
    ], true);

    if (is_wp_error($result)) {
        throw new RuntimeException($result->get_error_message());
    }

    return true;
}

function petshop_ar_sync_view_all_link(string $content): string
{
    $shopPath = (string) wp_parse_url((string) wc_get_page_permalink('shop'), PHP_URL_PATH);
    if ($shopPath === '') {
        $shopPath = '/loja/';
    }

    $viewAllUrl = add_query_arg(
        ['product_cat' => ['animal-republik']],
        $shopPath
    );
    $link = '<!-- wp:paragraph {"className":"petshop-section-head__cta"} --><p class="petshop-section-head__cta"><a class="petshop-section-head__link" href="' . esc_url($viewAllUrl) . '">Ver tudo</a></p><!-- /wp:paragraph -->';

    $content = preg_replace(
        '/<!-- wp:paragraph \{"className":"petshop-section-head__cta"\} -->.*?<!-- \/wp:paragraph -->/s',
        '',
        $content
    );
    if (!is_string($content)) {
        return '';
    }

    $updated = preg_replace(
        '/(<div class="wp-block-group petshop-section-head">.*?<!-- \/wp:heading -->)/s',
        '$1' . $link,
        $content,
        1
    );

    return is_string($updated) ? $updated : $content;
}

$categoryId = petshop_ar_ensure_category((array) $manifest['category']);
$tagIds = petshop_ar_ensure_tags((array) $manifest['tags']);
$created = 0;
$preserved = 0;
$images = 0;
$productIds = [];

foreach ((array) $manifest['products'] as $index => $record) {
    $record = (array) $record;
    $existingId = wc_get_product_id_by_sku((string) $record['sku']);
    $imageId = petshop_ar_ensure_image($record);
    ++$images;
    $productId = petshop_ar_ensure_product($record, $categoryId, $tagIds, $imageId, $index + 1);
    $productIds[] = $productId;
    $existingId > 0 ? ++$preserved : ++$created;
}

$thumbnailId = 0;
if ($productIds !== []) {
    $firstProduct = wc_get_product($productIds[0]);
    if ($firstProduct instanceof WC_Product) {
        $thumbnailId = (int) $firstProduct->get_image_id();
    }
}
if ($thumbnailId > 0 && (int) get_term_meta($categoryId, 'thumbnail_id', true) <= 0) {
    update_term_meta($categoryId, 'thumbnail_id', $thumbnailId);
}

$pageSynced = petshop_ar_sync_page_grid($categoryId);

echo "animal-republik-seed: created={$created} preserved={$preserved} products=" . count($productIds) . " images={$images} page_grid=" . ($pageSynced ? 'updated' : 'preserved') . "\n";
