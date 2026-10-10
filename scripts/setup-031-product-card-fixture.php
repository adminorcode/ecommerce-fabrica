<?php

if (!defined('ABSPATH')) {
    exit;
}

$signature = '_petshop_plan_031_fixture';
$homeSnapshotOption = 'petshop_plan_031_home_snapshot';
$comingSoonSnapshotOption = 'petshop_plan_031_coming_soon_snapshot';

if (get_option($comingSoonSnapshotOption, null) === null) {
    update_option($comingSoonSnapshotOption, [
        'woocommerce_coming_soon' => get_option('woocommerce_coming_soon', null),
        'woocommerce_store_pages_only' => get_option('woocommerce_store_pages_only', null),
    ], false);
}
update_option('woocommerce_coming_soon', 'no', false);
update_option('woocommerce_store_pages_only', 'no', false);

$category = get_term_by('slug', 'plano-031-card-variavel', 'product_cat');
if (!$category instanceof WP_Term) {
    $created = wp_insert_term('Plano 031 Card Variável', 'product_cat', ['slug' => 'plano-031-card-variavel']);
    if (is_wp_error($created)) {
        WP_CLI::error($created->get_error_message());
    }
    update_term_meta((int) $created['term_id'], $signature, '1');
    $category = get_term((int) $created['term_id'], 'product_cat');
}

if (!$category instanceof WP_Term) {
    WP_CLI::error('Categoria fixture 031 ausente.');
}

$categoryIds = [(int) $category->term_id];
$existingCatalogCategory = get_term_by('slug', 'bandanas', 'product_cat');
if ($existingCatalogCategory instanceof WP_Term) {
    $categoryIds[] = (int) $existingCatalogCategory->term_id;
}
$categoryIds = array_values(array_unique($categoryIds));

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
        "plan-031-{$slug}.svg",
        null,
        '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600"><rect width="600" height="600" fill="' . esc_attr($hex) . '"/><text x="300" y="315" text-anchor="middle" font-family="Arial" font-size="42" fill="#ffffff">' . esc_html($label) . '</text></svg>'
    );

    if (!empty($upload['error'])) {
        WP_CLI::error((string) $upload['error']);
    }

    $attachmentId = wp_insert_attachment([
        'post_mime_type' => 'image/svg+xml',
        'post_title' => "Plano 031 {$label}",
        'post_status' => 'inherit',
    ], (string) $upload['file']);

    if (is_wp_error($attachmentId)) {
        WP_CLI::error($attachmentId->get_error_message());
    }

    update_post_meta((int) $attachmentId, $signature, $slug);
    update_post_meta((int) $attachmentId, '_wp_attachment_image_alt', "Plano 031 {$label}");

    return (int) $attachmentId;
};

$imageInitial = $create_image('initial', 'Inicial', '#126e70');
$imageSmall = $create_image('small', 'Pequeno', '#d96523');
$imageMedium = $create_image('medium', 'Medio', '#4b5bdc');
$imageSoldout = $create_image('soldout', 'Esgotado', '#747172');

$find_product = static function (string $sku): ?WC_Product {
    $id = wc_get_product_id_by_sku($sku);
    if ($id <= 0) {
        return null;
    }

    $product = wc_get_product($id);
    return $product instanceof WC_Product ? $product : null;
};

$variable = $find_product('PLAN031-VARIABLE');
if (!$variable instanceof WC_Product_Variable) {
    $variable = new WC_Product_Variable();
    $variable->set_name('Produto Variável Plano 031');
    $variable->set_slug('produto-variavel-plano-031');
    $variable->set_sku('PLAN031-VARIABLE');
}

$variable->set_status('publish');
$variable->set_catalog_visibility('visible');
$variable->set_description('Fixture plan031busca para validar card variável no Ticket 031.');
$variable->set_short_description('Fixture plan031busca Ticket 031.');
$variable->set_category_ids($categoryIds);
$variable->set_image_id($imageInitial);
$variable->set_manage_stock(false);
$variable->set_stock_status('instock');
$variable->set_date_created(current_time('mysql'));
$variable->set_date_modified(current_time('mysql'));

