<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

final class ProductGridBlock
{
    private const MODES = ['manual', 'category', 'popular', 'seasonal'];
    private const ORDERBY = ['date', 'popularity', 'title', 'price', 'menu_order'];
    private const ORDER = ['ASC', 'DESC'];

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerBlock']);
        add_action('rest_api_init', [self::class, 'registerRestRoutes']);
    }

    public static function registerBlock(): void
    {
        $base = plugin_dir_path(PETSHOP_CORE_FILE) . 'blocks/build/';
        self::registerEditorScript('petshop-product-grid-editor', 'product-grid.js', $base);

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('petshop-product-grid-editor', 'petshop-core');
        }

        register_block_type(
            plugin_dir_path(PETSHOP_CORE_FILE) . 'blocks/product-grid',
            [
                'editor_script' => 'petshop-product-grid-editor',
                'render_callback' => [self::class, 'render'],
            ]
        );
    }

    private static function registerEditorScript(string $handle, string $file, string $base): void
    {
        $assetPath = $base . str_replace('.js', '.asset.php', $file);
        $asset = is_file($assetPath) ? require $assetPath : ['dependencies' => [], 'version' => '0.1.0'];

        wp_register_script(
            $handle,
            plugins_url('blocks/build/' . $file, PETSHOP_CORE_FILE),
            $asset['dependencies'],
            $asset['version'],
            true
        );
    }

    public static function registerRestRoutes(): void
    {
        register_rest_route('petshop/v1', '/product-grid/products', [
            'methods' => \WP_REST_Server::READABLE,
            'permission_callback' => [self::class, 'canEditProducts'],
            'callback' => [self::class, 'searchProducts'],
        ]);

        register_rest_route('petshop/v1', '/product-grid/categories', [
            'methods' => \WP_REST_Server::READABLE,
            'permission_callback' => [self::class, 'canEditProducts'],
            'callback' => [self::class, 'searchCategories'],
        ]);
    }

    public static function canEditProducts(): bool
    {
        return current_user_can('edit_products') || current_user_can('manage_woocommerce');
    }

    public static function searchProducts(\WP_REST_Request $request): \WP_REST_Response
    {
        $search = sanitize_text_field((string) $request->get_param('search'));
        $include = self::normalizeIds(self::requestIds($request->get_param('include')), 50);
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => $include !== [] ? 'post__in' : 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ];

        if ($include !== []) {
            $args['post__in'] = $include;
        } elseif ($search !== '') {
            $args['s'] = $search;
        }

        $query = new \WP_Query($args);
        $productIds = array_map('absint', $query->posts);

        if ($include === [] && $search !== '') {
            $skuMatches = get_posts([
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => 20,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_sku',
                        'value' => $search,
                        'compare' => 'LIKE',
                    ],
                ],
            ]);
            $productIds = array_values(array_unique(array_merge($productIds, array_map('absint', $skuMatches))));
        }

        $items = [];
        foreach (array_slice($productIds, 0, 20) as $productId) {
            $product = wc_get_product((int) $productId);
            if (!$product instanceof \WC_Product || !self::isVisibleProduct($product)) {
                continue;
            }

            $sku = $product->get_sku();
            $items[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $sku,
                'label' => $sku !== '' ? $product->get_name() . ' (' . $sku . ')' : $product->get_name(),
            ];
        }

        return rest_ensure_response($items);
    }

    public static function searchCategories(\WP_REST_Request $request): \WP_REST_Response
    {
        $search = sanitize_text_field((string) $request->get_param('search'));
        $include = self::normalizeIds(self::requestIds($request->get_param('include')), 100);
        $args = [
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'number' => 50,
            'orderby' => 'name',
            'order' => 'ASC',
        ];

        if ($include !== []) {
            $args['include'] = $include;
            $args['orderby'] = 'include';
        } elseif ($search !== '') {
            $args['search'] = $search;
        }

        $terms = get_terms($args);
        if (is_wp_error($terms)) {
            return rest_ensure_response([]);
        }

        $items = [];
        foreach ($terms as $term) {
            if (!$term instanceof \WP_Term) {
                continue;
            }
            $items[] = [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'parent' => (int) $term->parent,
                'label' => self::categoryLabel($term),
            ];
        }

        return rest_ensure_response($items);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function render(array $attributes, string $content, \WP_Block $block): string
    {
        unset($content, $block);

        $shortcodeAttributes = self::shortcodeAttributes($attributes);
        if ($shortcodeAttributes === null) {
            return '';
        }

        $productsHtml = do_shortcode(self::productsShortcode($shortcodeAttributes));

        return \Petshop\Core\Storefront\ProductGridShortcodes::renderProductGridHtml($productsHtml);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>|null
     */
    public static function shortcodeAttributes(array $attributes): ?array
    {
        $attributes = self::sanitizeAttributes($attributes);

        if ($attributes['selectionMode'] === 'manual') {
            $ids = self::validManualProductIds($attributes['productIds']);
            if ($ids === []) {
                return null;
            }

            return [
                'ids' => implode(',', $ids),
                'limit' => min($attributes['limit'], count($ids)),
                'columns' => $attributes['columns'],
                'orderby' => 'post__in',
            ];
        }

        if ($attributes['selectionMode'] === 'category') {
            $slugs = self::categorySlugsFromIds($attributes['categoryIds']);
            if ($slugs === []) {
                return null;
            }

            return [
                'category' => implode(',', $slugs),
                'limit' => $attributes['limit'],
                'columns' => $attributes['columns'],
                'orderby' => $attributes['orderby'],
                'order' => $attributes['order'],
            ];
        }

        if ($attributes['selectionMode'] === 'seasonal') {
            $slugs = self::seasonalCategorySlugs();
            if ($slugs === []) {
                return null;
            }

            return [
                'category' => implode(',', $slugs),
                'limit' => $attributes['limit'],
                'columns' => $attributes['columns'],
                'orderby' => $attributes['orderby'],
                'order' => $attributes['order'],
            ];
        }

        return [
            'limit' => $attributes['limit'],
            'columns' => $attributes['columns'],
            'orderby' => 'popularity',
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array{selectionMode: string, productIds: list<int>, categoryIds: list<int>, limit: int, columns: int, orderby: string, order: string}
     */
    public static function sanitizeAttributes(array $attributes): array
    {
        $mode = sanitize_key((string) ($attributes['selectionMode'] ?? 'popular'));
        if (!in_array($mode, self::MODES, true)) {
            $mode = 'popular';
        }

        $orderby = sanitize_key((string) ($attributes['orderby'] ?? 'date'));
        if (!in_array($orderby, self::ORDERBY, true)) {
            $orderby = 'date';
        }

        $order = strtoupper(sanitize_key((string) ($attributes['order'] ?? 'DESC')));
        if (!in_array($order, self::ORDER, true)) {
            $order = 'DESC';
        }

        return [
            'selectionMode' => $mode,
            'productIds' => self::normalizeIds((array) ($attributes['productIds'] ?? []), 20),
            'categoryIds' => self::normalizeIds((array) ($attributes['categoryIds'] ?? []), 12),
            'limit' => min(20, max(1, absint($attributes['limit'] ?? 4))),
            'columns' => min(6, max(2, absint($attributes['columns'] ?? 4))),
            'orderby' => $mode === 'popular' ? 'popularity' : $orderby,
            'order' => $order,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function blockMarkup(array $attributes): string
    {
        $json = wp_json_encode(self::sanitizeAttributes($attributes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return '<!-- wp:petshop/product-grid ' . $json . ' /-->';
    }

    public static function blockMarkupFromLegacyShortcode(string $shortcode): ?string
    {
        $parsed = self::parseShortcode(trim($shortcode));
        if ($parsed === null) {
            return null;
        }

        [$tag, $attributes] = $parsed;
        $limit = max(1, absint($attributes['limit'] ?? 4));
        $columns = max(2, absint($attributes['columns'] ?? 4));

        if ($tag === 'petshop_featured_products_grid') {
            return self::blockMarkup(['selectionMode' => 'popular', 'limit' => $limit, 'columns' => $columns]);
        }

        if ($tag === 'petshop_kits_section_grid') {
            return self::blockMarkup([
                'selectionMode' => 'category',
                'categoryIds' => self::termIdsFromSlugs((string) ($attributes['category'] ?? 'conjuntos')),
                'limit' => $limit,
                'columns' => $columns,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);
        }

        if ($tag === 'petshop_seasonal_products_grid') {
            return self::blockMarkup([
                'selectionMode' => 'seasonal',
                'limit' => $limit,
                'columns' => $columns,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);
        }

        if ($tag === 'petshop_product_showcase_grid') {
            return self::blockMarkup([
                'selectionMode' => 'category',
                'categoryIds' => self::termIdsFromSlugs((string) ($attributes['category'] ?? '')),
                'limit' => $limit,
                'columns' => $columns,
                'orderby' => (string) ($attributes['orderby'] ?? 'date'),
                'order' => (string) ($attributes['order'] ?? 'DESC'),
            ]);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function productsShortcode(array $attributes): string
    {
        $pairs = [];
        foreach ($attributes as $key => $value) {
            $pairs[] = sprintf('%s="%s"', sanitize_key((string) $key), esc_attr((string) $value));
        }

        return '[products ' . implode(' ', $pairs) . ']';
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private static function validManualProductIds(array $ids): array
    {
        $valid = [];
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (!$product instanceof \WC_Product || !self::isVisibleProduct($product)) {
                continue;
            }
            $valid[] = (int) $id;
        }

        return $valid;
    }

    private static function isVisibleProduct(\WC_Product $product): bool
    {
        return $product->get_status() === 'publish'
            && in_array($product->get_catalog_visibility(), ['visible', 'catalog'], true);
    }

    /**
     * @param list<int> $ids
     * @return list<string>
     */
    private static function categorySlugsFromIds(array $ids): array
    {
        $slugs = [];
        foreach ($ids as $id) {
            $term = get_term($id, 'product_cat');
            if (!$term instanceof \WP_Term || is_wp_error($term)) {
                continue;
            }
            $slugs[] = $term->slug;
        }

        return array_values(array_unique($slugs));
    }

    /**
     * @return list<string>
     */
    private static function seasonalCategorySlugs(): array
    {
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
            return [];
        }

        return array_values(array_filter(wp_list_pluck($terms, 'slug')));
    }

    /**
     * @param mixed $value
     * @return list<int>
     */
    private static function requestIds($value): array
    {
        if (is_string($value)) {
            return array_map('absint', array_filter(array_map('trim', explode(',', $value))));
        }

        return is_array($value) ? array_map('absint', $value) : [];
    }

    /**
     * @param array<mixed> $values
     * @return list<int>
     */
    private static function normalizeIds(array $values, int $limit): array
    {
        $ids = [];
        foreach ($values as $value) {
            $rawId = is_numeric($value) ? (int) $value : 0;
            if ($rawId <= 0) {
                continue;
            }
            $id = absint($rawId);
            if (in_array($id, $ids, true)) {
                continue;
            }
            $ids[] = $id;
            if (count($ids) >= $limit) {
                break;
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private static function termIdsFromSlugs(string $slugs): array
    {
        $ids = [];
        foreach (array_filter(array_map('trim', explode(',', $slugs))) as $slug) {
            $term = get_term_by('slug', sanitize_title($slug), 'product_cat');
            if (!$term instanceof \WP_Term) {
                continue;
            }
            $ids[] = (int) $term->term_id;
        }

        return array_values(array_unique($ids));
    }

    private static function categoryLabel(\WP_Term $term): string
    {
        if ($term->parent <= 0) {
            return $term->name;
        }

        $parent = get_term($term->parent, 'product_cat');
        if (!$parent instanceof \WP_Term || is_wp_error($parent)) {
            return $term->name;
        }

        return $parent->name . ' / ' . $term->name;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private static function parseShortcode(string $shortcode): ?array
    {
        if (!preg_match('/^\[(petshop_featured_products_grid|petshop_kits_section_grid|petshop_seasonal_products_grid|petshop_product_showcase_grid)\b([^\]]*)\]$/', $shortcode, $matches)) {
            return null;
        }

        $attributes = shortcode_parse_atts(trim($matches[2])) ?: [];

        return [$matches[1], $attributes];
    }
}
