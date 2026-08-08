<?php

declare(strict_types=1);

namespace Petshop\Core\Storefront;

defined('ABSPATH') || exit;

final class ProductShowcaseView
{
    public static function showcaseSectionGutenbergMarkup(
        string $sectionClass,
        string $headingId,
        string $title,
        string $ctaLabel,
        string $ctaUrl,
        string $intro,
        string $gridShortcode
    ): string {
        $headingId = sanitize_html_class($headingId);
        $titleEsc = esc_html($title);
        $ctaLabelEsc = esc_html($ctaLabel);
        $ctaUrlEsc = esc_url($ctaUrl);
        $introBlock = '';
        if (trim($intro) !== '') {
            $introEsc = esc_html($intro);
            $introBlock = <<<INTRO


<!-- wp:paragraph {"className":"petshop-product-showcase__intro"} -->
<p class="petshop-product-showcase__intro">{$introEsc}</p>
<!-- /wp:paragraph -->
INTRO;
        }

        $ctaBlock = '';
        if (trim($ctaLabel) !== '' && trim($ctaUrl) !== '') {
            $ctaBlock = <<<CTA

<!-- wp:paragraph {"className":"petshop-section-head__cta"} -->
<p class="petshop-section-head__cta"><a class="petshop-section-head__link" href="{$ctaUrlEsc}">{$ctaLabelEsc}</a></p>
<!-- /wp:paragraph -->
CTA;
        }

        return <<<BLOCKS
<!-- wp:group {"tagName":"section","className":"petshop-section petshop-product-showcase {$sectionClass}","layout":{"type":"constrained"}} -->
<section class="wp-block-group petshop-section petshop-product-showcase {$sectionClass}"><!-- wp:group {"className":"petshop-section-head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group petshop-section-head"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading" id="{$headingId}">{$titleEsc}</h2>
<!-- /wp:heading -->
{$ctaBlock}</div>
<!-- /wp:group -->
{$introBlock}
<!-- wp:shortcode -->{$gridShortcode}<!-- /wp:shortcode --></section>
<!-- /wp:group -->
BLOCKS;
    }

    public static function reviewsSectionGutenbergMarkup(int $limit = 3): string
    {
        $titleEsc = esc_html__('Quem compra, conta', 'petshop-core');
        $introEsc = esc_html__(
            'Avaliações reais e aprovadas dos produtos aparecem nesta seção.',
            'petshop-core'
        );
        $gridShortcode = sprintf('[petshop_reviews limit="%d"]', max(1, $limit));

        return <<<BLOCKS
<!-- wp:group {"tagName":"section","className":"petshop-section petshop-reviews-section","layout":{"type":"constrained"}} -->
<section class="wp-block-group petshop-section petshop-reviews-section"><!-- wp:group {"className":"petshop-section-head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group petshop-section-head"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading" id="petshop-reviews-heading">{$titleEsc}</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"petshop-reviews-section__intro"} -->
<p class="petshop-reviews-section__intro">{$introEsc}</p>
<!-- /wp:paragraph -->
<!-- wp:shortcode -->{$gridShortcode}<!-- /wp:shortcode --></section>
<!-- /wp:group -->
BLOCKS;
    }

    public static function renderSectionHead(
        string $title,
        string $headingId,
        string $ctaLabel,
        string $ctaUrl
    ): string {
        $headingId = sanitize_html_class($headingId);
        if ($headingId === '') {
            $headingId = 'petshop-section-heading';
        }

        ob_start();
        echo '<div class="petshop-section-head">';
        echo '<h2 id="' . esc_attr($headingId) . '" class="wp-block-heading petshop-section-head__title">';
        echo esc_html($title);
        echo '</h2>';
        if (trim($ctaLabel) !== '' && trim($ctaUrl) !== '') {
            echo '<a class="petshop-section-head__link" href="' . esc_url($ctaUrl) . '">';
            echo esc_html($ctaLabel);
            echo '</a>';
        }
        echo '</div>';

        return (string) ob_get_clean();
    }

    public static function wrapProductShowcase(
        string $sectionClass,
        string $headingId,
        string $title,
        string $ctaLabel,
        string $ctaUrl,
        string $intro,
        string $productsHtml
    ): string {
        if (!preg_match('/class="[^"]*\bproduct\b[^"]*"/', $productsHtml)) {
            return '';
        }

        ob_start();
        echo '<section class="petshop-section petshop-product-showcase ' . esc_attr(trim($sectionClass)) . '" aria-labelledby="' . esc_attr(sanitize_html_class($headingId)) . '">';
        echo self::renderSectionHead($title, $headingId, $ctaLabel, $ctaUrl);
        if (trim($intro) !== '') {
            echo '<p class="petshop-product-showcase__intro">' . esc_html($intro) . '</p>';
        }
        echo $productsHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</section>';

        return (string) ob_get_clean();
    }

    public static function resolveShopCtaUrl(string $ctaUrl): string
    {
        $ctaUrl = trim($ctaUrl);
        if ($ctaUrl !== '') {
            return $ctaUrl;
        }

        $shopUrl = wc_get_page_permalink('shop');

        return is_string($shopUrl) ? $shopUrl : home_url('/shop/');
    }

    /**
     * @param list<string> $slugs
     */
    public static function buildCatalogCategoryFilterUrl(array $slugs): string
    {
        $slugs = array_values(array_unique(array_filter(array_map(
            static fn (string $slug): string => sanitize_title($slug),
            $slugs
        ))));

        if ($slugs === []) {
            return self::resolveShopCtaUrl('');
        }

        if (count($slugs) === 1) {
            $term = get_term_by('slug', $slugs[0], 'product_cat');
            if ($term instanceof \WP_Term) {
                $termLink = get_term_link($term);
                if (!is_wp_error($termLink)) {
                    return (string) $termLink;
                }
            }
        }

        $shopUrl = wc_get_page_permalink('shop');
        if (!is_string($shopUrl) || $shopUrl === '') {
            $shopUrl = home_url('/shop/');
        }

        return add_query_arg('petshop_categories', implode(',', $slugs), $shopUrl);
    }

    /**
     * @param list<\WP_Term> $terms
     */
    public static function resolveSeasonalCtaUrl(string $ctaUrl, array $terms): string
    {
        $ctaUrl = trim($ctaUrl);
        if ($ctaUrl !== '') {
            return $ctaUrl;
        }

        $slugs = array_values(array_filter(array_map(
            static fn (\WP_Term $term): string => $term->slug,
            array_filter($terms, static fn ($term): bool => $term instanceof \WP_Term)
        )));

        if (count($slugs) > 1) {
            return self::buildCatalogCategoryFilterUrl($slugs);
        }

        $collections = get_page_by_path('colecoes');
        if ($collections instanceof \WP_Post && $collections->post_status === 'publish') {
            return (string) get_permalink($collections);
        }

        return self::buildCatalogCategoryFilterUrl($slugs);
    }

    public static function resolveCategoryCtaUrl(string $ctaUrl, string $categorySlugs): string
    {
        $ctaUrl = trim($ctaUrl);
        if ($ctaUrl !== '') {
            return $ctaUrl;
        }

        $slugs = array_values(array_filter(array_map('trim', explode(',', $categorySlugs))));

        return self::buildCatalogCategoryFilterUrl($slugs);
    }
}
