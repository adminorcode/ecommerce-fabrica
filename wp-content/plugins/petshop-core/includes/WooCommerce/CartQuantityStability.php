<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Removes only the incompatible third-party cart calculator assets.
 *
 * Quantity changes and Cart Block state remain owned by WooCommerce. This
 * class deliberately does not enqueue a guard, intercept network calls, or
 * render a parallel postcode field.
 */
final class CartQuantityStability
{
    /**
     * @var list<string>
     */
    private const CONFLICTING_SCRIPTS = [
        'woo-better-cart-custom-postcode',
        'wc-better-shipping-calculator-for-brazil-frontend',
        'wc-better-shipping-calculator-for-brazil-progress-bar',
    ];

    public static function bootstrap(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'dequeueConflictingScripts'], 1001);
        add_action('wp_print_scripts', [self::class, 'dequeueConflictingScripts'], 5);
    }

    public static function dequeueConflictingScripts(): void
    {
        if (!self::isCartStorefront()) {
            return;
        }

        foreach (self::CONFLICTING_SCRIPTS as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
        }
    }

    private static function isCartStorefront(): bool
    {
        if (is_admin()) {
            return false;
        }

        if (function_exists('is_cart') && is_cart()) {
            return true;
        }

        return function_exists('has_block') && has_block('woocommerce/cart');
    }
}
