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

require_once __DIR__ . '/includes/class-storefront-catalog.php';
require_once __DIR__ . '/includes/class-storefront-experience.php';
require_once __DIR__ . '/includes/class-storefront-breadcrumbs.php';

\Petshop\Core\StorefrontCatalog::bootstrap();
\Petshop\Core\StorefrontExperience::bootstrap();
\Petshop\Core\StorefrontBreadcrumbs::bootstrap();

add_action(
    'before_woocommerce_init',
    static function (): void {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
        }
    }
);
