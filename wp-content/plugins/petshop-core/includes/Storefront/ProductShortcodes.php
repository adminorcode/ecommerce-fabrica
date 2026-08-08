<?php

declare(strict_types=1);

namespace Petshop\Core\Storefront;

defined('ABSPATH') || exit;

final class ProductShortcodes
{
    public static function renderSeasonalProducts(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            [
                'limit' => 4,
                'columns' => 4,
                'title' => __('Coleção da estação', 'petshop-core'),
                'cta' => __('Ver todos →', 'petshop-core'),
                'cta_url' => '',
            ],
            $attributes,
            'petshop_seasonal_products'
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
        $products = do_shortcode(sprintf(
            '[products limit="%d" columns="%d" category="%s" orderby="date" order="DESC"]',
            max(1, absint($attributes['limit'])),
            max(1, absint($attributes['columns'])),
            esc_attr($slugs)
        ));

        $ctaUrl = ProductShowcaseView::resolveSeasonalCtaUrl((string) $attributes['cta_url'], $terms);

        return ProductShowcaseView::wrapProductShowcase(
            'petshop-seasonal-section',
            'petshop-seasonal-heading',
            (string) $attributes['title'],
            (string) $attributes['cta'],
            $ctaUrl,
            '',
            $products
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderReviews(array $attributes = []): string
    {
        $attributes = shortcode_atts(['limit' => 3], $attributes, 'petshop_reviews');
        $reviews = get_comments([
            'status' => 'approve',
            'post_type' => 'product',
            'type' => 'review',
            'number' => max(1, absint($attributes['limit'])),
        ]);
        if ($reviews === []) {
            return '';
        }

        ob_start();
        echo '<ul class="petshop-review-grid" aria-label="' . esc_attr__('Avaliações de clientes', 'petshop-core') . '">';
        foreach ($reviews as $review) {
            $rating = (int) get_comment_meta($review->comment_ID, 'rating', true);
            echo '<li class="petshop-review-card">';
            if ($rating > 0) {
                echo wp_kses_post(wc_get_rating_html($rating));
            }
            echo '<blockquote><p>' . esc_html(wp_trim_words($review->comment_content, 35)) . '</p></blockquote>';
            echo '<cite>' . esc_html($review->comment_author) . '</cite>';
            echo '</li>';
        }
        echo '</ul>';

        return (string) ob_get_clean();
    }

    public static function filterProductRatingHtml(string $ratingHtml, float $rating, int $count): string
    {
        return $count > 0 ? $ratingHtml : '';
    }

    public static function hasCatalogSales(): bool
    {
        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'total_sales',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'NUMERIC',
                ],
            ],
            'no_found_rows' => true,
        ]);

        return $query->have_posts();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderFeaturedProducts(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            [
                'limit' => 4,
                'columns' => 4,
                'fallback_title' => '',
                'cta' => __('Ver todos →', 'petshop-core'),
                'cta_url' => '',
            ],
            $attributes,
            'petshop_featured_products'
        );

        $fallbackTitle = trim((string) $attributes['fallback_title']);
        if ($fallbackTitle === '') {
            $fallbackTitle = (string) get_theme_mod(
                'petshop_featured_section_title',
                \Petshop\Core\Settings\DefaultSettings::get('petshop_featured_section_title')
            );
        }

        $title = self::hasCatalogSales()
            ? __('Mais vendidos', 'petshop-core')
            : $fallbackTitle;

        $products = do_shortcode(
            sprintf(
                '[products limit="%d" columns="%d" orderby="popularity"]',
                max(1, absint($attributes['limit'])),
                max(1, absint($attributes['columns']))
            )
        );

        $ctaUrl = ProductShowcaseView::resolveShopCtaUrl((string) $attributes['cta_url']);

        return ProductShowcaseView::wrapProductShowcase(
            'petshop-featured-section',
            'petshop-featured-heading',
            $title,
            (string) $attributes['cta'],
            $ctaUrl,
            '',
            $products
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderKitsSection(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            [
                'limit' => 4,
                'columns' => 4,
                'title' => __('Economize comprando kits', 'petshop-core'),
                'intro' => __(
                    'Escolhas práticas para compor o visual e facilitar a rotina profissional.',
                    'petshop-core'
                ),
                'cta' => __('Ver todos →', 'petshop-core'),
                'category' => 'conjuntos',
            ],
            $attributes,
            'petshop_kits_section'
        );

        $term = get_term_by('slug', (string) $attributes['category'], 'product_cat');
        if (!$term instanceof \WP_Term || (int) $term->count <= 0) {
            return '';
        }

        $products = do_shortcode(
            sprintf(
                '[products limit="%d" columns="%d" category="%s" orderby="date" order="DESC"]',
                max(1, absint($attributes['limit'])),
                max(1, absint($attributes['columns'])),
                esc_attr((string) $attributes['category'])
            )
        );

        $ctaUrl = get_term_link($term);
        if (is_wp_error($ctaUrl)) {
            $ctaUrl = wc_get_page_permalink('shop');
        }

        return ProductShowcaseView::wrapProductShowcase(
            'petshop-kits-section petshop-section--soft',
            'petshop-kits-heading',
            (string) $attributes['title'],
            (string) $attributes['cta'],
            (string) $ctaUrl,
            (string) $attributes['intro'],
            $products
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderProductShowcase(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            [
                'limit' => 4,
                'columns' => 4,
                'title' => '',
                'intro' => '',
                'cta' => __('Ver todos →', 'petshop-core'),
                'cta_url' => '',
                'category' => '',
                'orderby' => 'date',
                'order' => 'DESC',
                'class' => 'petshop-professional-section',
            ],
            $attributes,
            'petshop_product_showcase'
        );

        $title = trim((string) $attributes['title']);
        if ($title === '') {
            return '';
        }

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

        $ctaUrl = ProductShowcaseView::resolveCategoryCtaUrl((string) $attributes['cta_url'], $category);

        return ProductShowcaseView::wrapProductShowcase(
            (string) $attributes['class'],
            sanitize_title($title) . '-heading',
            $title,
            (string) $attributes['cta'],
            $ctaUrl,
            (string) $attributes['intro'],
            $products
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
}
