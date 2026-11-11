<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

function petshop041PerformanceProducts(): array
{
    if (get_option('petshop_gate_041_performance_products', [])) throw new RuntimeException('Previous performance fixtures require cleanup.');
    $ids = [];
    $save = static function (WC_Product $product) use (&$ids): int {
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->update_meta_data('_petshop_gate_041_performance', '1');
        $id = $product->save();
        $ids[] = $id;
        update_option('petshop_gate_041_performance_products', $ids, false);
        return $id;
    };
    $simple = new WC_Product_Simple();
    $simple->set_name('Fixture 041 quantidade');
    $simple->set_regular_price('28.00');
    $simple->set_manage_stock(true);
    $simple->set_stock_quantity(8);
    $simple->set_weight('0.1');
    $simpleId = $save($simple);
    $parent = new WC_Product_Variable();
    $parent->set_name('Fixture 041 variação');
    $attribute = new WC_Product_Attribute();
    $attribute->set_name('Tamanho 041');
    $attribute->set_options(['G']);
    $attribute->set_variation(true);
    $parent->set_attributes([$attribute]);
    $parentId = $save($parent);
    $variation = new WC_Product_Variation();
    $variation->set_parent_id($parentId);
    $variation->set_attributes(['tamanho-041' => 'G']);
    $variation->set_regular_price('20.00');
    $variation->set_manage_stock(true);
    $variation->set_stock_quantity(8);
    $variation->set_weight('0.1');
    $variationId = $save($variation);
    WC_Product_Variable::sync($parentId);
    $readonly = new WC_Product_Simple();
    $readonly->set_name('Fixture 041 individual');
    $readonly->set_regular_price('10.00');
    $readonly->set_sold_individually(true);
    $readonly->set_weight('0.1');
    $readonlyId = $save($readonly);
    $coupon = new WC_Coupon();
    $coupon->set_code('gate041-perf-' . strtolower(wp_generate_password(10, false)));
    $coupon->set_discount_type('percent');
    $coupon->set_amount(10);
    $coupon->set_product_ids([$simpleId, $parentId]);
    $coupon->update_meta_data('_petshop_gate_041_performance', '1');
    $coupon->save();
    update_option('petshop_gate_041_performance_coupon', $coupon->get_id(), false);
    return ['simple' => $simpleId, 'variation' => $variationId, 'readonly' => $readonlyId, 'coupon' => $coupon->get_code()];
}

function petshop041CleanupPerformanceProducts(): void
{
    foreach ((array) get_option('petshop_gate_041_performance_products', []) as $id) {
        $product = wc_get_product((int) $id);
        if ($product && $product->get_meta('_petshop_gate_041_performance') === '1') $product->delete(true);
    }
    $couponId = (int) get_option('petshop_gate_041_performance_coupon', 0);
    if ($couponId) {
        $coupon = new WC_Coupon($couponId);
        if ($coupon->get_meta('_petshop_gate_041_performance') === '1') $coupon->delete(true);
    }
    delete_option('petshop_gate_041_performance_products');
    delete_option('petshop_gate_041_performance_coupon');
}
