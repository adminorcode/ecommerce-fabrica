<?php

declare(strict_types=1);

namespace Petshop\Core\Compatibility;

defined('ABSPATH') || exit;

trait StorefrontLegacyApi
{
    private static function legacyHeroContent(string $shopUrl, int $heroId): string
    {
        return \Petshop\Core\Migration\HomeMigrator::legacyHeroMarkup($shopUrl, $heroId);
    }

    private static function campaignHeroContent(string $shopUrl, int $heroId): string
    {
        return \Petshop\Core\Migration\HomeMigrator::campaignHeroMarkup($shopUrl, $heroId);
    }

    private static function heroContent(string $shopUrl, int $heroId): string
    {
        return \Petshop\Core\Migration\HomeMigrator::heroMarkup($shopUrl, $heroId);
    }

    private static function benefitsContent(array $overrides = []): string
    {
        return \Petshop\Core\Migration\HomeMigrator::benefitsMarkup($overrides);
    }

    private static function stampNewManagedHome(int $homeId, string $shopUrl, int $heroId): void
    {
        \Petshop\Core\Provisioning\StorefrontProvisioner::stampManagedHome($homeId, $shopUrl, $heroId);
    }

    public static function renderAddCategoryFields(): void
    {
        \Petshop\Core\Admin\CategoryTermMeta::renderAddCategoryFields();
    }
    public static function renderMetaDescription(): void
    {
        \Petshop\Core\Storefront\SeoMeta::renderMetaDescription();
    }

    public static function renderArchiveCanonical(): void
    {
        \Petshop\Core\Storefront\SeoMeta::renderArchiveCanonical();
    }


    public static function renderEditCategoryFields(\WP_Term $term): void
    {
        \Petshop\Core\Admin\CategoryTermMeta::renderEditCategoryFields($term);
    }

    public static function saveCategoryFields(int $termId): void
    {
        \Petshop\Core\Admin\CategoryTermMeta::saveCategoryFields($termId);
    }

    public static function filterSeasonalMenuItems(array $items): array
    {
        return array_values(
            array_filter(
                $items,
                static function (\WP_Post $item): bool {
                    if ($item->type !== 'taxonomy' || $item->object !== 'product_cat') {
                        return true;
                    }

                    $termId = (int) $item->object_id;
                    return (bool) get_term_meta($termId, 'petshop_visible_in_menu', true);
                }
            )
        );
    }

    public static function renderCatalogFilter(): void
    {
        \Petshop\Core\Storefront\CatalogFilter::renderCatalogFilter();
    }

    public static function enqueueCatalogFilterAssets(): void
    {
        \Petshop\Core\Storefront\CatalogFilter::enqueueCatalogFilterAssets();
    }

    public static function applyCatalogCategoryFilter(\WP_Query $query): void
    {
        \Petshop\Core\Storefront\CatalogFilter::applyCatalogCategoryFilter($query);
    }

    public static function canonicalizeCatalogCategoryFilter(): void
    {
        \Petshop\Core\Storefront\CatalogFilter::canonicalizeCatalogCategoryFilter();
    }

    private static function selectedCatalogCategorySlugs(): array
    {
        return \Petshop\Core\Storefront\CatalogFilter::selectedCatalogCategorySlugs();
    }

    public static function closeCatalogToolbar(): void
    {
        \Petshop\Core\Storefront\CatalogFilter::closeCatalogToolbar();
    }

    public static function resolveExactSkuSearch(\WP_Query $query): void
    {
        \Petshop\Core\Storefront\CatalogFilter::resolveExactSkuSearch($query);
    }

    public static function filterExactSkuSearch(string $searchSql, \WP_Query $query): string
    {
        return \Petshop\Core\Storefront\CatalogFilter::filterExactSkuSearch($searchSql, $query);
    }

    public static function allowSingleSearchResultRedirect(bool $redirect): bool
    {
        return \Petshop\Core\Storefront\CatalogFilter::allowSingleSearchResultRedirect($redirect);
    }

    public static function renderCategoryGrid(array $attributes = []): string
    {
        return \Petshop\Core\Storefront\CategoryGrid::renderCategoryGrid($attributes);
    }

    public static function enqueueCategoryPreviewAssets(): void
    {
        \Petshop\Core\Storefront\CategoryGrid::enqueueCategoryPreviewAssets();
    }

    public static function renderSeasonalProducts(array $attributes = []): string
    {
        return \Petshop\Core\Storefront\ProductShortcodes::renderSeasonalProducts($attributes);
    }

    public static function renderReviews(array $attributes = []): string
    {
        return \Petshop\Core\Storefront\ProductShortcodes::renderReviews($attributes);
    }

    public static function filterProductRatingHtml(string $ratingHtml, float $rating, int $count): string
    {
        return \Petshop\Core\Storefront\ProductShortcodes::filterProductRatingHtml($ratingHtml, $rating, $count);
    }

    private static function hasCatalogSales(): bool
    {
        return \Petshop\Core\Storefront\ProductShortcodes::hasCatalogSales();
    }