$size = new WC_Product_Attribute();
$size->set_id(0);
$size->set_name('size');
$size->set_options(['small', 'medium', 'soldout']);
$size->set_visible(true);
$size->set_variation(true);

$color = new WC_Product_Attribute();
$color->set_id(0);
$color->set_name('color');
$color->set_options(['red']);
$color->set_visible(true);
$color->set_variation(true);

$variable->set_attributes([$size, $color]);
$variable->update_meta_data($signature, '1');
$variableId = $variable->save();

$ensure_variation = static function (
    int $parentId,
    string $sku,
    array $attributes,
    string $regular,
    string $sale,
    int $imageId,
    bool $inStock
) use ($signature): int {
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
    $variation->set_manage_stock(true);
    $variation->set_stock_quantity($inStock ? 10 : 0);
    $variation->set_stock_status($inStock ? 'instock' : 'outofstock');
    $variation->set_image_id($imageId);
    $variation->update_meta_data($signature, '1');

    return $variation->save();
};

$initialVariationId = $ensure_variation($variableId, 'PLAN031-VAR-INITIAL', ['size' => '', 'color' => 'red'], '15', '10', $imageInitial, true);
$smallVariationId = $ensure_variation($variableId, 'PLAN031-VAR-SMALL', ['size' => 'small', 'color' => 'red'], '20', '', $imageSmall, true);
$mediumVariationId = $ensure_variation($variableId, 'PLAN031-VAR-MEDIUM', ['size' => 'medium', 'color' => 'red'], '30', '', $imageMedium, true);
$soldoutVariationId = $ensure_variation($variableId, 'PLAN031-VAR-SOLDOUT', ['size' => 'soldout', 'color' => 'red'], '40', '', $imageSoldout, false);

WC_Product_Variable::sync($variableId);
wc_delete_product_transients($variableId);
if (function_exists('wc_update_product_lookup_tables')) {
    wc_update_product_lookup_tables($variableId);
}
clean_post_cache($variableId);

$related = $find_product('PLAN031-RELATED');
if (!$related instanceof WC_Product_Simple) {
    $related = new WC_Product_Simple();
    $related->set_name('Produto Relacionado Plano 031');
    $related->set_slug('produto-relacionado-plano-031');
    $related->set_sku('PLAN031-RELATED');
}

$related->set_status('publish');
$related->set_catalog_visibility('visible');
$related->set_description('Fixture relacionado plan031relacionado para validar relacionados do Ticket 031.');
$related->set_short_description('Fixture relacionado plan031relacionado Ticket 031.');
$related->set_regular_price('12');
$related->set_category_ids([(int) $category->term_id]);
$related->set_image_id($imageSmall);
$related->set_date_created(current_time('mysql'));
$related->set_date_modified(current_time('mysql'));
$related->update_meta_data($signature, '1');
$relatedId = $related->save();
wc_delete_product_transients($relatedId);
if (function_exists('wc_update_product_lookup_tables')) {
    wc_update_product_lookup_tables($relatedId);
}
clean_post_cache($relatedId);

flush_rewrite_rules(false);

$homeId = (int) get_option('page_on_front');
if ($homeId > 0) {
    $home = get_post($homeId);
    if ($home instanceof WP_Post) {
        if (get_option($homeSnapshotOption, null) === null) {
            update_option($homeSnapshotOption, (string) $home->post_content, false);
        }

        $block = '<!-- wp:petshop/product-grid {"selectionMode":"manual","productIds":[' . $variableId . '],"limit":1,"columns":4,"orderby":"date","order":"DESC"} /-->';
        if (!str_contains((string) $home->post_content, '"productIds":[' . $variableId . ']')) {
            wp_update_post([
                'ID' => $homeId,
                'post_content' => $block . "\n\n" . (string) $home->post_content,
            ]);
        }
    }
}

update_option('petshop_plan_031_fixture', [
    'product_id' => $variableId,
    'related_id' => $relatedId,
    'category_id' => (int) $category->term_id,
    'initial_variation_id' => $initialVariationId,
    'small_variation_id' => $smallVariationId,
    'medium_variation_id' => $mediumVariationId,
    'soldout_variation_id' => $soldoutVariationId,
], false);

WP_CLI::success('Fixture 031 preparado.');
