<?php

declare(strict_types=1);

namespace Petshop\Core\Provisioning;

use Petshop\Core\Migration\HomeHeroContent;
use Petshop\Core\Migration\HomeLegacySchemas;
use Petshop\Core\Migration\HomeMigrator;
use Petshop\Core\Storefront\ProductShortcodes;
use Petshop\Core\Storefront\ProductShowcaseView;
use Petshop\Core\Storefront\SupportContent;

defined('ABSPATH') || exit;

final class StorefrontProvisioner
{
    private const VERSION = '3.1.0';
    private const OPTION = 'petshop_storefront_version';
    private const COMMERCIAL_MENU_OPTION = 'petshop_commercial_menu_version';

    use StorefrontPages;
    use StorefrontProvisioning;
    use HomeHeroContent;
    use HomeLegacySchemas;

    public static function ensureStorefront(): void
    {
        $isScheduled = get_option(\Petshop\Core\Lifecycle::SCHEDULED_OPTION, false) !== false;
        if (
            !class_exists('WooCommerce')
            || (get_option(self::OPTION) === self::VERSION && !$isScheduled && !self::needsManagedHomeMigration())
        ) {
            return;
        }

        $isInitialInstall = get_option(self::OPTION, false) === false;
        \Petshop\Core\WooCommerce\Routes::migratePages();
        \Petshop\Core\WooCommerce\CartCheckout::migrate();
        $aboutId = self::ensurePage(
            'sobre-o-autelie',
            'Sobre o Auteliê',
            '<!-- wp:heading --><h2 class="wp-block-heading">Acessórios feitos para celebrar cada pet</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>O Auteliê Moda Pet reúne acessórios com acabamento cuidadoso para tutores e profissionais de banho e tosa.</p><!-- /wp:paragraph -->'
        );
        $supportId = self::ensurePage(
            'atendimento',
            'Atendimento',
            '<!-- wp:heading --><h2 class="wp-block-heading">Como podemos ajudar?</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Use os canais oficiais informados pela loja para tirar dúvidas sobre produtos, pedidos e cuidados com os acessórios.</p><!-- /wp:paragraph -->'
        );
        $shippingId = self::ensurePage(
            'envios-e-entregas',
            'Envios e entregas',
            '<!-- wp:heading --><h2 class="wp-block-heading">Envios e entregas</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Prazo, modalidade e valor de entrega são apresentados antes da conclusão do pedido, conforme o endereço informado.</p><!-- /wp:paragraph -->'
        );
        $personalizeId = self::ensurePage(
            'personalize',
            'Personalize',
            '<!-- wp:heading --><h2 class="wp-block-heading">Personalização em preparação</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Esta área está reservada para uma futura experiência de personalização. O catálogo atual continua disponível normalmente.</p><!-- /wp:paragraph -->'
        );
        $collectionsId = self::ensurePage(
            'colecoes',
            'Coleções',
            '<!-- wp:heading --><h2 class="wp-block-heading">Coleções para cada ocasião</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Conheça as coleções disponíveis e encontre acessórios para diferentes estilos e épocas do ano.</p><!-- /wp:paragraph -->'
            . '<!-- wp:shortcode -->[petshop_categories limit="8"]<!-- /wp:shortcode -->'
        );
        $policiesId = self::ensurePage(
            'politicas-da-loja',
            'Políticas da loja',
            '<!-- wp:heading --><h2 class="wp-block-heading">Políticas da loja</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Consulte aqui as políticas comerciais, de privacidade e de troca aprovadas para a loja antes da publicação.</p><!-- /wp:paragraph -->'
        );
        $wishlistId = self::ensurePage(
            'lista-de-desejos',
            'Lista de desejos',
            self::wishlistPageContent()
        );
        if (get_theme_mod('petshop_wishlist_page', null) === null) {
            set_theme_mod('petshop_wishlist_page', $wishlistId);
        }
        self::migrateWishlistPage($wishlistId);
        $heroId = self::placeholderAttachment('hero-wide');
        if ($heroId <= 0) {
            throw new \Petshop\Core\Migration\MigrationException(
                'PETSHOP_HERO_ATTACHMENT_MISSING',
                'Imagem hero-wide ausente; execute o seed 004b e tente novamente.'
            );
        }
        $existingHome = get_page_by_path('inicio');
        $homeId = self::ensurePage(
            'inicio',
            'Início',
            self::homeContent(
                (string) wc_get_page_permalink('shop'),
                (string) get_permalink($supportId),
                $heroId
            )
        );
        if (!$existingHome instanceof \WP_Post) {
            self::stampNewManagedHome($homeId, (string) wc_get_page_permalink('shop'), $heroId);
        }

        HomeMigrator::migrate(
            $homeId,
            (string) wc_get_page_permalink('shop'),
            (string) get_permalink($supportId),
            $heroId
        );
        if ($isInitialInstall) {
            self::upgradeWooCommerceBlocks();
            self::configureMenus($homeId, $aboutId, $supportId, $shippingId, $personalizeId, $policiesId);
            self::configureTheme($homeId);
            update_option('show_on_front', 'page');
            update_option('page_on_front', $homeId);
            update_option('woocommerce_coming_soon', 'no');
            update_option('woocommerce_hide_out_of_stock_items', 'yes');
        } else {
            self::addPolicyToManagedFooter($policiesId);
        }
        self::ensureCommercialMenu($collectionsId, $personalizeId);
        self::ensureHeaderDefaults();

        flush_rewrite_rules(false);

        update_option(self::OPTION, self::VERSION, false);
    }

