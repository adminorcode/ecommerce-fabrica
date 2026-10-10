<?php

declare(strict_types=1);

namespace Petshop\Core\Storefront;

use Petshop\Core\CategoryIcons;

defined('ABSPATH') || exit;

final class CategoryGrid
{
    private const VERSION = '2.4.1';

    public static function renderCategoryGrid(array $attributes = []): string
    {
        $attributes = shortcode_atts(['limit' => 8], $attributes, 'petshop_categories');
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => 0,
            'meta_key' => 'petshop_menu_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms)) {
            return '';
        }

        $visibleTerms = array_values(
            array_filter(
                $terms,
                static fn (\WP_Term $term): bool => $term->slug !== 'promocoes'
                    && (bool) get_term_meta(
                        $term->term_id,
                        'petshop_visible_in_menu',
                        true
                    )
            )
        );
        $visibleTerms = array_slice($visibleTerms, 0, max(1, absint($attributes['limit'])));

        self::enqueueCategoryPreviewAssets();

        ob_start();
        echo '<ul class="petshop-category-grid" aria-label="' . esc_attr__('Categorias de produtos', 'petshop-core') . '">';
        foreach ($visibleTerms as $term) {
            $display = CategoryIcons::resolveDisplayForTerm($term);
            $termLink = get_term_link($term);
            if (is_wp_error($termLink)) {
                continue;
            }
            $previewId = 'petshop-category-preview-' . (int) $term->term_id;
            $products = self::categoryPreviewProducts($term);
            $hasPreview = $products !== [];

            echo '<li class="petshop-category-card"' . ($hasPreview ? ' data-petshop-category-preview' : '') . '>';
            echo '<a class="petshop-category-card__trigger" href="' . esc_url($termLink) . '"';
            if ($hasPreview) {
                echo ' aria-expanded="false" aria-controls="' . esc_attr($previewId) . '"';
            }
            echo '>';
            echo '<span class="petshop-category-card__well">';
            if ($display['source'] === 'attachment' && $display['url'] !== '') {
                echo '<span class="petshop-category-card__icon petshop-category-card__icon--media" aria-hidden="true">';
                echo '<img src="' . esc_url($display['url']) . '" alt="" width="40" height="40" loading="lazy" decoding="async">';
                echo '</span>';
            } else {
                echo '<span class="petshop-category-card__icon" style="--petshop-category-icon: url(\'' . esc_url($display['url']) . '\')" aria-hidden="true"></span>';
            }
            echo '</span>';
            echo '<span class="petshop-category-card__label">' . esc_html($term->name) . '</span>';
            echo '</a>';
            if ($hasPreview) {
                echo self::renderCategoryPreviewPanel($term, $products, $previewId, (string) $termLink);
            }
            echo '</li>';
        }
        echo '</ul>';

        return (string) ob_get_clean();
    }

    public static function enqueueCategoryPreviewAssets(): void
    {
        $assetPath = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/category-preview.js';
        wp_enqueue_script(
            'petshop-category-preview',
            plugins_url('assets/js/category-preview.js', PETSHOP_CORE_FILE),
            [],
            is_file($assetPath) ? (string) filemtime($assetPath) : self::VERSION,
            true
        );
    }

    /**
     * @return list<\WC_Product>
     */
    private static function categoryPreviewProducts(\WP_Term $term): array
    {
        if (!function_exists('wc_get_products')) {
            return [];
        }

        $candidates = wc_get_products([
            'status' => 'publish',
            'limit' => 12,
            'category' => [$term->slug],
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);

        if (!is_array($candidates) || $candidates === []) {
            return [];
        }

        $valid = array_values(array_filter(
            $candidates,
            static fn ($product): bool => $product instanceof \WC_Product
        ));
        $withImage = array_values(array_filter(
            $valid,
            static fn (\WC_Product $product): bool => $product->get_image_id() > 0
        ));

        return array_slice($withImage !== [] ? $withImage : $valid, 0, 3);
    }

    /**
     * @param list<\WC_Product> $products
     */
    private static function renderCategoryPreviewPanel(
        \WP_Term $term,
        array $products,
        string $previewId,
        string $termLink
    ): string {
        ob_start();
        echo '<div id="' . esc_attr($previewId) . '" class="petshop-category-preview" hidden aria-hidden="true" role="region" aria-label="'
            . esc_attr(sprintf(__('Prévia de %s', 'petshop-core'), $term->name))
            . '">';
        echo '<ul class="petshop-category-preview__products">';
        foreach ($products as $product) {
            $image = $product->get_image(
                'woocommerce_thumbnail',
                [
                    'class' => 'petshop-category-preview__image',
                    'alt' => $product->get_name(),
                    'loading' => 'lazy',
                    'decoding' => 'async',
                ]
            );
            echo '<li class="petshop-category-preview__item">';
            echo '<a class="petshop-category-preview__link" href="' . esc_url($product->get_permalink()) . '">';
            echo '<span class="petshop-category-preview__media">' . $image . '</span>';
            echo '<span class="petshop-category-preview__name">' . esc_html($product->get_name()) . '</span>';
            echo '<span class="petshop-category-preview__price">' . wp_kses_post($product->get_price_html()) . '</span>';
            echo '</a></li>';
        }
        echo '</ul>';
        echo '<a class="petshop-category-preview__cta" href="' . esc_url($termLink) . '">';
        echo esc_html(sprintf(__('Ver %s', 'petshop-core'), $term->name));
        echo '</a>';
        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $attributes
     */
}
