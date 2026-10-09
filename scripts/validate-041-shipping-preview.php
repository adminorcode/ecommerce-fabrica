<?php

use Petshop\Core\WooCommerce\ShippingQuotes;

defined('ABSPATH') || exit;
$originalCart = WC()->cart;
$originalCustomer = WC()->customer;
$originalSession = WC()->session;
$cartData = $originalCart->get_cart_contents();
$cartTotals = $originalCart->get_totals();
$sessionData = $originalSession->get_session_data();
$customerData = $originalCustomer->get_data();
$shippingPackages = WC()->shipping()->packages;
$shippingMethods = WC()->shipping()->shipping_methods;
$created = [];
$captures = [];
$expectedState = '';
$priceContext = static function ($price) use (&$expectedState) {
    if ($expectedState !== '' && (WC()->customer->get_shipping_state() !== $expectedState
        || WC()->customer->get_billing_state() !== $expectedState)) {
        throw new RuntimeException('Price/tax helpers ran before applying preview destination');
    }
    return $price;
};
add_filter('woocommerce_product_get_price', $priceContext, 1000);
$free = new WC_Shipping_Free_Shipping();
$free->requires = 'min_amount';
$free->min_amount = '100';
$free->ignore_discounts = 'no';
$rates = static function (array $rates, array $package) use (&$captures, $free, $originalSession): array {
    if (empty($package['product_page_calculation'])) return $rates;
    if (WC()->session === $originalSession) throw new RuntimeException('Preview shared the real cart session');
    $line = reset($package['contents']);
    $captures[] = [
        'quantity' => $line['quantity'], 'subtotal' => $line['line_subtotal'],
        'formattedQuantity' => $line['formatted_data']->quantity ?? null,
        'coupons' => WC()->cart->get_applied_coupons(),
        'product_id' => $line['product_id'], 'variation_id' => $line['variation_id'],
        'variation' => $line['variation'], 'weight' => $line['data']->get_weight(),
    ];
    return $free->is_available($package)
        ? ['fixture_free' => new WC_Shipping_Rate('fixture_free', 'Fixture free shipping', 0, [], 'free_shipping')]
        : ['fixture_paid' => new WC_Shipping_Rate('fixture_paid', 'Fixture paid shipping', 19.9, [], 'flat_rate')];
};
add_filter('woocommerce_package_rates', $rates, 1000, 2);
try {
    foreach ([40, 120] as $price) {
        $product = new WC_Product_Simple();
        $product->set_name('Synthetic preview 041');
        $product->set_regular_price((string) $price);
        $product->set_weight('0.2');
        $product->set_length('10'); $product->set_width('10'); $product->set_height('2');
        $product->set_tax_status('none');
        $product->save();
        $created[] = $product;
    }
    $parent = new WC_Product_Variable();
    $parent->set_name('Synthetic variable preview 041');
    $attribute = new WC_Product_Attribute();
    $attribute->set_name('model'); $attribute->set_options(['physical', 'virtual']); $attribute->set_variation(true);
    $parent->set_attributes([$attribute]); $parent->save(); $created[] = $parent;
    $variation = new WC_Product_Variation();
    $variation->set_parent_id($parent->get_id()); $variation->set_attributes(['model' => 'physical']);
    $variation->set_regular_price('40'); $variation->set_sale_price('35'); $variation->set_weight('0.75');
    $variation->set_tax_status('none'); $variation->save(); $created[] = $variation;
    $originalCart->set_subtotal(1000);
    $expectedState = 'SP';
    $cheap = ShippingQuotes::quote($created[0], '01310100', 1);
    if ($cheap['rates'][0]['id'] !== 'fixture_paid') throw new RuntimeException('Cheap preview inherited free shipping from expensive cart');
    $originalCart->set_subtotal(0);
    $expectedState = 'RS';
    $expensive = ShippingQuotes::quote($created[1], '91210320', 1);
    if ($expensive['rates'][0]['id'] !== 'fixture_free' || $expensive['rates'][0]['cost'] !== 0.0) throw new RuntimeException('Expensive preview lost native free shipping with empty cart');
    $many = ShippingQuotes::quote($created[0], '91210320', 3);
    if ($many['rates'][0]['id'] !== 'fixture_free') throw new RuntimeException('Quantity not reflected in native free shipping threshold');
    if (array_column($captures, 'quantity') !== [1, 1, 3]) throw new RuntimeException('Preview package quantities incorrect');
    foreach ($captures as $capture) {
        if ($capture['coupons'] !== []) throw new RuntimeException('Preview inherited cart coupons');
        if ($capture['formattedQuantity'] !== null && $capture['formattedQuantity'] !== $capture['quantity']) throw new RuntimeException('Melhor Envio formatted quantity incorrect');
    }
    $virtual = clone $created[0];
    $virtual->set_virtual(true);
    $captureCount = count($captures);
    $virtualQuote = ShippingQuotes::quote($virtual, '91210320', 1);
    if ($virtualQuote['rates'] !== [] || count($captures) !== $captureCount) throw new RuntimeException('Virtual product triggered a shipping calculation');
    ShippingQuotes::quote($variation, '91210320', 2);
    $selected = end($captures);
    if ($selected['product_id'] !== $parent->get_id() || $selected['variation_id'] !== $variation->get_id()
        || $selected['variation'] !== ['attribute_model' => 'physical'] || $selected['quantity'] !== 2
        || (float) $selected['subtotal'] !== 70.0 || (float) $selected['weight'] !== 0.75) {
        throw new RuntimeException('Selected variation, sale price, quantity or weight lost in preview package');
    }
    $virtualVariation = clone $variation;
    $virtualVariation->set_virtual(true);
    $captureCount = count($captures);
    if (ShippingQuotes::quote($virtualVariation, '91210320', 2)['rates'] !== [] || count($captures) !== $captureCount) {
        throw new RuntimeException('Virtual variation triggered a shipping calculation');
    }
    if (WC()->cart !== $originalCart || WC()->customer !== $originalCustomer || WC()->session !== $originalSession) throw new RuntimeException('Preview did not restore runtime references');
    if ($originalCart->get_cart_contents() !== $cartData || $originalCustomer->get_data() !== $customerData
        || $originalSession->get_session_data() !== $sessionData || WC()->shipping()->packages !== $shippingPackages
        || WC()->shipping()->shipping_methods !== $shippingMethods) throw new RuntimeException('Preview mutated real cart/customer/session/shipping state');
    fwrite(STDOUT, "041 preview: native free shipping, quantities, selected variation/sale/weight, virtual products, formatted_data, cache/session/customer isolation passed (synthetic rates).\n");
} finally {
    remove_filter('woocommerce_product_get_price', $priceContext, 1000);
    remove_filter('woocommerce_package_rates', $rates, 1000);
    $originalCart->set_totals($cartTotals);
    foreach ($created as $product) $product->delete(true);
}
