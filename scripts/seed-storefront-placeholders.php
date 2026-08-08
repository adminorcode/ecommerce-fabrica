<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute este seed com WP-CLI e WooCommerce ativo.');
}

$manifestPath = __DIR__ . '/data/004b-products.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * @param array<string, string> $image
 */
function petshop_004b_ensure_image(string $key, array $image): int
{
    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => '_petshop_placeholder_key',
        'meta_value' => $key,
    ]);
    if ($existing !== []) {
        return (int) $existing[0];
    }

    $temporary = download_url($image['url'], 60);
    if (is_wp_error($temporary)) {
        throw new RuntimeException("Falha ao baixar {$key}: {$temporary->get_error_message()}");
    }

    $file = [
        'name' => "petshop-004b-{$key}.jpg",
        'tmp_name' => $temporary,
    ];
    $attachmentId = media_handle_sideload($file, 0, $image['alt']);
    if (is_wp_error($attachmentId)) {
        @unlink($temporary);
        throw new RuntimeException("Falha ao importar {$key}: {$attachmentId->get_error_message()}");
    }

    update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field($image['alt']));
    update_post_meta($attachmentId, '_petshop_placeholder_key', $key);
    update_post_meta($attachmentId, '_petshop_placeholder_source', esc_url_raw($image['source']));
    update_post_meta($attachmentId, '_petshop_placeholder_license', 'Pexels License');
    update_post_meta($attachmentId, '_petshop_placeholder_author', sanitize_text_field($image['author']));

    return (int) $attachmentId;
}

$imageIds = [];
foreach ($manifest['images'] as $key => $image) {
    $imageIds[$key] = petshop_004b_ensure_image((string) $key, $image);
}

$created = 0;
$preserved = 0;
$categoryImages = [];

foreach ($manifest['products'] as $record) {
    $categorySlugs = isset($record['categories'])
        ? array_values(array_unique(array_map('strval', (array) $record['categories'])))
        : [(string) $record['category']];
    $categoryIds = [];
    foreach ($categorySlugs as $categorySlug) {
        $term = get_term_by('slug', $categorySlug, 'product_cat');
        if (!$term instanceof WP_Term) {
            throw new RuntimeException("Categoria ausente: {$categorySlug}");
        }
        $categoryIds[] = (int) $term->term_id;
        $categoryImages[(int) $term->term_id] ??= (int) $imageIds[$record['image']];
    }

    $existingId = wc_get_product_id_by_sku((string) $record['sku']);
    if ($existingId > 0) {
        $existingProduct = wc_get_product($existingId);
        if (
            $existingProduct instanceof WC_Product
            && (bool) get_post_meta($existingId, '_petshop_placeholder_004b', true)
            && $existingProduct->get_catalog_visibility() !== 'visible'
        ) {
            $existingProduct->set_catalog_visibility('visible');
            $existingProduct->save();
        }
        ++$preserved;
        continue;
    }

    $product = new WC_Product_Simple();
    $product->set_name(sanitize_text_field((string) $record['name']));
    $product->set_sku(sanitize_text_field((string) $record['sku']));
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_regular_price(wc_format_decimal($record['price']));
    $product->set_manage_stock(true);
    $product->set_stock_quantity(max(0, (int) $record['stock']));
    $product->set_stock_status((int) $record['stock'] > 0 ? 'instock' : 'outofstock');
    $product->set_description(wp_kses_post((string) $record['description']));
    $product->set_short_description(
        wp_kses_post(
            '<p>' . esc_html(wp_trim_words(wp_strip_all_tags((string) $record['description']), 24)) . '</p>'
        )
    );
    $product->set_category_ids($categoryIds);
    $product->set_image_id((int) $imageIds[$record['image']]);
    $product->add_meta_data('_petshop_placeholder_004b', '1', true);
    $product->add_meta_data('_petshop_placeholder_source_workbook', $manifest['sourceWorkbook'], true);
    $productId = $product->save();

    ++$created;
}

foreach ($categoryImages as $termId => $attachmentId) {
    if ((int) get_term_meta($termId, 'thumbnail_id', true) === 0) {
        update_term_meta($termId, 'thumbnail_id', $attachmentId);
    }
}

foreach (($manifest['categoryDescriptions'] ?? []) as $slug => $description) {
    $term = get_term_by('slug', (string) $slug, 'product_cat');
    if ($term instanceof WP_Term && trim((string) $term->description) === '') {
        wp_update_term(
            $term->term_id,
            'product_cat',
            ['description' => wp_kses_post((string) $description)]
        );
    }
}

echo "placeholder-seed: created={$created} preserved={$preserved} images=" . count($imageIds) . "\n";
