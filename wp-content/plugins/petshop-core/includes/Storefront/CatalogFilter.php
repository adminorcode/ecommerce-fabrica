<?php

declare(strict_types=1);

namespace Petshop\Core\Storefront;

defined('ABSPATH') || exit;

final class CatalogFilter
{
    private const VERSION = '3.0.0';
    private const ATTRIBUTE_FILTERS = [
        'filter_pa_color' => ['taxonomy' => 'pa_color', 'label' => 'Cor'],
        'filter_pa_size' => ['taxonomy' => 'pa_size', 'label' => 'Tamanho'],
    ];
    private const STOCK_STATUSES = ['instock', 'onbackorder', 'outofstock'];

    public static function renderCatalogFilter(): void
    {
        if (!is_shop() && !is_product_taxonomy()) {
            return;
        }

        $categories = self::catalogTerms();
        $attributes = self::attributeTerms();
        if ($categories === [] && $attributes === []) {
            return;
        }

        $selectedCategories = self::selectedCatalogCategorySlugs();
        if ($selectedCategories === [] && is_product_category()) {
            $currentTerm = get_queried_object();
            if ($currentTerm instanceof \WP_Term) {
                $selectedCategories = [$currentTerm->slug];
            }
        }
        $applied = self::appliedFilters();
        $stock = self::selectedStockStatus();

        echo '<div class="petshop-catalog-filter-backdrop" data-petshop-filter-backdrop hidden></div>';
        echo '<aside id="petshop-catalog-filter-panel" class="petshop-catalog-sidebar" role="dialog" aria-modal="false" aria-labelledby="petshop-catalog-filter-title" tabindex="-1">';
        echo '<form class="petshop-catalog-filter" action="' . esc_url(wc_get_page_permalink('shop')) . '" method="get">';
        $productSearch = self::productSearchTermFromRequest($_GET);
        if ($productSearch !== '') {
            echo '<input type="hidden" name="s" value="' . esc_attr($productSearch) . '">';
            echo '<input type="hidden" name="post_type" value="product">';
        }
        echo '<div class="petshop-catalog-filter__header"><h2 id="petshop-catalog-filter-title" class="petshop-catalog-filter__title">' . esc_html__('Filtros', 'petshop-core') . '</h2>';
        echo '<button type="button" class="petshop-catalog-filter__close" data-petshop-filter-close aria-label="' . esc_attr__('Fechar filtros', 'petshop-core') . '"><span aria-hidden="true">&times;</span></button></div>';
        echo '<div class="petshop-catalog-filter__body">';

        if ($applied !== []) {
            self::renderAppliedFilters($applied, 'panel');
        }

        if ($categories !== []) {
            self::openFacet('categories', __('Categorias', 'petshop-core'), true, $selectedCategories !== []);
            echo '<div class="petshop-catalog-filter__search"><label for="petshop-category-search">' . esc_html__('Filtrar categorias', 'petshop-core') . '</label>';
            echo '<input id="petshop-category-search" type="search" placeholder="' . esc_attr__('Digite uma categoria', 'petshop-core') . '" autocomplete="off" aria-controls="petshop-category-options"></div>';
            echo '<p class="screen-reader-text" data-petshop-category-status aria-live="polite"></p><ul id="petshop-category-options">';
            foreach ($categories as $term) {
                self::renderTermCheckbox('petshop_categories[]', $term, in_array($term->slug, $selectedCategories, true), 'category');
            }
            echo '</ul><button type="button" class="petshop-catalog-filter__more" data-petshop-filter-more data-more-label="' . esc_attr__('Ver mais', 'petshop-core') . '" data-less-label="' . esc_attr__('Ver menos', 'petshop-core') . '">' . esc_html__('Ver mais', 'petshop-core') . '</button>';
            self::closeFacet();
        }

        self::openFacet('price', __('Preço', 'petshop-core'), false, self::scalarRequestValue('min_price') !== '' || self::scalarRequestValue('max_price') !== '');
        echo '<div class="petshop-catalog-filter__price">';
        echo '<label for="petshop-min-price">' . esc_html__('Mínimo', 'petshop-core') . '<input id="petshop-min-price" name="min_price" type="number" min="0" step="0.01" inputmode="decimal" value="' . esc_attr(self::scalarRequestValue('min_price')) . '"></label>';
        echo '<label for="petshop-max-price">' . esc_html__('Máximo', 'petshop-core') . '<input id="petshop-max-price" name="max_price" type="number" min="0" step="0.01" inputmode="decimal" value="' . esc_attr(self::scalarRequestValue('max_price')) . '"></label>';
        echo '</div>';
        self::closeFacet();

        foreach ($attributes as $key => $definition) {
            $selected = self::selectedSlugs($key);
            self::openFacet($definition['taxonomy'], $definition['label'], false, $selected !== []);
            echo '<ul>';
            foreach ($definition['terms'] as $term) {
                self::renderTermCheckbox($key, $term, in_array($term->slug, $selected, true), $definition['taxonomy']);
            }
            echo '</ul>';
            self::closeFacet();
        }

        self::openFacet('stock', __('Disponibilidade', 'petshop-core'), false, $stock !== '');
        echo '<label class="petshop-catalog-filter__select" for="petshop-stock-status">' . esc_html__('Estoque', 'petshop-core');
        echo '<select id="petshop-stock-status" name="stock_status"><option value="">' . esc_html__('Todos', 'petshop-core') . '</option>';
        $stockLabels = ['instock' => __('Em estoque', 'petshop-core'), 'onbackorder' => __('Sob encomenda', 'petshop-core'), 'outofstock' => __('Fora de estoque', 'petshop-core')];
        foreach ($stockLabels as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($stock, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        self::closeFacet();
        echo '</div>';

        if (isset($_GET['orderby']) && self::scalarRequestValue('orderby') !== '') {
            echo '<input type="hidden" name="orderby" value="' . esc_attr(sanitize_key(self::scalarRequestValue('orderby'))) . '">';
        }
        echo '<div class="petshop-catalog-filter__actions"><button class="petshop-button petshop-catalog-filter__apply" type="submit">' . esc_html__('Aplicar filtros', 'petshop-core') . '</button>';
        echo '<a href="' . esc_url(wc_get_page_permalink('shop')) . '">' . esc_html__('Limpar', 'petshop-core') . '</a></div>';
        echo '</form></aside><div class="petshop-catalog-toolbar"><div class="petshop-catalog-toolbar__filters">';
        echo '<button class="petshop-catalog-filter-toggle" type="button" data-petshop-filter-open aria-controls="petshop-catalog-filter-panel" aria-expanded="false">';
        echo esc_html__('Filtros', 'petshop-core');
        if ($applied !== []) {
            echo '<span aria-label="' . esc_attr(sprintf(_n('%d filtro aplicado', '%d filtros aplicados', count($applied), 'petshop-core'), count($applied))) . '">' . esc_html((string) count($applied)) . '</span>';
        }
        echo '</button>';
        if ($applied !== []) {
            self::renderAppliedFilters($applied, 'toolbar');
        }
        echo '</div>';
    }

    public static function enqueueCatalogFilterAssets(): void
    {
        if (!is_shop() && !is_product_taxonomy()) {
            return;
        }
        $assetPath = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/catalog-filter.js';
        wp_enqueue_script('petshop-catalog-filter', plugins_url('assets/js/catalog-filter.js', PETSHOP_CORE_FILE), [], is_file($assetPath) ? (string) filemtime($assetPath) : self::VERSION, true);
    }

    public static function applyCatalogCategoryFilter(\WP_Query $query): void
    {
        $isProductTaxonomy = method_exists($query, 'is_tax') && $query->is_tax(['product_cat', 'product_tag']);
        if (is_admin() || !$query->is_main_query() || (!$query->is_post_type_archive('product') && !$isProductTaxonomy)) {
            return;
        }

        $taxQuery = (array) $query->get('tax_query');
        $categories = self::selectedCatalogCategorySlugs();
        if ($categories !== []) {
            $taxQuery = self::withoutCatalogCategoryClauses($taxQuery);
            $query->set('product_cat', '');
            $taxQuery['relation'] = 'AND';
            $taxQuery[] = ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $categories, 'operator' => 'IN', 'include_children' => true];
        }
        $taxQuery['relation'] = $taxQuery['relation'] ?? 'AND';
        foreach (self::ATTRIBUTE_FILTERS as $key => $definition) {
            $terms = self::selectedSlugs($key);
            if ($terms !== [] && taxonomy_exists($definition['taxonomy'])) {
                $taxQuery[] = ['taxonomy' => $definition['taxonomy'], 'field' => 'slug', 'terms' => $terms, 'operator' => 'IN'];
            }
        }
        if (count($taxQuery) > 1) {
            $query->set('tax_query', $taxQuery);
        }

        $stock = self::selectedStockStatus();
        if ($stock !== '') {
            $metaQuery = (array) $query->get('meta_query');
            $metaQuery[] = ['key' => '_stock_status', 'value' => $stock, 'compare' => '='];
            $query->set('meta_query', $metaQuery);
        }
    }

    public static function canonicalizeCatalogCategoryFilter(): void
    {
        if (!is_shop() && !is_product_taxonomy()) {
            return;
        }
        $hasLegacy = isset($_GET['product_cat']);
        $hasFiltersOnTaxonomy = is_product_taxonomy() && !self::requestTargetsShop() && self::knownRequestParametersPresent();
        if (!$hasLegacy && !$hasFiltersOnTaxonomy) {
            return;
        }
        $parameters = self::canonicalParametersFromRequest($_GET);
        if (!isset($parameters['petshop_categories']) && is_product_category()) {
            $currentTerm = get_queried_object();
            if ($currentTerm instanceof \WP_Term) {
                $parameters['petshop_categories'] = [$currentTerm->slug];
            }
        }
        $url = add_query_arg($parameters, wc_get_page_permalink('shop'));
        wp_safe_redirect($url, 302, 'Petshop canonical catalog filters');
        exit;
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    public static function canonicalParametersFromRequest(array $source): array
    {
        $parameters = [];
        $productSearch = self::productSearchTermFromRequest($source);
        if ($productSearch !== '') {
            $parameters['s'] = $productSearch;
            $parameters['post_type'] = 'product';
        }
        $categorySource = $source['product_cat'] ?? $source['petshop_categories'] ?? [];
        $categories = self::sanitizeSlugValues($categorySource);
        if ($categories !== []) {
            $parameters['petshop_categories'] = $categories;
        }
        foreach (['min_price', 'max_price'] as $key) {
            $value = isset($source[$key]) && is_scalar($source[$key]) ? wc_format_decimal(wp_unslash((string) $source[$key])) : '';
            if ($value !== '' && (float) $value >= 0) {
                $parameters[$key] = $value;
            }
        }
        foreach (array_keys(self::ATTRIBUTE_FILTERS) as $key) {
            $values = self::sanitizeSlugValues($source[$key] ?? []);
            if ($values !== []) {
                $parameters[$key] = implode(',', $values);
            }
        }
        $stock = isset($source['stock_status']) && is_scalar($source['stock_status']) ? sanitize_key(wp_unslash((string) $source['stock_status'])) : '';
        if (in_array($stock, self::STOCK_STATUSES, true)) {
            $parameters['stock_status'] = $stock;
        }
        if (isset($source['orderby']) && is_scalar($source['orderby'])) {
            $orderby = sanitize_key(wp_unslash((string) $source['orderby']));
            if (function_exists('wc_get_catalog_ordering_options') && array_key_exists($orderby, wc_get_catalog_ordering_options())) {
                $parameters['orderby'] = $orderby;
            }
        }

        return $parameters;
    }

    /** @return list<string> */
    public static function selectedCatalogCategorySlugs(): array
    {
        return self::sanitizeSlugValues($_GET['product_cat'] ?? $_GET['petshop_categories'] ?? []);
    }

    public static function closeCatalogToolbar(): void
    {
        if ((is_shop() || is_product_taxonomy()) && (self::catalogTerms() !== [] || self::attributeTerms() !== [])) {
            echo '</div>';
        }
    }

    public static function resolveExactSkuSearch(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || !$query->is_search() || !class_exists('WooCommerce')) return;
        $postType = $query->get('post_type');
        if ($postType !== 'product' && $postType !== ['product']) return;
        $search = trim((string) $query->get('s'));
        if ($search === '') return;
        $productId = wc_get_product_id_by_sku($search);
        if ($productId <= 0) return;
        $product = wc_get_product($productId);
        if ($product && $product->is_type('variation')) $productId = $product->get_parent_id();
        $query->set('_petshop_exact_sku_product_id', $productId);
        $query->set('posts_per_page', 1);
    }

    public static function filterExactSkuSearch(string $searchSql, \WP_Query $query): string
    {
        $productId = (int) $query->get('_petshop_exact_sku_product_id');
        if ($productId <= 0) return $searchSql;
        global $wpdb;
        return $wpdb->prepare(" AND {$wpdb->posts}.ID = %d", $productId);
    }

    public static function allowSingleSearchResultRedirect(bool $redirect): bool
    {
        if (!$redirect) {
            return false;
        }

        global $wp_query;

        return $wp_query instanceof \WP_Query && (int) $wp_query->get('_petshop_exact_sku_product_id') > 0;
    }

    /** @return list<\WP_Term> */
    private static function catalogTerms(): array
    {
        $selected = self::selectedCatalogCategorySlugs();
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'meta_key' => 'petshop_menu_order', 'orderby' => 'meta_value_num', 'order' => 'ASC']);
        if (is_wp_error($terms) || !is_array($terms)) return [];
        return array_values(array_filter($terms, static fn ($term): bool => $term instanceof \WP_Term && ($term->count > 0 || in_array($term->slug, $selected, true)) && (bool) get_term_meta($term->term_id, 'petshop_visible_in_menu', true)));
    }

    /** @return array<string, array{taxonomy: string, label: string, terms: list<\WP_Term>}> */
    private static function attributeTerms(): array
    {
        $result = [];
        foreach (self::ATTRIBUTE_FILTERS as $key => $definition) {
            if (!taxonomy_exists($definition['taxonomy'])) continue;
            $selected = self::selectedSlugs($key);
            $terms = get_terms(['taxonomy' => $definition['taxonomy'], 'hide_empty' => false, 'orderby' => 'name']);
            if (!is_array($terms) || is_wp_error($terms)) continue;
            $terms = array_values(array_filter($terms, static fn ($term): bool => $term instanceof \WP_Term && ($term->count > 0 || in_array($term->slug, $selected, true))));
            if ($terms !== []) $result[$key] = ['taxonomy' => $definition['taxonomy'], 'label' => __($definition['label'], 'petshop-core'), 'terms' => $terms];
        }
        return $result;
    }

    private static function renderTermCheckbox(string $name, \WP_Term $term, bool $checked, string $prefix): void
    {
        $inputId = 'petshop-' . sanitize_html_class($prefix . '-' . $term->term_id);
        echo '<li data-petshop-filter-option><label for="' . esc_attr($inputId) . '"><input id="' . esc_attr($inputId) . '" type="checkbox" name="' . esc_attr($name) . '" value="' . esc_attr($term->slug) . '"' . checked($checked, true, false) . '>';
        echo '<span class="petshop-catalog-filter__name">' . esc_html($term->name) . '</span><span class="petshop-catalog-filter__count" aria-label="' . esc_attr(sprintf(_n('%d produto', '%d produtos', $term->count, 'petshop-core'), $term->count)) . '">' . esc_html((string) $term->count) . '</span></label></li>';
    }

    /** @param array<int|string, mixed> $taxQuery @return array<int|string, mixed> */
    private static function withoutCatalogCategoryClauses(array $taxQuery): array
    {
        $filtered = [];
        foreach ($taxQuery as $key => $clause) {
            if ($key === 'relation') {
                continue;
            }
            if (!is_array($clause) || ($clause['taxonomy'] ?? '') === 'product_cat') {
                continue;
            }
            $filtered[] = $clause;
        }

        return $filtered;
    }

    private static function openFacet(string $slug, string $label, bool $defaultOpen, bool $active): void
    {
        $facetId = 'petshop-filter-facet-' . sanitize_html_class($slug);
        $isOpen = $defaultOpen || $active;
        echo '<fieldset class="petshop-catalog-filter__facet' . ($active ? ' is-active' : '') . '" data-petshop-filter-facet>';
        echo '<legend><button class="petshop-catalog-filter__facet-toggle" type="button" aria-expanded="' . ($isOpen ? 'true' : 'false') . '" aria-controls="' . esc_attr($facetId) . '">';
        echo '<span>' . esc_html($label) . '</span><span class="petshop-catalog-filter__chevron" aria-hidden="true"></span></button></legend>';
        echo '<div id="' . esc_attr($facetId) . '" class="petshop-catalog-filter__facet-panel"' . ($isOpen ? '' : ' hidden') . '>';
    }

    private static function closeFacet(): void
    {
        echo '</div></fieldset>';
    }

    /** @param list<array{label: string, remove_url: string}> $applied */
    private static function renderAppliedFilters(array $applied, string $context): void
    {
        echo '<div class="petshop-catalog-filter__applied petshop-catalog-filter__applied--' . esc_attr($context) . '" aria-label="' . esc_attr__('Filtros aplicados', 'petshop-core') . '">';
        if ($context === 'panel') {
            echo '<h3>' . esc_html__('Aplicados', 'petshop-core') . '</h3>';
        }
        echo '<ul>';
        foreach ($applied as $filter) {
            echo '<li><a href="' . esc_url($filter['remove_url']) . '"><span>' . esc_html($filter['label']) . '</span><span aria-hidden="true">&times;</span></a></li>';
        }
        echo '</ul>';
        if ($context === 'panel') {
            echo '<a class="petshop-catalog-filter__clear" href="' . esc_url(wc_get_page_permalink('shop')) . '">' . esc_html__('Limpar todos', 'petshop-core') . '</a>';
        }
        echo '</div>';
    }

    /** @return list<string> */
    private static function selectedSlugs(string $key): array
    {
        return self::sanitizeSlugValues($_GET[$key] ?? []);
    }

    /** @return list<string> */
    private static function sanitizeSlugValues(mixed $raw): array
    {
        $values = is_array($raw) ? $raw : [$raw];
        $slugs = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) continue;
            foreach (explode(',', wp_unslash((string) $value)) as $candidate) {
                $slug = sanitize_title($candidate);
                if ($slug !== '') $slugs[] = $slug;
            }
        }
        return array_values(array_unique($slugs));
    }

    private static function selectedStockStatus(): string
    {
        $stock = sanitize_key(self::scalarRequestValue('stock_status'));
        return in_array($stock, self::STOCK_STATUSES, true) ? $stock : '';
    }

    private static function scalarRequestValue(string $key): string
    {
        return isset($_GET[$key]) && is_scalar($_GET[$key]) ? trim(wp_unslash((string) $_GET[$key])) : '';
    }

    /** @return list<array{label: string, remove_url: string}> */
    private static function appliedFilters(): array
    {
        $applied = [];
        $current = self::canonicalParametersFromRequest($_GET);
        if (!isset($current['petshop_categories']) && is_product_category()) {
            $currentTerm = get_queried_object();
            if ($currentTerm instanceof \WP_Term) {
                $current['petshop_categories'] = [$currentTerm->slug];
            }
        }
        foreach ($current as $key => $value) {
            if ($key === 'post_type') {
                continue;
            }
            $values = is_array($value) ? $value : explode(',', (string) $value);
            foreach ($values as $item) {
                $args = $current;
                if ($key === 's') {
                    unset($args['s'], $args['post_type']);
                } elseif (count($values) > 1) {
                    $remaining = array_values(array_diff($values, [$item]));
                    $args[$key] = is_array($value) ? $remaining : implode(',', $remaining);
                } else {
                    unset($args[$key]);
                }
                $applied[] = ['label' => self::filterLabel($key, (string) $item), 'remove_url' => add_query_arg($args, wc_get_page_permalink('shop'))];
            }
        }
        return $applied;
    }

    private static function filterLabel(string $key, string $value): string
    {
        if ($key === 's') {
            return __('Busca', 'petshop-core') . ': ' . $value;
        }
        $prefixes = ['product_cat' => __('Categoria', 'petshop-core'), 'petshop_categories' => __('Categoria', 'petshop-core'), 'filter_pa_color' => __('Cor', 'petshop-core'), 'filter_pa_size' => __('Tamanho', 'petshop-core'), 'min_price' => __('Preço mínimo', 'petshop-core'), 'max_price' => __('Preço máximo', 'petshop-core'), 'stock_status' => __('Estoque', 'petshop-core'), 'orderby' => __('Ordem', 'petshop-core')];
        $termTaxonomy = in_array($key, ['product_cat', 'petshop_categories'], true) ? 'product_cat' : (self::ATTRIBUTE_FILTERS[$key]['taxonomy'] ?? '');
        if ($termTaxonomy !== '') {
            $term = get_term_by('slug', $value, $termTaxonomy);
            if ($term instanceof \WP_Term) $value = $term->name;
        }
        return ($prefixes[$key] ?? $key) . ': ' . $value;
    }

    private static function knownRequestParametersPresent(): bool
    {
        foreach (['s', 'post_type', 'product_cat', 'petshop_categories', 'min_price', 'max_price', 'filter_pa_color', 'filter_pa_size', 'stock_status', 'orderby'] as $key) {
            if (isset($_GET[$key])) return true;
        }
        return false;
    }

    /** @param array<string, mixed> $source */
    private static function productSearchTermFromRequest(array $source): string
    {
        $postType = $source['post_type'] ?? '';
        if (is_array($postType)) {
            $postType = reset($postType);
        }
        $postType = is_scalar($postType) ? sanitize_key(wp_unslash((string) $postType)) : '';
        if ($postType !== 'product') {
            return '';
        }

        $search = $source['s'] ?? '';
        if (is_array($search)) {
            $search = reset($search);
        }
        if (!is_scalar($search)) {
            return '';
        }

        return sanitize_text_field(wp_unslash((string) $search));
    }

    private static function requestTargetsShop(): bool
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return is_shop();
        }
        $requestPath = untrailingslashit((string) wp_parse_url(wp_unslash((string) $_SERVER['REQUEST_URI']), PHP_URL_PATH));
        $shopPath = untrailingslashit((string) wp_parse_url(wc_get_page_permalink('shop'), PHP_URL_PATH));

        return $requestPath === $shopPath;
    }
}