    public static function renderFeaturedProducts(array $attributes = []): string
    {
        return \Petshop\Core\Storefront\ProductShortcodes::renderFeaturedProducts($attributes);
    }

    public static function renderKitsSection(array $attributes = []): string
    {
        return \Petshop\Core\Storefront\ProductShortcodes::renderKitsSection($attributes);
    }

    public static function renderProductShowcase(array $attributes = []): string
    {
        return \Petshop\Core\Storefront\ProductShortcodes::renderProductShowcase($attributes);
    }

    public static function renderFeaturedProductsGrid(array $attributes = []): string
    {
        return \Petshop\Core\Storefront\ProductGridShortcodes::renderFeaturedProductsGrid($attributes);
    }

    public static function renderKitsSectionGrid(array $attributes = []): string
    {
        return \Petshop\Core\Storefront\ProductGridShortcodes::renderKitsSectionGrid($attributes);
    }

    public static function renderSeasonalProductsGrid(array $attributes = []): string
    {
        return \Petshop\Core\Storefront\ProductGridShortcodes::renderSeasonalProductsGrid($attributes);
    }

    public static function renderProductShowcaseGrid(array $attributes = []): string
    {
        return \Petshop\Core\Storefront\ProductGridShortcodes::renderProductShowcaseGrid($attributes);
    }

    public static function hideEmptyHomeSections(string $content, array $block): string
    {
        return \Petshop\Core\Storefront\ProductGridShortcodes::hideEmptyHomeSections($content, $block);
    }

    private static function showcaseSectionGutenbergMarkup(string $className, string $headingId, string $title, string $ctaLabel, string $ctaUrl, string $intro, string $gridShortcode): string
    {
        return \Petshop\Core\Storefront\ProductShowcaseView::showcaseSectionGutenbergMarkup($className, $headingId, $title, $ctaLabel, $ctaUrl, $intro, $gridShortcode);
    }

    private static function reviewsSectionGutenbergMarkup(int $limit = 3): string
    {
        return \Petshop\Core\Storefront\ProductShowcaseView::reviewsSectionGutenbergMarkup($limit);
    }

    private static function wrapProductShowcase(string $className, string $title, string $content, string $ctaLabel, string $ctaUrl, string $intro = ''): string
    {
        return \Petshop\Core\Storefront\ProductShowcaseView::wrapProductShowcase(
            $className,
            sanitize_title($title) . '-heading',
            $title,
            $ctaLabel,
            $ctaUrl,
            $intro,
            $content
        );
    }

    private static function resolveShopCtaUrl(string $ctaUrl): string
    {
        return \Petshop\Core\Storefront\ProductShowcaseView::resolveShopCtaUrl($ctaUrl);
    }

    private static function buildCatalogCategoryFilterUrl(array $slugs): string
    {
        return \Petshop\Core\Storefront\ProductShowcaseView::buildCatalogCategoryFilterUrl($slugs);
    }

    private static function resolveSeasonalCtaUrl(string $ctaUrl, array $terms): string
    {
        return \Petshop\Core\Storefront\ProductShowcaseView::resolveSeasonalCtaUrl($ctaUrl, $terms);
    }

    private static function resolveCategoryCtaUrl(string $ctaUrl, string $categorySlugs): string
    {
        return \Petshop\Core\Storefront\ProductShowcaseView::resolveCategoryCtaUrl($ctaUrl, $categorySlugs);
    }

    private static function resolveSupportBannerUrl(string $fallbackUrl = ''): string
    {
        return \Petshop\Core\Storefront\SupportContent::resolveSupportBannerUrl($fallbackUrl);
    }

    private static function supportBannerContent(int $imageId, string $url): string
    {
        return \Petshop\Core\Storefront\SupportContent::supportBannerContent($imageId, $url);
    }

    private static function applyHomeSchemaTwentyTwo(string $content): string
    {
        return \Petshop\Core\Storefront\SupportContent::applyHomeSchemaTwentyTwo($content);
    }

    private static function applyHomeSchemaTwentyThree(string $content): string
    {
        return \Petshop\Core\Storefront\SupportContent::applyHomeSchemaTwentyThree($content);
    }

    private static function applyHomeSchemaTwentyFour(string $content, string $shopUrl, int $heroId): string
    {
        return \Petshop\Core\Storefront\SupportContent::applyHomeSchemaTwentyFour($content, $shopUrl, $heroId);
    }

    public static function renderSupportBanner(): string
    {
        return \Petshop\Core\Storefront\SupportContent::renderSupportBanner();
    }

    public static function renderReviewsSection(array $attributes = []): string
    {
        return \Petshop\Core\Storefront\SupportContent::renderReviewsSection($attributes);
    }

    public static function renderProductAssurance(): void
    {
        \Petshop\Core\Storefront\SupportContent::renderProductAssurance();
    }

    public static function relatedProductArgs(array $args): array
    {
        return \Petshop\Core\Storefront\SupportContent::relatedProductArgs($args);
    }
}
