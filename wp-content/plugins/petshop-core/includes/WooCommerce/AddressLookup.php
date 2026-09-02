<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class AddressLookup
{
    public static function bootstrap(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        if (!is_account_page() && !is_checkout()) {
            return;
        }

        $relative = 'assets/js/address-lookup.js';
        $path = plugin_dir_path(PETSHOP_CORE_FILE) . $relative;

        wp_enqueue_script(
            'petshop-address-lookup',
            plugins_url($relative, PETSHOP_CORE_FILE),
            [],
            is_file($path) ? (string) filemtime($path) : '1.0.0',
            true
        );
    }
}