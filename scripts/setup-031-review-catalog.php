<?php

if (!defined('ABSPATH')) {
    exit;
}

$signature = '_petshop_plan_031_review_catalog';

$ensure_term = static function (string $taxonomy, string $name, string $slug): int {
    $existing = get_term_by('slug', $slug, $taxonomy);
    if ($existing instanceof WP_Term) {
        return (int) $existing->term_id;
    }

    $created = wp_insert_term($name, $taxonomy, ['slug' => $slug]);
    if (is_wp_error($created)) {
        $again = get_term_by('slug', $slug, $taxonomy);
        if ($again instanceof WP_Term) {
            return (int) $again->term_id;
        }
        WP_CLI::error($created->get_error_message());
    }

    return (int) $created['term_id'];
};

$ensure_term('pa_size', 'G', 'g');
$sizeIds = [
    'p' => $ensure_term('pa_size', 'P', 'p'),
    'm' => $ensure_term('pa_size', 'M', 'm'),
    'g' => $ensure_term('pa_size', 'G', 'g'),
];
$colorIds = [
    'azul' => $ensure_term('pa_color', 'Azul', 'azul'),
    'coral' => $ensure_term('pa_color', 'Coral', 'coral'),
];

$categoryId = $ensure_term('product_cat', 'Revisão 031', 'revisao-031');
$categoryIds = [$categoryId];
$bandanas = get_term_by('slug', 'bandanas', 'product_cat');
if ($bandanas instanceof WP_Term) {
    $categoryIds[] = (int) $bandanas->term_id;
}

$create_image = static function (string $slug, string $label, string $hex) use ($signature): int {
    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'meta_key' => $signature,
        'meta_value' => $slug,
        'fields' => 'ids',
        'posts_per_page' => 1,
    ]);
    if ($existing !== []) {
        return (int) $existing[0];
    }

    $upload = wp_upload_bits(
        "plan-031-review-{$slug}.svg",
        null,
        '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600"><rect width="600" height="600" fill="' . esc_attr($hex) . '"/><text x="300" y="315" text-anchor="middle" font-family="Arial" font-size="42" fill="#ffffff">' . esc_html($label) . '</text></svg>'
    );
    if (!empty($upload['error'])) {
        WP_CLI::error((string) $upload['error']);
    }

    $attachmentId = wp_insert_attachment([
        'post_mime_type' => 'image/svg+xml',
        'post_title' => "Revisão 031 {$label}",
        'post_status' => 'inherit',
    ], (string) $upload['file']);
    if (is_wp_error($attachmentId)) {
        WP_CLI::error($attachmentId->get_error_message());
    }

    update_post_meta((int) $attachmentId, $signature, $slug);
    update_post_meta((int) $attachmentId, '_wp_attachment_image_alt', "Revisão 031 {$label}");

    return (int) $attachmentId;
};

$images = [
    'p' => $create_image('p', 'P', '#126e70'),
    'm' => $create_image('m', 'M', '#d96523'),
    'g' => $create_image('g', 'G', '#4b5bdc'),
    'sale' => $create_image('sale', 'Promo', '#c94b0b'),
    'out' => $create_image('out', 'Esgotado', '#747172'),
    'simple' => $create_image('simple', 'Simples', '#252426'),
    'azul' => $create_image('azul', 'Azul', '#126e70'),
    'coral' => $create_image('coral', 'Coral', '#e07a5f'),
];

$sizeAttributeId = (int) wc_attribute_taxonomy_id_by_name('pa_size');
$colorAttributeId = (int) wc_attribute_taxonomy_id_by_name('pa_color');

$taxonomy_attribute = static function (int $id, string $name, array $termIds): WC_Product_Attribute {
    $attribute = new WC_Product_Attribute();
    $attribute->set_id($id);
    $attribute->set_name($name);
    $attribute->set_options(array_map('intval', $termIds));
    $attribute->set_visible(true);
    $attribute->set_variation(true);

    return $attribute;
};

$save_variable = static function (string $sku, string $name, string $slug, int $imageId, array $attributes) use ($signature, $categoryIds): WC_Product_Variable {
    $existingId = wc_get_product_id_by_sku($sku);
    $product = $existingId > 0 ? wc_get_product($existingId) : null;
    if (!$product instanceof WC_Product_Variable) {
        $product = new WC_Product_Variable();
        $product->set_sku($sku);
        $product->set_slug($slug);
    }

    $product->set_name($name);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_category_ids($categoryIds);
    $product->set_image_id($imageId);
    $product->set_attributes($attributes);
    $product->set_manage_stock(false);
    $product->set_stock_status('instock');
    $product->update_meta_data($signature, '1');
    $product->save();

    return $product;
};

$save_variation = static function (int $parentId, string $sku, array $attributes, string $regular, string $sale, int $imageId, bool $inStock) use ($signature): int {
    $existingId = wc_get_product_id_by_sku($sku);
    $variation = $existingId > 0 ? wc_get_product($existingId) : null;
    if (!$variation instanceof WC_Product_Variation) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($parentId);
        $variation->set_sku($sku);
    }

    $variation->set_status('publish');
    $variation->set_attributes($attributes);
    $variation->set_regular_price($regular);
    $variation->set_sale_price($sale);
    $variation->set_image_id($imageId);
    $variation->set_manage_stock(true);
    $variation->set_stock_quantity($inStock ? 12 : 0);
    $variation->set_stock_status($inStock ? 'instock' : 'outofstock');
    $variation->update_meta_data($signature, '1');

    return (int) $variation->save();
};

