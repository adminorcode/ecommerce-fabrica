<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Keeps Cart Block quantity updates after a shipping quote.
 *
 * The Brazilian calculator plugin reconsults CEP on every update-item and
 * invalidates the cart store. Late Store API snapshots also flash the old
 * quantity while shipping recalculates.
 */
final class CartQuantityStability
{
    public const SCRIPT_HANDLE = 'petshop-cart-quantity-stability';

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
        add_filter('pre_option_woo_better_calc_enable_cart_page', [self::class, 'disablePluginCartCalculator']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueGuard'], 1);
        add_action('wp_enqueue_scripts', [self::class, 'dequeueConflictingScripts'], 1001);
        add_action('wp_print_scripts', [self::class, 'dequeueConflictingScripts'], 5);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets'], 101);
        add_action('wp_footer', [self::class, 'renderRoot'], 5);
    }

    public static function disablePluginCartCalculator(mixed $value): mixed
    {
        if (!self::isCartStorefront()) {
            return $value;
        }

        return 'no';
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

    public static function enqueueGuard(): void
    {
        if (!self::isCartStorefront()) {
            return;
        }

        $path = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/cart-quantity-guard.js';
        wp_enqueue_script(
            'petshop-cart-quantity-guard',
            plugins_url('assets/js/cart-quantity-guard.js', PETSHOP_CORE_FILE),
            [],
            is_file($path) ? (string) filemtime($path) : '1.0.0',
            false
        );
        wp_localize_script('petshop-cart-quantity-guard', 'petshopCartQtyConfig', [
            'shippingDebounceMs' => 1000,
        ]);
    }

    public static function enqueueAssets(): void
    {
        if (!self::isCartStorefront()) {
            return;
        }

        $path = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/cart-quantity-stability.js';
        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            plugins_url('assets/js/cart-quantity-stability.js', PETSHOP_CORE_FILE),
            ['wp-data', 'petshop-cart-quantity-guard'],
            is_file($path) ? (string) filemtime($path) : '1.0.0',
            true
        );
        wp_localize_script(self::SCRIPT_HANDLE, 'petshopCartShipping', [
            'label' => __('CEP', 'petshop-core'),
            'placeholder' => __('00000-000', 'petshop-core'),
            'button' => __('Calcular', 'petshop-core'),
            'invalid' => __('Informe um CEP com 8 dígitos.', 'petshop-core'),
            'updating' => __('Atualizando opções de entrega...', 'petshop-core'),
            'emptyRates' => __('Não há opção de entrega para este CEP.', 'petshop-core'),
            'error' => __('Não foi possível calcular a entrega. Tente novamente.', 'petshop-core'),
        ]);
    }

    public static function renderRoot(): void
    {
        if (!self::isCartStorefront()) {
            return;
        }

        echo '<div class="petshop-cart-shipping" data-petshop-cart-shipping hidden></div>';
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
