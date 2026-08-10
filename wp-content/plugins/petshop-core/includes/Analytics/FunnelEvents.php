<?php

declare(strict_types=1);

namespace Petshop\Core\Analytics;

defined('ABSPATH') || exit;

final class FunnelEvents
{
    public static function bootstrap(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        if (!class_exists('WooCommerce')) return;
        $path = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/funnel-events.js';
        wp_enqueue_script('petshop-funnel-events', plugins_url('assets/js/funnel-events.js', PETSHOP_CORE_FILE), ['jquery'], is_file($path) ? (string) filemtime($path) : '1.0.0', true);
        $event = '';
        $data = [];
        if (is_product()) {
            $product = wc_get_product(get_queried_object_id());
            if ($product) { $event = 'view_item'; $data = self::productData($product); }
        } elseif (is_search()) {
            $event = 'search'; $data = ['search_term' => get_search_query()];
        } elseif (is_shop() || is_product_taxonomy()) {
            $event = 'view_item_list'; $data = ['item_list_name' => wp_strip_all_tags(woocommerce_page_title(false))];
        } elseif (is_checkout() && !is_order_received_page()) {
            $event = 'begin_checkout'; $data = ['currency' => get_woocommerce_currency(), 'value' => WC()->cart ? (float) WC()->cart->get_total('edit') : 0];
        } elseif (is_order_received_page()) {
            $orderId = absint(get_query_var('order-received'));
            $orderKey = isset($_GET['key']) && is_scalar($_GET['key']) ? wc_clean(wp_unslash((string) $_GET['key'])) : '';
            $data = self::purchaseData($orderId, $orderKey, get_current_user_id());
            if ($data !== []) $event = 'purchase';
        }
        wp_add_inline_script('petshop-funnel-events', 'window.petshopFunnelConfig=' . wp_json_encode(['consent' => (bool) apply_filters('petshop_analytics_consent_granted', false), 'initialEvent' => $event, 'initialData' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';', 'before');
    }

    /** @return array<string, string|float> */
    public static function purchaseData(int $orderId, string $orderKey, int $currentUserId): array
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order || $orderKey === '' || !hash_equals($order->get_order_key(), $orderKey)) return [];
        $customerId = $order->get_customer_id();
        if ($customerId > 0 && $customerId !== $currentUserId && !current_user_can('manage_woocommerce')) return [];
        return ['transaction_id' => (string) $order->get_order_number(), 'currency' => $order->get_currency(), 'value' => (float) $order->get_total()];
    }

    /** @return array<string, mixed> */
    private static function productData(\WC_Product $product): array
    {
        return ['currency' => get_woocommerce_currency(), 'value' => (float) wc_get_price_to_display($product), 'items' => [['item_id' => $product->get_sku() !== '' ? $product->get_sku() : (string) $product->get_id(), 'item_name' => $product->get_name(), 'price' => (float) wc_get_price_to_display($product)]]];
    }
}
