<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce\PaymentDiscount;

defined('ABSPATH') || exit;

final class CheckoutSync
{
    public const NAMESPACE = 'petshop-payment-discount';

    public static function bootstrap(): void
    {
        add_action('woocommerce_blocks_loaded', [self::class, 'registerStoreApi']);
        add_action('woocommerce_checkout_update_order_review', [self::class, 'syncClassicReview']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueCheckoutScript']);
    }

    public static function registerStoreApi(): void
    {
        if (!function_exists('woocommerce_store_api_register_update_callback')) {
            return;
        }

        woocommerce_store_api_register_update_callback([
            'namespace' => self::NAMESPACE,
            'callback' => [self::class, 'updateFromStoreApi'],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function updateFromStoreApi(array $data): void
    {
        $method = isset($data['paymentMethod']) && is_scalar($data['paymentMethod'])
            ? (string) $data['paymentMethod']
            : '';
        Cart::remember($method);
    }

    public static function syncClassicReview(string $posted): void
    {
        $data = [];
        parse_str($posted, $data);
        $method = isset($data['payment_method']) && is_scalar($data['payment_method'])
            ? (string) $data['payment_method']
            : '';
        if ($method === '') {
            return;
        }

        Cart::remember($method);
    }

    public static function enqueueCheckoutScript(): void
    {
        if (
            is_admin()
            || !function_exists('is_checkout')
            || !is_checkout()
            || (function_exists('is_order_received_page') && is_order_received_page())
            || (function_exists('is_checkout_pay_page') && is_checkout_pay_page())
        ) {
            return;
        }

        $relative = 'assets/js/payment-discount-checkout.js';
        $path = plugin_dir_path(PETSHOP_CORE_FILE) . $relative;
        $dependencies = ['wp-data'];
        if (wp_script_is('wc-blocks-checkout', 'registered')) {
            $dependencies[] = 'wc-blocks-checkout';
        }

        wp_enqueue_script(
            'petshop-payment-discount-checkout',
            plugins_url($relative, PETSHOP_CORE_FILE),
            $dependencies,
            is_file($path) ? (string) filemtime($path) : '1.0.0',
            true
        );
    }
}
