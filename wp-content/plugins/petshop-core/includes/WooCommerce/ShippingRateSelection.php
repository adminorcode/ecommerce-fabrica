<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

/** Preserve native cache when inactive supplier rules cause redundant invalidations. */
final class ShippingRateSelection
{
    /** @var array<int, list<array{callback: callable, guard: callable, priority: int, accepted: int}>> */
    private static array $suspended = [];

    public static function bootstrap(): void
    {
        add_filter('rest_request_before_callbacks', [self::class, 'beforeSelection'], 100, 3);
        add_filter('rest_request_after_callbacks', [self::class, 'afterSelection'], 100, 3);
    }

    /** Older sessions may still contain a supplier offer from a now-disabled rule. */
    private static function hasCachedSupplierFreeOffer(): bool
    {
        $session = WC()->session;
        if (!$session) return true;
        // Match the ten package slots cleared by the supplier callback we suspend.
        for ($index = 0; $index < 10; $index++) {
            $package = $session->get('shipping_for_package_' . $index);
            if (!is_array($package)) continue;
            foreach ($package['rates'] ?? [] as $rate) {
                if ($rate instanceof \WC_Shipping_Rate && in_array($rate->get_id(), ['free_shipping_min', 'free_shipping_product'], true)) return true;
            }
        }
        return false;
    }

    public static function beforeSelection($response, array $handler, \WP_REST_Request $request)
    {
        $quantityUpdate = $request->get_route() === '/wc/store/v1/cart/update-item';
        if ($request->get_method() !== 'POST' || (!$quantityUpdate && $request->get_route() !== '/wc/store/v1/cart/select-shipping-rate')
            || get_option('woo_better_enable_free_shipping_by_product', 'no') === 'yes'
            || get_option('woo_better_enable_min_free_shipping', 'no') === 'yes') return $response;
        $hook = $GLOBALS['wp_filter']['woocommerce_before_calculate_totals'] ?? null;
        if (!$hook instanceof \WP_Hook) return $response;
        $requestId = spl_object_id($request);
        foreach ($hook->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $entry) {
                $callback = $entry['function'];
                if (!is_array($callback) || !is_object($callback[0])
                    || get_class($callback[0]) !== 'Lkn\\WcBetterShippingCalculatorForBrazil\\Includes\\WcBetterShippingCalculatorForBrazil'
                    || $callback[1] !== 'lkn_set_shipping_calculation_flag') continue;
                if (remove_action('woocommerce_before_calculate_totals', $callback, $priority)) {
                    // Store API initializes its session inside the route callback, after this filter.
                    $invalidated = false;
                    $guard = static function (...$args) use ($callback, $quantityUpdate, &$invalidated): void {
                        // Quantity writes retain the first invalidation. A second
                        // totals pass in this same request uses WC's package hash,
                        // which still invalidates genuinely different packages.
                        if (($quantityUpdate && !$invalidated) || self::hasCachedSupplierFreeOffer()) call_user_func_array($callback, $args);
                        $invalidated = true;
                    };
                    add_action('woocommerce_before_calculate_totals', $guard, $priority, $entry['accepted_args']);
                    self::$suspended[$requestId][] = ['callback' => $callback, 'guard' => $guard, 'priority' => $priority, 'accepted' => $entry['accepted_args']];
                }
            }
        }
        return $response;
    }

    public static function afterSelection($response, array $handler, \WP_REST_Request $request)
    {
        $requestId = spl_object_id($request);
        foreach (self::$suspended[$requestId] ?? [] as $entry) {
            remove_action('woocommerce_before_calculate_totals', $entry['guard'], $entry['priority']);
            add_action('woocommerce_before_calculate_totals', $entry['callback'], $entry['priority'], $entry['accepted']);
        }
        unset(self::$suspended[$requestId]);
        return $response;
    }
}
