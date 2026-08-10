<?php

declare(strict_types=1);

namespace Petshop\Core;

use Petshop\Core\Admin\Customizer;
use Petshop\Core\Cli\MigrateCommand;
use Petshop\Core\Storefront\SearchExperience;
use Petshop\Core\WooCommerce\Routes;
use Petshop\Core\WooCommerce\ProductDetails;
use Petshop\Core\WooCommerce\CartCheckout;
use Petshop\Core\WooCommerce\OrderTracking;
use Petshop\Core\WooCommerce\GuestAccount;
use Petshop\Core\Analytics\FunnelEvents;

defined('ABSPATH') || exit;

final class Plugin
{
    public static function bootstrap(): void
    {
        CategoryIcons::bootstrap();
        StorefrontCatalog::bootstrap();
        StorefrontExperience::bootstrap();
        StorefrontBreadcrumbs::bootstrap();
        StorefrontProductCard::bootstrap();
        StorefrontWishlist::bootstrap();
        Routes::bootstrap();
        SearchExperience::bootstrap();
        ProductDetails::bootstrap();
        CartCheckout::bootstrap();
        OrderTracking::bootstrap();
        GuestAccount::bootstrap();
        FunnelEvents::bootstrap();
        HomeCampaignBlocks::bootstrap();
        ProductGridBlock::bootstrap();
        Customizer::bootstrap();

        add_action('init', [self::class, 'loadTextdomain'], 1);

        if (defined('WP_CLI') && WP_CLI) {
            MigrateCommand::register();
        }
    }

    public static function loadTextdomain(): void
    {
        load_plugin_textdomain('petshop-core', false, dirname(plugin_basename(PETSHOP_CORE_FILE)) . '/languages');
    }
}
