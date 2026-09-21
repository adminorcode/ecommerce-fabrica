<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Blocks;

use Petshop\Core\Personalization\Infrastructure\ProductSettings;
use Petshop\Core\Storefront\ProductGridShortcodes;

defined('ABSPATH') || exit;

/**
 * Dynamic showcase listing published, purchasable products with personalization
 * enabled. No commercial copy lives in PHP: heading and intro stay in Gutenberg.
 */
final class PersonalizableProductsBlock
{
    public const NAME = 'petshop/personalizable-products';
    public const EDITOR_HANDLE = 'petshop-personalizable-products-editor';

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'register']);
    }

    public static function register(): void
    {
        $directory = plugin_dir_path(PETSHOP_CORE_FILE) . 'blocks/personalizable-products';
        if (!is_file($directory . '/block.json')) {
            return;
        }

        $editorRelative = 'blocks/personalizable-products/editor.js';
        $editorPath = plugin_dir_path(PETSHOP_CORE_FILE) . $editorRelative;
        wp_register_script(
            self::EDITOR_HANDLE,
            plugins_url($editorRelative, PETSHOP_CORE_FILE),
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render'],
            is_file($editorPath) ? (string) filemtime($editorPath) : '1.0.0',
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::EDITOR_HANDLE, 'petshop-core');
        }

        register_block_type($directory, [
            'editor_script' => self::EDITOR_HANDLE,
            'render_callback' => [self::class, 'render'],
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function render(array $attributes = []): string
    {
        $limit = min(24, max(1, absint($attributes['limit'] ?? 8)));
        $columns = min(6, max(2, absint($attributes['columns'] ?? 4)));

        $productIds = self::resolveProductIds($attributes, $limit);
        if ($productIds === []) {
            return '';
        }

        $shortcode = sprintf(
            '[products ids="%s" limit="%d" columns="%d" orderby="post__in"]',
            esc_attr(implode(',', $productIds)),
            count($productIds),
            $columns
        );

        $html = ProductGridShortcodes::renderProductGridHtml(do_shortcode($shortcode));
        if ($html === '') {
            return '';
        }

        return '<div class="petshop-personalizable-products">' . $html . '</div>';
    }

    /**
     * @param array<string, mixed> $attributes
     * @return list<int>
     */
    public static function resolveProductIds(array $attributes, int $limit): array
    {
        $explicit = self::parseIdList((string) ($attributes['productIds'] ?? ''));
        if ($explicit !== []) {
            return self::filterEnabledPurchasable($explicit, $limit);
        }

        $categories = self::parseSlugList((string) ($attributes['categorySlugs'] ?? ''));
        if ($categories !== []) {
            return self::queryEnabled($limit, $categories);
        }

        return self::queryEnabled($limit, []);
    }

    /**
     * @return list<int>
     */
    public static function enabledProductIds(int $limit): array
    {
        return self::queryEnabled($limit, []);
    }

    /**
     * @param list<string> $categorySlugs
     * @return list<int>
     */
    private static function queryEnabled(int $limit, array $categorySlugs): array
    {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit * 3,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => ProductSettings::META_ENABLED,
                    'value' => 'yes',
                ],
            ],
        ];

        if ($categorySlugs !== []) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $categorySlugs,
                ],
            ];
        }

        $candidates = get_posts($args);

        return self::filterEnabledPurchasable(
            array_map('absint', is_array($candidates) ? $candidates : []),
            $limit
        );
    }

    /**
     * @param list<int> $candidates
     * @return list<int>
     */
    private static function filterEnabledPurchasable(array $candidates, int $limit): array
    {
        $ids = [];
        foreach ($candidates as $candidate) {
            $productId = absint($candidate);
            if ($productId <= 0) {
                continue;
            }

            $product = wc_get_product($productId);
            if (!$product instanceof \WC_Product || !$product->is_purchasable() || !$product->is_visible()) {
                continue;
            }

            if (!ProductSettings::forProduct($productId)->isUsable()) {
                continue;
            }

            $ids[] = $productId;
            if (count($ids) >= $limit) {
                break;
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private static function parseIdList(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $ids = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $part) {
            $id = absint($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private static function parseSlugList(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $slugs = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $part) {
            $slug = sanitize_title((string) $part);
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }
}
