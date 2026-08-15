<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

final class StorefrontExperience
{
    use \Petshop\Core\Compatibility\StorefrontLegacyApi;

    private const VERSION = '3.1.0';
    private const OPTION = 'petshop_storefront_version';
    private const LOCK_OPTION = 'petshop_storefront_migration_lock';
    private const ERROR_OPTION = 'petshop_storefront_migration_error';
    private const COMMERCIAL_MENU_OPTION = 'petshop_commercial_menu_version';

    public static function bootstrap(): void
    {
        add_action('admin_init', [self::class, 'maybeEnsureStorefront'], 40);
        add_action('admin_notices', [self::class, 'renderMigrationNotice']);
        add_action('admin_notices', [self::class, 'renderPlaceholderProductNotice']);
        add_action('product_cat_add_form_fields', [self::class, 'renderAddCategoryFields']);
        add_action('product_cat_edit_form_fields', [self::class, 'renderEditCategoryFields']);
        add_action('created_product_cat', [self::class, 'saveCategoryFields']);
        add_action('edited_product_cat', [self::class, 'saveCategoryFields']);
        add_filter('wp_nav_menu_objects', [self::class, 'filterSeasonalMenuItems']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueCatalogFilterAssets']);
        add_action('template_redirect', [self::class, 'canonicalizeCatalogCategoryFilter'], 0);
        add_action('woocommerce_before_shop_loop', [self::class, 'renderCatalogFilter'], 15);
        add_action('woocommerce_before_shop_loop', [self::class, 'closeCatalogToolbar'], 40);
        add_action('woocommerce_single_product_summary', [self::class, 'renderProductAssurance'], 25);
        add_filter('woocommerce_output_related_products_args', [self::class, 'relatedProductArgs']);
        add_action('pre_get_posts', [self::class, 'resolveExactSkuSearch']);
        add_action('pre_get_posts', [self::class, 'applyCatalogCategoryFilter'], 20);
        add_filter('posts_search', [self::class, 'filterExactSkuSearch'], 20, 2);
        add_action('wp_head', [self::class, 'renderMetaDescription'], 1);
        add_action('wp_head', [self::class, 'renderArchiveCanonical'], 2);
        add_shortcode('petshop_categories', [self::class, 'renderCategoryGrid']);
        add_shortcode('petshop_seasonal_products', [self::class, 'renderSeasonalProducts']);
        add_shortcode('petshop_reviews', [self::class, 'renderReviews']);
        add_shortcode('petshop_featured_products', [self::class, 'renderFeaturedProducts']);
        add_shortcode('petshop_kits_section', [self::class, 'renderKitsSection']);
        add_shortcode('petshop_product_showcase', [self::class, 'renderProductShowcase']);
        add_shortcode('petshop_featured_products_grid', [self::class, 'renderFeaturedProductsGrid']);
        add_shortcode('petshop_kits_section_grid', [self::class, 'renderKitsSectionGrid']);
        add_shortcode('petshop_seasonal_products_grid', [self::class, 'renderSeasonalProductsGrid']);
        add_shortcode('petshop_product_showcase_grid', [self::class, 'renderProductShowcaseGrid']);
        add_shortcode('petshop_reviews_section', [self::class, 'renderReviewsSection']);
        add_shortcode('petshop_support_banner', [self::class, 'renderSupportBanner']);
        add_filter('render_block', [self::class, 'hideEmptyHomeSections'], 10, 2);
        add_filter('woocommerce_product_get_rating_html', [self::class, 'filterProductRatingHtml'], 10, 3);
    }

    public static function maybeEnsureStorefront(): void
    {
        $isCli = defined('WP_CLI') && WP_CLI;
        $isScheduled = get_option(\Petshop\Core\Lifecycle::SCHEDULED_OPTION, false) !== false;
        $needsHomeMigration = self::needsManagedHomeMigration();
        if (
            (!$isCli && !current_user_can('manage_woocommerce') && !current_user_can('manage_options'))
            || !class_exists('WooCommerce')
            || (get_option(self::OPTION) === self::VERSION && !$isScheduled && !$needsHomeMigration)
        ) {
            return;
        }

        $lock = (int) get_option(self::LOCK_OPTION);
        if ($lock > 0 && $lock > time() - 300) {
            return;
        }
        if ($lock > 0) {
            delete_option(self::LOCK_OPTION);
        }
        if (!add_option(self::LOCK_OPTION, time(), '', false)) {
            return;
        }

        try {
            self::ensureStorefront();
            delete_option(self::ERROR_OPTION);
            delete_option(\Petshop\Core\Lifecycle::SCHEDULED_OPTION);
        } catch (\Throwable $error) {
            $errorCode = $error instanceof \Petshop\Core\Migration\MigrationException
                ? $error->errorCode()
                : 'PETSHOP_MIGRATION_FAILED';
            update_option(self::ERROR_OPTION, $errorCode, false);
            error_log(sprintf('Petshop storefront migration failed [%s]: %s', $errorCode, $error->getMessage()));
        } finally {
            delete_option(self::LOCK_OPTION);
        }
    }

    public static function renderMigrationNotice(): void
    {
        $message = (string) get_option(self::ERROR_OPTION, '');
        if ($message === '' || !current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html(sprintf(__('Não foi possível atualizar a configuração da loja (%s). Consulte o log técnico.', 'petshop-core'), $message));
        echo '</p></div>';
    }

    public static function renderPlaceholderProductNotice(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $productId = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if (
            !$screen instanceof \WP_Screen
            || $screen->base !== 'post'
            || $screen->post_type !== 'product'
            || $productId <= 0
            || !(bool) get_post_meta($productId, '_petshop_placeholder_004b', true)
        ) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__(
            'Produto demonstrativo do Plano 004: substitua a fotografia provisória e revise o conteúdo antes da publicação ao cliente.',
            'petshop-core'
        );
        echo '</p></div>';
    }

    public static function ensureStorefront(): void
    {
        \Petshop\Core\Provisioning\StorefrontProvisioner::ensureStorefront();
    }

    private static function needsManagedHomeMigration(): bool
    {
        $homeId = (int) get_option('page_on_front');
        if ($homeId <= 0 || !(bool) get_post_meta($homeId, '_petshop_managed_page', true)) {
            return false;
        }

        $content = (string) get_post_field('post_content', $homeId);
        $shopUrl = (string) wc_get_page_permalink('shop');

        return (int) get_post_meta($homeId, '_petshop_home_schema_version', true)
            < \Petshop\Core\Migration\HomeMigrator::currentSchema()
            || \Petshop\Core\Migration\HomeMigrator::needsProductGridShortcodeRepair($content, $shopUrl)
            || \Petshop\Core\Migration\HomeMigrator::needsSupportSectionRepair($content);
    }
}
