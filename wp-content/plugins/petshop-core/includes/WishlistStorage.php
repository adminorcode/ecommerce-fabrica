<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

trait WishlistStorage
{
    private static function getStoredIds(): array
    {
        if (!is_user_logged_in()) {
            return [];
        }

        return self::getStoredIdsForUser(get_current_user_id());
    }

    /**
     * @return list<int>
     */
    private static function getStoredIdsForUser(int $userId): array
    {
        $raw = get_user_meta($userId, self::META_KEY, true);
        if (!is_array($raw)) {
            return [];
        }

        return self::sanitizeProductIds(array_map('absint', $raw));
    }

    /**
     * @param list<int> $ids
     */
    private static function persistIds(array $ids): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        update_user_meta(get_current_user_id(), self::META_KEY, self::sanitizeProductIds($ids));
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private static function sanitizeProductIds(array $ids): array
    {
        $unique = [];

        foreach ($ids as $id) {
            $productId = absint($id);
            if ($productId <= 0 || isset($unique[$productId]) || !self::isValidProduct($productId)) {
                continue;
            }

            $unique[$productId] = $productId;
        }

        return array_values($unique);
    }

    private static function isValidProduct(int $productId): bool
    {
        $product = wc_get_product($productId);

        return $product instanceof \WC_Product && $product->is_visible();
    }

    /**
     * @return list<int>
     */
    private static function parseIdsFromRequest(bool $readPost = true): array
    {
        if (!$readPost) {
            return [];
        }

        $raw = $_POST['productIds'] ?? $_POST['ids'] ?? [];
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_map('absint', $raw);
    }

    private static function shouldEnqueue(): bool
    {
        if (!function_exists('is_woocommerce')) {
            return false;
        }

        if (is_shop() || is_product_category() || is_product_tag() || is_search() || is_front_page()) {
            return true;
        }

        $pageId = (int) get_theme_mod('petshop_wishlist_page', 0);
        if ($pageId > 0 && is_page($pageId)) {
            return true;
        }

        $page = get_page_by_path(self::PAGE_SLUG);
        if ($page instanceof \WP_Post && is_page((int) $page->ID)) {
            return true;
        }

        if (function_exists('is_account_page') && is_account_page()) {
            if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url(self::ENDPOINT)) {
                return true;
            }
        }

        return false;
    }
}
