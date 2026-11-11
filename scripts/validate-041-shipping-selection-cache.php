<?php
declare(strict_types=1);
defined('ABSPATH') || exit(1);
use Petshop\Core\WooCommerce\ShippingRateSelection;
if (!WC()->session) WC()->initialize_session();
$originalPackage = WC()->session->get('shipping_for_package_0');
WC()->session->set('shipping_for_package_0', null);
$hook = 'woocommerce_before_calculate_totals';
$matching = [];
foreach (($GLOBALS['wp_filter'][$hook]->callbacks ?? []) as $priority => $entries) {
    foreach ($entries as $entry) {
        $callback = $entry['function'];
        if (is_array($callback) && is_object($callback[0])
            && get_class($callback[0]) === 'Lkn\WcBetterShippingCalculatorForBrazil\Includes\WcBetterShippingCalculatorForBrazil'
            && $callback[1] === 'lkn_set_shipping_calculation_flag') $matching[] = [$callback, $priority];
    }
}
if (count($matching) !== 1) throw new RuntimeException('Expected installed supplier callback');
[$callback, $priority] = $matching[0];
$check = static function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
$guards = static function () use ($hook): array {
    $found = [];
    foreach (($GLOBALS['wp_filter'][$hook]->callbacks ?? []) as $entries) {
        foreach ($entries as $entry) {
            $guard = $entry['function'];
            if ($guard instanceof Closure && (new ReflectionFunction($guard))->getClosureScopeClass()?->getName() === ShippingRateSelection::class) $found[] = $guard;
        }
    }
    return $found;
};
$request = new WP_REST_Request('POST', '/wc/store/v1/cart/select-shipping-rate');
$options = ['woo_better_enable_free_shipping_by_product', 'woo_better_enable_min_free_shipping'];
// Filters simulate administration without changing options in the shared database.
$disabled = static fn () => 'no';
foreach ($options as $option) add_filter('pre_option_' . $option, $disabled);
try {
    $sentinel = new WP_Error('gate041', 'Sentinel');
    $check(ShippingRateSelection::beforeSelection($sentinel, [], $request) === $sentinel, 'Response must remain untouched');
    $check(has_action($hook, $callback) === false, 'Pure selection must retain native rate cache when rules inactive');
    ShippingRateSelection::afterSelection($sentinel, [], $request);
    $check(has_action($hook, $callback) === $priority, 'Restore original callback and priority after request/error');
    $other = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    ShippingRateSelection::beforeSelection(null, [], $other);
    $check(has_action($hook, $callback) === $priority, 'Address edits must retain supplier recalculation');
    foreach ($options as $option) {
        $enabled = static fn () => 'yes';
        add_filter('pre_option_' . $option, $enabled, 100);
        try {
            ShippingRateSelection::beforeSelection(null, [], $request);
            $check(has_action($hook, $callback) === $priority, 'Active supplier free shipping must retain recalculation: ' . $option);
        } finally { remove_filter('pre_option_' . $option, $enabled, 100); }
    }
    $session = WC()->session;
    WC()->session = null;
    try { ShippingRateSelection::beforeSelection(null, [], $request); }
    finally { WC()->session = $session; }
    $paidPackage = ['package_hash' => 'paid-package', 'rates' => ['paid' => new WC_Shipping_Rate('paid', 'Paid quote', 42.99, [], 'paid')]];
    $session->set('shipping_for_package_0', $paidPackage);
    $check(count($guards()) === 1, 'Install exactly one guard before late session initialization');
    foreach ($guards() as $guard) $guard(WC()->cart);
    $check($session->get('shipping_for_package_0') == $paidPackage, 'Paid cache must survive selection after session hydration');
    ShippingRateSelection::afterSelection(null, [], $request);
    $check($guards() === [], 'Remove temporary guard after request');
    foreach (['free_shipping_min', 'free_shipping_product'] as $method) {
        ShippingRateSelection::beforeSelection(null, [], $request);
        // Session/cache can be hydrated after REST before-callbacks; inspect it only at totals.
        WC()->session->set('shipping_for_package_0', ['package_hash' => 'old-rule', 'rates' => [$method => new WC_Shipping_Rate($method, 'Old free offer', 0, [], 'free_shipping')]]);
        $check(has_action($hook, $callback) === false, 'Selection installs a guard until Store API session is available');
        $check(count($guards()) === 1, 'The real request guard must run at calculation time');
        foreach ($guards() as $guard) $guard(WC()->cart);
        $check(WC()->session->get('shipping_for_package_0') === null, 'Supplier must actually discard the obsolete free offer');
        ShippingRateSelection::afterSelection(null, [], $request);
    }
    $quantityRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-item');
    ShippingRateSelection::beforeSelection(null, [], $quantityRequest);
    $session->set('shipping_for_package_0', $paidPackage);
    foreach ($guards() as $guard) $guard(WC()->cart);
    $check($session->get('shipping_for_package_0') === null, 'Quantity must keep its first supplier invalidation');
    $session->set('shipping_for_package_0', $paidPackage);
    foreach ($guards() as $guard) $guard(WC()->cart);
    $check($session->get('shipping_for_package_0') == $paidPackage, 'Do not discard the same-request quote in a second totals pass');
    ShippingRateSelection::afterSelection(null, [], $quantityRequest);
    $check(has_action($hook, $callback) === $priority && $guards() === [], 'Restore supplier callback after quantity request');
} finally {
    WC()->session->set('shipping_for_package_0', $originalPackage);
    ShippingRateSelection::afterSelection(null, [], $request);
    foreach ($options as $option) remove_filter('pre_option_' . $option, $disabled);
}
WP_CLI::success('041 selection compatibility: inactive/active rules, obsolete free offers discarded, address edits and callback restoration passed.');
