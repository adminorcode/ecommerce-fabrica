<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

defined('ABSPATH') || exit;

/** Loads the Cart Block component after WooCommerce has initialized its block registry. */
final class CartShippingQuoteBlocksIntegration implements IntegrationInterface
{
    private const SCRIPT_HANDLE = 'petshop-cart-shipping-quote-block';

    public function get_name(): string
    {
        return 'petshop-cart-shipping-quote';
    }

    public function initialize(): void
    {
        $relativePath = 'assets/js/cart-shipping-quote-block.js';
        $path = plugin_dir_path(PETSHOP_CORE_FILE) . $relativePath;
        if (!is_readable($path)) {
            return;
        }

        wp_register_script('petshop-cart-estimate', plugins_url('assets/src/shared/cart-estimate.js', PETSHOP_CORE_FILE), [],
            (string) filemtime(plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/src/shared/cart-estimate.js'), true);

        wp_register_script(
            'petshop-cart-feedback',
            plugins_url('assets/src/shared/cart-feedback.js', PETSHOP_CORE_FILE),
            ['wc-blocks-components', 'wc-price-format', 'wp-data', 'wp-element', 'wp-i18n', 'petshop-cart-operations', 'petshop-cart-estimate'],
            (string) filemtime(plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/src/shared/cart-feedback.js'),
            true
        );

        wp_register_script(
            self::SCRIPT_HANDLE,
            plugins_url($relativePath, PETSHOP_CORE_FILE),
            ['wc-blocks-checkout', 'wc-blocks-components', 'wc-price-format', 'wp-data', 'wp-element', 'wp-i18n', 'petshop-cart-operations', 'petshop-cart-feedback'],
            (string) filemtime($path),
            true
        );
    }

    /** @return list<string> */
    public function get_script_handles(): array
    {
        return [self::SCRIPT_HANDLE];
    }

    /** @return list<string> */
    public function get_editor_script_handles(): array
    {
        return [self::SCRIPT_HANDLE];
    }

    /** @return array<string, mixed> */
    public function get_script_data(): array
    {
        return [];
    }
}
