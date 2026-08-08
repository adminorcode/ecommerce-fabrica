<?php

declare(strict_types=1);

namespace Petshop\Core\Storefront;

defined('ABSPATH') || exit;

final class SeoMeta
{
    public static function renderMetaDescription(): void
    {
        if (self::hasSeoPlugin()) {
            return;
        }

        $description = '';
        if (is_product()) {
            $product = wc_get_product(get_queried_object_id());
            $description = $product ? ($product->get_short_description() ?: $product->get_description()) : '';
        } elseif (is_product_category()) {
            $description = term_description(get_queried_object_id(), 'product_cat');
        } elseif (is_shop()) {
            $description = (string) get_theme_mod(
                'petshop_shop_description',
                \Petshop\Core\Settings\DefaultSettings::get('petshop_shop_description')
            );
        }

        $description = wp_trim_words(wp_strip_all_tags($description), 28, '');
        if ($description !== '') {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }
    }

    public static function renderArchiveCanonical(): void
    {
        if (self::hasSeoPlugin() || (!is_shop() && !is_product_category() && !is_search())) {
            return;
        }

        $url = get_pagenum_link(max(1, (int) get_query_var('paged')));
        $selectedSlugs = CatalogFilter::selectedCatalogCategorySlugs();
        if ($selectedSlugs !== []) {
            $url = add_query_arg('petshop_categories', implode(',', $selectedSlugs), $url);
        }
        if (isset($_GET['orderby']) && is_scalar($_GET['orderby'])) {
            $url = add_query_arg('orderby', sanitize_key(wp_unslash((string) $_GET['orderby'])), $url);
        }
        if (is_string($url) && $url !== '') {
            echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        }
    }

    private static function hasSeoPlugin(): bool
    {
        return defined('WPSEO_VERSION')
            || defined('RANK_MATH_VERSION')
            || defined('AIOSEO_VERSION')
            || defined('SEOPRESS_VERSION');
    }

}