$sync = static function (int $productId): void {
    WC_Product_Variable::sync($productId);
    wc_delete_product_transients($productId);
    if (function_exists('wc_update_product_lookup_tables')) {
        wc_update_product_lookup_tables($productId);
    }
    clean_post_cache($productId);
};

$sizeOptions = array_values($sizeIds);
$colorOptions = array_values($colorIds);
$sizeAttribute = static fn (): WC_Product_Attribute => $taxonomy_attribute($sizeAttributeId, 'pa_size', $sizeOptions);
$colorAttribute = static fn (): WC_Product_Attribute => $taxonomy_attribute($colorAttributeId, 'pa_color', $colorOptions);

$all = $save_variable('PLAN031-PMG-ALL', 'Bandana revisão P M G', 'bandana-revisao-p-m-g', $images['p'], [$sizeAttribute()]);
$allId = $all->get_id();
$save_variation($allId, 'PLAN031-PMG-ALL-P', ['pa_size' => 'p'], '19.90', '', $images['p'], true);
$save_variation($allId, 'PLAN031-PMG-ALL-M', ['pa_size' => 'm'], '24.90', '', $images['m'], true);
$save_variation($allId, 'PLAN031-PMG-ALL-G', ['pa_size' => 'g'], '29.90', '', $images['g'], true);
$sync($allId);

$gOut = $save_variable('PLAN031-PMG-G-OUT', 'Coleira revisão G esgotado', 'coleira-revisao-g-esgotado', $images['out'], [$sizeAttribute()]);
$gOutId = $gOut->get_id();
$save_variation($gOutId, 'PLAN031-PMG-G-OUT-P', ['pa_size' => 'p'], '18.00', '', $images['p'], true);
$save_variation($gOutId, 'PLAN031-PMG-G-OUT-M', ['pa_size' => 'm'], '22.00', '', $images['m'], true);
$save_variation($gOutId, 'PLAN031-PMG-G-OUT-G', ['pa_size' => 'g'], '26.00', '', $images['out'], false);
$sync($gOutId);

$pOut = $save_variable('PLAN031-PMG-P-OUT', 'Peitoral revisão P esgotado', 'peitoral-revisao-p-esgotado', $images['m'], [$sizeAttribute()]);
$pOutId = $pOut->get_id();
$save_variation($pOutId, 'PLAN031-PMG-P-OUT-P', ['pa_size' => 'p'], '15.00', '', $images['out'], false);
$save_variation($pOutId, 'PLAN031-PMG-P-OUT-M', ['pa_size' => 'm'], '22.00', '', $images['m'], true);
$save_variation($pOutId, 'PLAN031-PMG-P-OUT-G', ['pa_size' => 'g'], '28.00', '', $images['g'], true);
$sync($pOutId);

$sale = $save_variable('PLAN031-PMG-SALE', 'Camiseta revisão promocional', 'camiseta-revisao-promocional', $images['sale'], [$sizeAttribute()]);
$saleId = $sale->get_id();
$save_variation($saleId, 'PLAN031-PMG-SALE-P', ['pa_size' => 'p'], '40.00', '28.00', $images['sale'], true);
$save_variation($saleId, 'PLAN031-PMG-SALE-M', ['pa_size' => 'm'], '36.00', '', $images['m'], true);
$save_variation($saleId, 'PLAN031-PMG-SALE-G', ['pa_size' => 'g'], '50.00', '34.00', $images['g'], true);
$sync($saleId);

$combo = $save_variable('PLAN031-COLOR-SIZE', 'Guia revisão cor e tamanho', 'guia-revisao-cor-tamanho', $images['azul'], [$colorAttribute(), $sizeAttribute()]);
$comboId = $combo->get_id();
$save_variation($comboId, 'PLAN031-COLOR-AZUL-P', ['pa_color' => 'azul', 'pa_size' => 'p'], '21.00', '', $images['azul'], true);
$save_variation($comboId, 'PLAN031-COLOR-AZUL-M', ['pa_color' => 'azul', 'pa_size' => 'm'], '25.00', '', $images['out'], false);
$save_variation($comboId, 'PLAN031-COLOR-CORAL-P', ['pa_color' => 'coral', 'pa_size' => 'p'], '23.00', '', $images['coral'], true);
$save_variation($comboId, 'PLAN031-COLOR-CORAL-M', ['pa_color' => 'coral', 'pa_size' => 'm'], '27.00', '', $images['m'], true);
$sync($comboId);

$simpleId = wc_get_product_id_by_sku('PLAN031-SIMPLE');
$simple = $simpleId > 0 ? wc_get_product($simpleId) : null;
if (!$simple instanceof WC_Product_Simple) {
    $simple = new WC_Product_Simple();
    $simple->set_sku('PLAN031-SIMPLE');
    $simple->set_slug('brinquedo-revisao-simples');
}
$simple->set_name('Brinquedo revisão simples');
$simple->set_status('publish');
$simple->set_catalog_visibility('visible');
$simple->set_category_ids($categoryIds);
$simple->set_image_id($images['simple']);
$simple->set_regular_price('15.90');
$simple->set_manage_stock(true);
$simple->set_stock_quantity(8);
$simple->set_stock_status('instock');
$simple->update_meta_data($signature, '1');
$simpleId = (int) $simple->save();
wc_delete_product_transients($simpleId);
clean_post_cache($simpleId);

WP_CLI::success(wp_json_encode([
    'all' => get_permalink($allId),
    'g_out' => get_permalink($gOutId),
    'p_out' => get_permalink($pOutId),
    'sale' => get_permalink($saleId),
    'combo' => get_permalink($comboId),
    'simple' => get_permalink($simpleId),
    'category' => get_term_link($categoryId, 'product_cat'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
