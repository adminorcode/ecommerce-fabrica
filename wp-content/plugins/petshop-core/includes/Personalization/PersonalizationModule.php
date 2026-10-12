<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization;

use Petshop\Core\Personalization\Admin\OrderPanel;
use Petshop\Core\Personalization\Admin\PersonalizationsPage;
use Petshop\Core\Personalization\Admin\Settings;
use Petshop\Core\Personalization\Blocks\PersonalizableProductsBlock;
use Petshop\Core\Personalization\Http\DownloadController;
use Petshop\Core\Personalization\Http\DraftController;
use Petshop\Core\Personalization\Http\UploadController;
use Petshop\Core\Personalization\Infrastructure\Capabilities;
use Petshop\Core\Personalization\Infrastructure\CleanupScheduler;
use Petshop\Core\Personalization\Infrastructure\PrivateStorage;
use Petshop\Core\Personalization\Infrastructure\Privacy;
use Petshop\Core\Personalization\Infrastructure\SchemaMigrator;
use Petshop\Core\Personalization\Migration\PersonalizePageMigrator;
use Petshop\Core\Personalization\WooCommerce\AccountIntegration;
use Petshop\Core\Personalization\WooCommerce\CartIntegration;
use Petshop\Core\Personalization\WooCommerce\EditorSurface;
use Petshop\Core\Personalization\WooCommerce\OrderIntegration;
use Petshop\Core\Personalization\WooCommerce\ProductConfiguration;
use Petshop\Core\Personalization\WooCommerce\StoreApiIntegration;

defined('ABSPATH') || exit;

/**
 * Entry point of the personalization module (Plano 012).
 */
final class PersonalizationModule
{
    public static function bootstrap(): void
    {
        add_action('plugins_loaded', [self::class, 'maybeMigrate'], 20);
        // Page content needs $wp_rewrite, only available after `plugins_loaded`.
        add_action('wp_loaded', [self::class, 'maybeMigratePage'], 20);

        ProductConfiguration::bootstrap();
        EditorSurface::bootstrap();
        CartIntegration::bootstrap();
        StoreApiIntegration::bootstrap();
        OrderIntegration::bootstrap();
        AccountIntegration::bootstrap();
        UploadController::bootstrap();
        DraftController::bootstrap();
        DownloadController::bootstrap();
        PersonalizableProductsBlock::bootstrap();
        PersonalizationsPage::bootstrap();
        OrderPanel::bootstrap();
        Settings::bootstrap();
        Privacy::bootstrap();
        CleanupScheduler::bootstrap();
    }

    /**
     * Idempotent schema/capability/page upgrade path shared by activation,
     * normal requests and WP-CLI.
     */
    public static function maybeMigrate(): void
    {
        SchemaMigrator::maybeMigrate();
        Capabilities::ensureAssigned();
    }

    public static function maybeMigratePage(): void
    {
        if (!is_admin() && !(defined('WP_CLI') && WP_CLI)) {
            return;
        }

        try {
            PersonalizePageMigrator::maybeMigrate();
        } catch (\Throwable $error) {
            error_log('Petshop personalization: migração da página /personalize/ falhou: ' . $error->getMessage());
        }
    }

    /**
     * Called from the plugin activation hook. Storage failures must not break
     * activation: the feature simply stays unavailable until fixed.
     */
    public static function activate(): void
    {
        SchemaMigrator::install();
        update_option(SchemaMigrator::OPTION, SchemaMigrator::VERSION, false);
        Capabilities::ensureAssigned();
        CleanupScheduler::ensureScheduled();

        try {
            PrivateStorage::ensureReady();
        } catch (\Throwable $error) {
            error_log('Petshop personalization: storage privado indisponível na ativação: ' . $error->getMessage());
        }
    }
}
