<?php

defined('ABSPATH') || exit;

$slug = 'bandana-desconto-por-variacao';
$existing = get_page_by_path($slug, OBJECT, 'product');
if ($existing instanceof WP_Post) {
    wp_delete_post($existing->ID, true);
}

$attribute = new WC_Product_Attribute();
$attribute->set_id(0);
$attribute->set_name('Tamanho');
$attribute->set_options(['P', 'M', 'G']);
$attribute->set_visible(true);
$attribute->set_variation(true);

$product = new WC_Product_Variable();
$product->set_name('Bandana desconto por variação');
$product->set_slug($slug);
$product->set_status('publish');
$product->set_catalog_visibility('visible');
$product->set_description('Produto de demonstração. Cada tamanho tem o próprio desconto no meio de pagamento.');
$product->set_short_description('P com 5%, M com 7% e G com 10% no meio de pagamento.');
$product->set_attributes([$attribute]);
$product->set_stock_status('instock');
$productId = $product->save();

$variations = [
    'P' => '5.00',
    'M' => '7.00',
    'G' => '10.00',
];

foreach ($variations as $size => $percent) {
    $variation = new WC_Product_Variation();
    $variation->set_parent_id($productId);
    $variation->set_status('publish');
    $variation->set_attributes(['tamanho' => $size]);
    $variation->set_regular_price('100');
    $variation->set_tax_status('none');
    $variation->set_manage_stock(true);
    $variation->set_stock_quantity(20);
    $variation->set_stock_status('instock');
    $variation->update_meta_data('_petshop_payment_discount_percent', $percent);
    $variation->save();
}

WC_Product_Variable::sync($productId);
wc_delete_product_transients($productId);

$reloaded = wc_get_product($productId);
$lines = [];
if ($reloaded instanceof WC_Product_Variable) {
    foreach ($reloaded->get_children() as $childId) {
        $child = wc_get_product($childId);
        if (!$child instanceof WC_Product_Variation) {
            continue;
        }
        $lines[] = [
            'id' => $child->get_id(),
            'tamanho' => $child->get_attribute('tamanho'),
            'preco' => $child->get_regular_price(),
            'desconto' => (string) $child->get_meta('_petshop_payment_discount_percent'),
        ];
    }
}

echo wp_json_encode([
    'id' => $productId,
    'url' => get_permalink($productId),
    'edit' => admin_url('post.php?post=' . $productId . '&action=edit'),
    'variacoes' => $lines,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
