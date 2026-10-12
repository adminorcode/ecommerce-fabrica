<?php

use Petshop\Core\WooCommerce\ShippingQuoteCartExtension;

defined('ABSPATH') || exit;
$previous = [WC()->cart, WC()->customer, WC()->session, WC()->shipping()->packages, WC()->shipping()->shipping_methods];
$created = [];
$coupons = [];
$taxDisplay = 'excl';
$taxIncluded = 'no';
$yes = static fn () => 'yes';
$no = static fn () => 'no';
$display = static function () use (&$taxDisplay) { return $taxDisplay; };
$included = static function () use (&$taxIncluded) { return $taxIncluded; };
$taxRates = static fn () => [999991 => ['rate' => '10.0000', 'label' => 'Synthetic 041 tax', 'shipping' => 'yes', 'compound' => 'no']];
$flat = new WC_Shipping_Flat_Rate(999991);
$flat->enabled = 'yes'; $flat->instance_settings['cost'] = '17.13';
$pickup = new WC_Shipping_Local_Pickup(999992);
$pickup->enabled = 'yes'; $pickup->instance_settings['cost'] = '0';
$methods = static fn () => [$flat->get_instance_id() => $flat, $pickup->get_instance_id() => $pickup];
$noGlobalMethods = static fn () => [];
$split = static function (array $packages): array {
    if (!$packages) return [];
    $template = reset($packages);
    $result = [];
    foreach ($template['contents'] as $key => $line) {
        $package = $template;
        $package['contents'] = [$key => $line];
        $package['contents_cost'] = $line['line_total'];
        $result[] = $package;
    }
    return $result;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
add_filter('pre_option_woocommerce_calc_taxes', $yes);
add_filter('pre_option_woocommerce_enable_coupons', $yes);
add_filter('pre_option_woocommerce_shipping_cost_requires_address', $no);
add_filter('pre_option_woocommerce_tax_display_cart', $display);
add_filter('pre_option_woocommerce_prices_include_tax', $included);
add_filter('woocommerce_matched_tax_rates', $taxRates, 1000);
add_filter('woocommerce_shipping_zone_shipping_methods', $methods, 1000);
add_filter('woocommerce_shipping_methods', $noGlobalMethods, 1000);
add_filter('woocommerce_cart_shipping_packages', $split, 1000);
try {
    WC()->session = new class extends WC_Session {};
    WC()->customer = new WC_Customer();
    foreach (['shipping', 'billing'] as $group) {
        foreach (['country' => 'BR', 'state' => 'SP', 'postcode' => '01310100', 'city' => 'São Paulo', 'address_1' => 'Synthetic 041 street'] as $key => $value) {
            WC()->customer->{'set_' . $group . '_' . $key}($value);
        }
    }
    WC()->cart = new WC_Cart();
    foreach (['12.35', '19.99'] as $price) {
        $product = new WC_Product_Simple();
        $product->set_name('Synthetic native commerce 041');
        $product->set_regular_price($price); $product->set_tax_status('taxable');
        $product->set_weight('0.2'); $product->save(); $created[] = $product;
    }
    foreach (['fixed_cart' => '7.13', 'percent' => '12.5'] as $type => $amount) {
        $coupon = new WC_Coupon();
        $coupon->set_code('gate041-' . $type . '-' . strtolower(wp_generate_password(10, false)));
        $coupon->set_discount_type($type); $coupon->set_amount($amount); $coupon->save(); $coupons[] = $coupon;
    }
    $a = WC()->cart->add_to_cart($created[0]->get_id(), 3);
    $b = WC()->cart->add_to_cart($created[1]->get_id(), 2);
    $assert((bool) $a && (bool) $b, 'Native commerce fixture could not add two lines');
    foreach ([['excl', 'no'], ['incl', 'yes']] as [$taxDisplay, $taxIncluded]) {
        foreach ($coupons as $coupon) {
            WC()->cart->remove_coupons();
            $assert(WC()->cart->apply_coupon($coupon->get_code()), 'Native coupon not accepted');
            WC()->cart->calculate_totals();
            WC()->session->set('chosen_shipping_methods', ['flat_rate:999991', 'local_pickup:999992']);
            WC()->cart->calculate_totals();
            $packages = WC()->shipping()->get_packages();
            $assert(count($packages) === 2, 'Two native packages required');
            $assert(isset($packages[0]['rates']['flat_rate:999991']) && isset($packages[1]['rates']['local_pickup:999992']), 'Native package method identity lost');
            $assert((float) $packages[1]['rates']['local_pickup:999992']->get_cost() === 0.0, 'Zero-cost pickup lost');
            $before = WC()->cart->get_totals();
            $items = WC()->cart->get_cart_contents();
            $selection = WC()->session->get('chosen_shipping_methods');
            $assert($before['discount_total'] > 0 && $before['cart_contents_tax'] > 0, 'Discount and native tax must actually be exercised');
            $newPostcode = WC()->customer->get_shipping_postcode() === '01310100' ? '01001000' : '01310100';
            ShippingQuoteCartExtension::updateDestination(['action' => 'set_quote_destination', 'postcode' => $newPostcode]);
            $assert(WC()->session->get('petshop_shipping_destination_origin') === 'quote'
                && WC()->session->get('petshop_shipping_destination_postcode') === $newPostcode
                && WC()->customer->get_shipping_postcode() === $newPostcode
                && WC()->customer->get_shipping_city() === '' && WC()->customer->get_shipping_address_1() === '',
                'Test must execute a new CEP-only destination and clear old geography');
            WC()->cart->calculate_totals();
            $assert(WC()->cart->get_totals() === $before, 'CEP-only extension changed official discounted/taxed totals with identical synthetic method/tax rules');
            $assert(WC()->cart->get_cart_contents() === $items, 'CEP operation changed native line contents');
            $assert(WC()->session->get('chosen_shipping_methods') === $selection, 'CEP operation lost per-package selection');
            $assert(WC()->cart->get_applied_coupons() === [$coupon->get_code()], 'CEP operation lost coupon identity');
        }
    }
    WC()->cart->empty_cart(false);
    $limited = new WC_Product_Simple();
    $limited->set_name('Synthetic stock limit 041'); $limited->set_regular_price('10');
    $limited->set_manage_stock(true); $limited->set_stock_quantity(2); $limited->save(); $created[] = $limited;
    $assert(WC()->cart->add_to_cart($limited->get_id(), 3) === false, 'Native stock maximum bypassed');
    $limited->set_sold_individually(true); $limited->save();
    $key = WC()->cart->add_to_cart($limited->get_id(), 2);
    $assert((bool) $key && WC()->cart->get_cart_item($key)['quantity'] === 1, 'Sold-individually native quantity bypassed');
    $assert(WC()->cart->add_to_cart($limited->get_id(), 1) === false, 'Sold-individually duplicate accepted');
    fwrite(STDOUT, "041 native commerce: fixed/percentage coupons, incl/excl taxes, native rounding totals preserved, two packages, delivery/zero pickup, stock and sold-individually passed (isolated synthetic methods; not carrier/browser evidence).\n");
} finally {
    remove_filter('pre_option_woocommerce_calc_taxes', $yes);
    remove_filter('pre_option_woocommerce_enable_coupons', $yes);
    remove_filter('pre_option_woocommerce_shipping_cost_requires_address', $no);
    remove_filter('pre_option_woocommerce_tax_display_cart', $display);
    remove_filter('pre_option_woocommerce_prices_include_tax', $included);
    remove_filter('woocommerce_matched_tax_rates', $taxRates, 1000);
    remove_filter('woocommerce_shipping_zone_shipping_methods', $methods, 1000);
    remove_filter('woocommerce_shipping_methods', $noGlobalMethods, 1000);
    remove_filter('woocommerce_cart_shipping_packages', $split, 1000);
    [WC()->cart, WC()->customer, WC()->session, WC()->shipping()->packages, WC()->shipping()->shipping_methods] = $previous;
    foreach (array_reverse($created) as $product) $product->delete(true);
    foreach ($coupons as $coupon) $coupon->delete(true);
}