    private static function needsManagedHomeMigration(): bool
    {
        $homeId = (int) get_option('page_on_front');
        if ($homeId <= 0 || !(bool) get_post_meta($homeId, '_petshop_managed_page', true)) {
            return false;
        }

        $content = (string) get_post_field('post_content', $homeId);
        $shopUrl = (string) wc_get_page_permalink('shop');

        return (int) get_post_meta($homeId, '_petshop_home_schema_version', true) < HomeMigrator::currentSchema()
            || HomeMigrator::needsProductGridShortcodeRepair($content, $shopUrl)
            || HomeMigrator::needsSupportSectionRepair($content);
    }

    public static function stampManagedHome(int $homeId, string $shopUrl, int $heroId): void
    {
        self::stampNewManagedHome($homeId, $shopUrl, $heroId);
    }

    private static function resolveSupportBannerUrl(string $fallbackUrl = ''): string
    {
        return SupportContent::resolveSupportBannerUrl($fallbackUrl);
    }

    private static function supportBannerContent(int $imageId, string $url): string
    {
        return SupportContent::supportBannerContent($imageId, $url);
    }

    private static function showcaseSectionGutenbergMarkup(string $className, string $headingId, string $title, string $ctaLabel, string $ctaUrl, string $intro, string $gridShortcode): string
    {
        return ProductShowcaseView::showcaseSectionGutenbergMarkup($className, $headingId, $title, $ctaLabel, $ctaUrl, $intro, $gridShortcode);
    }

    private static function reviewsSectionGutenbergMarkup(int $limit = 3): string
    {
        return ProductShowcaseView::reviewsSectionGutenbergMarkup($limit);
    }

    private static function resolveShopCtaUrl(string $ctaUrl): string
    {
        return ProductShowcaseView::resolveShopCtaUrl($ctaUrl);
    }

    private static function resolveSeasonalCtaUrl(string $ctaUrl, array $terms): string
    {
        return ProductShowcaseView::resolveSeasonalCtaUrl($ctaUrl, $terms);
    }

    private static function resolveCategoryCtaUrl(string $ctaUrl, string $categorySlugs): string
    {
        return ProductShowcaseView::resolveCategoryCtaUrl($ctaUrl, $categorySlugs);
    }

    private static function hasCatalogSales(): bool
    {
        return ProductShortcodes::hasCatalogSales();
    }
}
