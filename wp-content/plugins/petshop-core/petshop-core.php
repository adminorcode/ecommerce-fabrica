<?php
/**
 * Plugin Name: Petshop Core
 * Plugin URI:  https://example.invalid
 * Description: Regras de negócio e integrações específicas do e-commerce do petshop.
 * Version:     0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * Author:      Petshop
 * Text Domain: petshop-core
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

defined('PETSHOP_CORE_FILE') || define('PETSHOP_CORE_FILE', __FILE__);

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook(__FILE__, [\Petshop\Core\Lifecycle::class, 'activate']);
register_deactivation_hook(__FILE__, [\Petshop\Core\Lifecycle::class, 'deactivate']);

\Petshop\Core\Plugin::bootstrap();

add_action(
    'before_woocommerce_init',
    static function (): void {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                __FILE__,
                true
            );
        }
    }
);
