<?php

declare(strict_types=1);

namespace Petshop\Core\Storefront;

defined('ABSPATH') || exit;

final class CatalogFilter
{
    private const VERSION = '2.4.1';

    public static function renderCatalogFilter(): void
    {
        if (!is_shop() && !is_product_taxonomy()) {
            return;
        }

        $terms = self::catalogTerms();
        if ($terms === []) {
            return;
        }

        $selectedSlugs = self::selectedCatalogCategorySlugs();
        if ($selectedSlugs === [] && is_product_category()) {
            $currentTerm = get_queried_object();
            if ($currentTerm instanceof \WP_Term) {
                $selectedSlugs = [$currentTerm->slug];
            }
        }
        echo '<aside class="petshop-catalog-sidebar" aria-labelledby="petshop-catalog-filter-title">';
        echo '<form class="petshop-catalog-filter" action="' . esc_url(wc_get_page_permalink('shop')) . '" method="get">';
        echo '<h2 id="petshop-catalog-filter-title" class="petshop-catalog-filter__title">' . esc_html__('Categorias', 'petshop-core') . '</h2>';
        echo '<div class="petshop-catalog-filter__search">';
        echo '<label for="petshop-category-search">' . esc_html__('Filtrar categorias', 'petshop-core') . '</label>';
        echo '<input id="petshop-category-search" type="search" placeholder="' . esc_attr__('Digite uma categoria', 'petshop-core') . '" autocomplete="off" aria-controls="petshop-category-options">';
        echo '</div>';
        echo '<p class="screen-reader-text" data-petshop-category-status aria-live="polite"></p>';
        echo '<ul id="petshop-category-options">';
        foreach ($terms as $term) {
            $inputId = 'petshop-category-' . (int) $term->term_id;
            echo '<li>';
            echo '<label for="' . esc_attr($inputId) . '">';
            echo '<input id="' . esc_attr($inputId) . '" type="checkbox" name="petshop_categories[]" value="' . esc_attr($term->slug) . '"' . checked(in_array($term->slug, $selectedSlugs, true), true, false) . '>';
            echo '<span class="petshop-catalog-filter__name">' . esc_html($term->name) . '</span>';
            echo '<span class="petshop-catalog-filter__count" aria-label="' . esc_attr(sprintf(_n('%d produto', '%d produtos', $term->count, 'petshop-core'), $term->count)) . '">' . esc_html((string) $term->count) . '</span>';
            echo '</label>';
            echo '</li>';
        }
        echo '</ul>';
        echo '<button class="petshop-button petshop-catalog-filter__apply" type="submit">' . esc_html__('Aplicar filtros', 'petshop-core') . '</button>';
        echo '</form>';
        echo '</aside>';
        echo '<div class="petshop-catalog-toolbar">';
    }

    public static function enqueueCatalogFilterAssets(): void
    {
        if (!is_shop() && !is_product_taxonomy()) {
            return;
        }

        $assetPath = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/catalog-filter.js';
        wp_enqueue_script(
            'petshop-catalog-filter',
            plugins_url('assets/js/catalog-filter.js', PETSHOP_CORE_FILE),
            [],
            is_file($assetPath) ? (string) filemtime($assetPath) : self::VERSION,
            true
        );
    }

    public static function applyCatalogCategoryFilter(\WP_Query $query): void
    {
        $isProductTaxonomy = method_exists($query, 'is_tax')
            && $query->is_tax(['product_cat', 'product_tag']);
        if (is_admin() || !$query->is_main_query() || (!$query->is_post_type_archive('product') && !$isProductTaxonomy)) {
            return;
        }

        $selectedSlugs = self::selectedCatalogCategorySlugs();
        if ($selectedSlugs === []) {
            return;
        }

        $taxQuery = (array) $query->get('tax_query');
        $taxQuery['relation'] = 'AND';
        $taxQuery[] = [
            'taxonomy' => 'product_cat',
            'field' => 'slug',
            'terms' => $selectedSlugs,
            'operator' => 'IN',
            'include_children' => true,
        ];
        $query->set('tax_query', $taxQuery);
    }

    public static function canonicalizeCatalogCategoryFilter(): void
    {
        if (!is_product_taxonomy()) {
            return;
        }

        $selectedSlugs = self::selectedCatalogCategorySlugs();
        if ($selectedSlugs === []) {
            return;
        }

        $url = add_query_arg(
            'petshop_categories',
            implode(',', $selectedSlugs),
            wc_get_page_permalink('shop')
        );
        if (isset($_GET['orderby']) && is_scalar($_GET['orderby'])) {
            $url = add_query_arg('orderby', sanitize_key(wp_unslash((string) $_GET['orderby'])), $url);
        }
        wp_safe_redirect($url, 302, 'Petshop catalog filter');
        exit;
    }

    /** @return list<string> */
    public static function selectedCatalogCategorySlugs(): array
    {
        if (!isset($_GET['petshop_categories'])) {
            return [];
        }

        $raw = wp_unslash($_GET['petshop_categories']);
        $values = is_array($raw) ? $raw : [$raw];
        $slugs = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            foreach (explode(',', (string) $value) as $candidate) {
                $slug = sanitize_title($candidate);
                if ($slug !== '') {
                    $slugs[] = $slug;
                }
            }
        }

        return array_values(array_unique($slugs));
    }

    public static function closeCatalogToolbar(): void
    {
        if (!is_shop() && !is_product_taxonomy()) {
            return;
        }

        if (self::catalogTerms() !== []) {
            echo '</div>';
        }
    }

    /** @return list<\WP_Term> */
    private static function catalogTerms(): array
    {
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'meta_key' => 'petshop_menu_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
        ]);
        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        return array_values(array_filter(
            $terms,
            static fn ($term): bool => $term instanceof \WP_Term
                && (bool) get_term_meta($term->term_id, 'petshop_visible_in_menu', true)
        ));
    }

    public static function resolveExactSkuSearch(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || !$query->is_search() || !class_exists('WooCommerce')) {
            return;
        }
        $postType = $query->get('post_type');
        if ($postType !== 'product' && $postType !== ['product']) {
            return;
        }
        $search = trim((string) $query->get('s'));
        if ($search === '') {
            return;
        }
        $productId = wc_get_product_id_by_sku($search);
        if ($productId <= 0) {
            return;
        }

        $product = wc_get_product($productId);
        if ($product && $product->is_type('variation')) {
            $productId = $product->get_parent_id();
        }

        $query->set('_petshop_exact_sku_product_id', $productId);
        $query->set('posts_per_page', 1);
    }

    public static function filterExactSkuSearch(string $searchSql, \WP_Query $query): string
    {
        $productId = (int) $query->get('_petshop_exact_sku_product_id');
        if ($productId <= 0) {
            return $searchSql;
        }

        global $wpdb;

        return $wpdb->prepare(" AND {$wpdb->posts}.ID = %d", $productId);
    }
}
