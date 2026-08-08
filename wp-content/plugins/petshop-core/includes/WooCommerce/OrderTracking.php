<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class OrderTracking
{
    private const META_CARRIER = '_petshop_tracking_carrier';
    private const META_CODE = '_petshop_tracking_code';
    private const META_URL = '_petshop_tracking_url';

    public static function bootstrap(): void
    {
        add_action('add_meta_boxes', [self::class, 'addMetaBox']);
        add_action('woocommerce_process_shop_order_meta', [self::class, 'saveMetaBox']);
        add_action('woocommerce_order_details_after_order_table', [self::class, 'renderForCustomer']);
        add_action('woocommerce_email_after_order_table', [self::class, 'renderForEmail'], 20, 4);
        add_action('woocommerce_thankyou', [self::class, 'renderNextSteps'], 20);
    }

    public static function addMetaBox(): void
    {
        $screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';
        add_meta_box('petshop-order-tracking', __('Rastreamento da entrega', 'petshop-core'), [self::class, 'renderMetaBox'], $screen, 'side', 'default');
        if ($screen !== 'shop_order') add_meta_box('petshop-order-tracking', __('Rastreamento da entrega', 'petshop-core'), [self::class, 'renderMetaBox'], 'shop_order', 'side', 'default');
    }

    public static function renderMetaBox(mixed $postOrOrder): void
    {
        $order = $postOrOrder instanceof \WC_Order ? $postOrOrder : wc_get_order((int) ($postOrOrder->ID ?? 0));
        if (!$order instanceof \WC_Order) return;
        wp_nonce_field('petshop_save_tracking_' . $order->get_id(), '_petshop_tracking_nonce');
        foreach ([self::META_CARRIER => __('Transportadora', 'petshop-core'), self::META_CODE => __('Código', 'petshop-core'), self::META_URL => __('URL de rastreamento', 'petshop-core')] as $key => $label) {
            $type = $key === self::META_URL ? 'url' : 'text';
            echo '<p><label for="' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><input class="widefat" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" type="' . esc_attr($type) . '" value="' . esc_attr((string) $order->get_meta($key, true)) . '"></p>';
        }
    }

    public static function saveMetaBox(int $orderId): void
    {
        if (!isset($_POST['_petshop_tracking_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['_petshop_tracking_nonce'])), 'petshop_save_tracking_' . $orderId) || !current_user_can('edit_shop_order', $orderId)) return;
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) return;
        foreach ([self::META_CARRIER, self::META_CODE, self::META_URL] as $key) {
            $raw = isset($_POST[$key]) && is_scalar($_POST[$key]) ? wp_unslash((string) $_POST[$key]) : '';
            $value = $key === self::META_URL ? esc_url_raw($raw) : sanitize_text_field($raw);
            $value === '' ? $order->delete_meta_data($key) : $order->update_meta_data($key, $value);
        }
        $order->save();
    }

    public static function renderForCustomer(\WC_Order $order): void
    {
        self::render($order, false);
    }

    public static function renderForEmail(\WC_Order $order, bool $sentToAdmin, bool $plainText, mixed $email): void
    {
        unset($email);
        if (!$sentToAdmin) self::render($order, $plainText);
    }

    public static function renderNextSteps(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) return;
        $text = trim((string) get_theme_mod('petshop_order_next_steps', \Petshop\Core\Settings\DefaultSettings::get('petshop_order_next_steps')));
        if ($text === '') return;
        echo '<section class="petshop-order-next-steps"><h2>' . esc_html__('Próximos passos', 'petshop-core') . '</h2><p>' . esc_html($text) . '</p></section>';
    }

    private static function render(\WC_Order $order, bool $plainText): void
    {
        $carrier = trim((string) $order->get_meta(self::META_CARRIER, true));
        $code = trim((string) $order->get_meta(self::META_CODE, true));
        $url = trim((string) $order->get_meta(self::META_URL, true));
        if ($carrier === '' && $code === '' && $url === '') return;
        if ($plainText) {
            echo "\n" . esc_html__('Rastreamento da entrega', 'petshop-core') . "\n";
            if ($carrier !== '') echo esc_html__('Transportadora:', 'petshop-core') . ' ' . esc_html($carrier) . "\n";
            if ($code !== '') echo esc_html__('Código:', 'petshop-core') . ' ' . esc_html($code) . "\n";
            if ($url !== '') echo esc_url($url) . "\n";
            return;
        }
        echo '<section class="petshop-order-tracking"><h2>' . esc_html__('Rastreamento da entrega', 'petshop-core') . '</h2><dl>';
        if ($carrier !== '') echo '<div><dt>' . esc_html__('Transportadora', 'petshop-core') . '</dt><dd>' . esc_html($carrier) . '</dd></div>';
        if ($code !== '') echo '<div><dt>' . esc_html__('Código', 'petshop-core') . '</dt><dd>' . esc_html($code) . '</dd></div>';
        echo '</dl>';
        if ($url !== '') echo '<p><a class="button" href="' . esc_url($url) . '" rel="noopener noreferrer">' . esc_html__('Acompanhar entrega', 'petshop-core') . '</a></p>';
        echo '</section>';
    }
}
