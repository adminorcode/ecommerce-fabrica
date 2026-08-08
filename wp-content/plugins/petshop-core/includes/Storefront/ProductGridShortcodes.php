<?php

declare(strict_types=1);

namespace Petshop\Core\Storefront;

defined('ABSPATH') || exit;

final class ProductGridShortcodes
{
    public static function renderFeaturedProductsGrid(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            ['limit' => 4, 'columns' => 4],
            $attributes,
            'petshop_featured_products_grid'
        );

        return self::renderProductGridHtml(
            do_shortcode(
                sprintf(
                    '[products limit="%d" columns="%d" orderby="popularity"]',
                    max(1, absint($attributes['limit'])),
                    max(1, absint($attributes['columns']))
                )
            )
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderKitsSectionGrid(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            ['limit' => 4, 'columns' => 4, 'category' => 'conjuntos'],
            $attributes,
            'petshop_kits_section_grid'
        );

        $term = get_term_by('slug', (string) $attributes['category'], 'product_cat');
        if (!$term instanceof \WP_Term || (int) $term->count <= 0) {
            return '';
        }

        return self::renderProductGridHtml(
            do_shortcode(
                sprintf(
                    '[products limit="%d" columns="%d" category="%s" orderby="date" order="DESC"]',
                    max(1, absint($attributes['limit'])),
                    max(1, absint($attributes['columns'])),
                    esc_attr((string) $attributes['category'])
                )
            )
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderSeasonalProductsGrid(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            ['limit' => 4, 'columns' => 4],
            $attributes,
            'petshop_seasonal_products_grid'
        );

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'petshop_seasonal', 'value' => '1'],
                ['key' => 'petshop_visible_in_menu', 'value' => '1'],
            ],
        ]);
        if (is_wp_error($terms) || $terms === []) {
            return '';
        }

        $slugs = implode(',', wp_list_pluck($terms, 'slug'));

        return self::renderProductGridHtml(
            do_shortcode(
                sprintf(
                    '[products limit="%d" columns="%d" category="%s" orderby="date" order="DESC"]',
                    max(1, absint($attributes['limit'])),
                    max(1, absint($attributes['columns'])),
                    esc_attr($slugs)
                )
            )
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderProductShowcaseGrid(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            [
                'limit' => 4,
                'columns' => 4,
                'category' => '',
                'orderby' => 'date',
                'order' => 'DESC',
            ],
            $attributes,
            'petshop_product_showcase_grid'
        );

        $category = trim((string) $attributes['category']);
        $products = $category !== ''
            ? do_shortcode(sprintf(
                '[products limit="%d" columns="%d" category="%s" orderby="%s" order="%s"]',
                max(1, absint($attributes['limit'])),
                max(1, absint($attributes['columns'])),
                esc_attr($category),
                esc_attr((string) $attributes['orderby']),
                esc_attr((string) $attributes['order'])
            ))
            : do_shortcode(sprintf(
                '[products limit="%d" columns="%d" orderby="%s" order="%s"]',
                max(1, absint($attributes['limit'])),
                max(1, absint($attributes['columns'])),
                esc_attr((string) $attributes['orderby']),
                esc_attr((string) $attributes['order'])
            ));

        return self::renderProductGridHtml($products);
    }

    public static function isBlockEditorContext(): bool
    {
        if (is_admin()) {
            return true;
        }

        return defined('REST_REQUEST') && REST_REQUEST;
    }

    public static function hideEmptyHomeSections(string $content, array $block): string
    {
        if (($block['blockName'] ?? '') !== 'core/group' || self::isBlockEditorContext()) {
            return $content;
        }

        $className = (string) ($block['attrs']['className'] ?? '');
        if (str_contains($className, 'petshop-reviews-section')) {
            if (str_contains($content, 'petshop-review-card')) {
                return $content;
            }

            return '';
        }

        if (str_contains($className, 'petshop-product-showcase')) {
            if (preg_match('/class="[^"]*\bproduct\b[^"]*"/', $content)) {
                return $content;
            }

            return '';
        }

        return $content;
    }

    public static function renderProductGridHtml(string $productsHtml): string
    {
        if (!preg_match('/class="[^"]*\bproduct\b[^"]*"/', $productsHtml)) {
            return '';
        }

        return $productsHtml;
    }
}
