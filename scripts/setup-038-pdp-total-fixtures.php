<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!class_exists('WooCommerce')) {
    throw new RuntimeException('WooCommerce não está ativo.');
}

function petshop_038_publish_product(WC_Product $product, string $name, string $sku, string $slug): WC_Product
{
    $existingId = wc_get_product_id_by_sku($sku);
    if ($existingId > 0) {
        $existing = wc_get_product($existingId);
        if ($existing instanceof WC_Product) {
            $product = $existing;
        }
    }

    $product->set_name($name);
    $product->set_slug($slug);
    $product->set_sku($sku);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_manage_stock(true);
    $product->set_stock_quantity(20);
    $product->set_stock_status('instock');
    $product->set_sold_individually(false);
    $product->save();

    return $product;
}

$simple = petshop_038_publish_product(
    new WC_Product_Simple(),
    'Ticket 038 Produto Simples',
    'PETSHOP-038-SIMPLE',
    'ticket-038-produto-simples'
);
$simple->set_regular_price('19.90');
$simple->set_sale_price('');
$simple->save();

$sale = petshop_038_publish_product(
    new WC_Product_Simple(),
    'Ticket 038 Produto Promocional',
    'PETSHOP-038-SALE',
    'ticket-038-produto-promocional'
);
$sale->set_regular_price('30.00');
$sale->set_sale_price('21.50');
$sale->save();

$attributeName = 'pa_petshop_038_tamanho';
$attributeLabel = 'Tamanho 038';

if (!taxonomy_exists($attributeName)) {
    $attributeId = wc_create_attribute([
        'name' => $attributeLabel,
        'slug' => str_replace('pa_', '', $attributeName),
        'type' => 'select',
        'order_by' => 'menu_order',
        'has_archives' => false,
    ]);

    if (is_wp_error($attributeId)) {
        throw new RuntimeException($attributeId->get_error_message());
    }

    delete_transient('wc_attribute_taxonomies');
    WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
    register_taxonomy(
        $attributeName,
        ['product'],
        [
            'hierarchical' => false,
            'label' => $attributeLabel,
            'query_var' => true,
            'rewrite' => false,
            'show_ui' => false,
        ]
    );
}

foreach (['p' => 'P', 'g' => 'G'] as $slug => $label) {
    if (!term_exists($slug, $attributeName)) {
        $term = wp_insert_term($label, $attributeName, ['slug' => $slug]);
        if (is_wp_error($term)) {
            throw new RuntimeException($term->get_error_message());
        }
    }
}

$variable = petshop_038_publish_product(
    new WC_Product_Variable(),
    'Ticket 038 Produto Variável',
    'PETSHOP-038-VARIABLE',
    'ticket-038-produto-variavel'
);
$variable->set_manage_stock(false);

$attribute = new WC_Product_Attribute();
$attributeId = (int) wc_attribute_taxonomy_id_by_name(str_replace('pa_', '', $attributeName));
if ($attributeId <= 0) {
    $attributeId = (int) wc_attribute_taxonomy_id_by_name($attributeName);
}
$attribute->set_id($attributeId);
$attribute->set_name($attributeName);
$attribute->set_options(['p', 'g']);
$attribute->set_position(0);
$attribute->set_visible(true);
$attribute->set_variation(true);

$variable->set_attributes([$attribute]);
$variable->save();

wp_set_object_terms($variable->get_id(), ['p', 'g'], $attributeName);

$variationPrices = [
    'p' => ['sku' => 'PETSHOP-038-VARIABLE-P', 'regular' => '24.00', 'sale' => ''],
    'g' => ['sku' => 'PETSHOP-038-VARIABLE-G', 'regular' => '32.00', 'sale' => '28.00'],
];

foreach ($variationPrices as $slug => $data) {
    $variationId = wc_get_product_id_by_sku($data['sku']);
    $variation = $variationId > 0 ? wc_get_product($variationId) : new WC_Product_Variation();
    if (!$variation instanceof WC_Product_Variation) {
        $variation = new WC_Product_Variation();
    }

    $variation->set_parent_id($variable->get_id());
    $variation->set_attributes([$attributeName => $slug]);
    $variation->set_sku($data['sku']);
    $variation->set_regular_price($data['regular']);
    $variation->set_sale_price($data['sale']);
    $variation->set_status('publish');
    $variation->set_manage_stock(true);
    $variation->set_stock_quantity(20);
    $variation->set_stock_status('instock');
    $variation->save();
}

WC_Product_Variable::sync($variable->get_id());
wc_delete_product_transients($variable->get_id());

echo wp_json_encode([
    'simple' => (int) $simple->get_id(),
    'sale' => (int) $sale->get_id(),
    'variable' => (int) $variable->get_id(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
