<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class Routes
{
    /** @var array<string, array{slug: string, title: string, legacy_slug: string, legacy_title: string}> */
    private const PAGES = [
        'woocommerce_shop_page_id' => ['slug' => 'loja', 'title' => 'Loja', 'legacy_slug' => 'shop', 'legacy_title' => 'Shop'],
        'woocommerce_cart_page_id' => ['slug' => 'carrinho', 'title' => 'Carrinho', 'legacy_slug' => 'cart', 'legacy_title' => 'Cart'],
        'woocommerce_checkout_page_id' => ['slug' => 'finalizar-compra', 'title' => 'Finalizar compra', 'legacy_slug' => 'checkout', 'legacy_title' => 'Checkout'],
        'woocommerce_myaccount_page_id' => ['slug' => 'minha-conta', 'title' => 'Minha conta', 'legacy_slug' => 'my-account', 'legacy_title' => 'My account'],
    ];

    public static function bootstrap(): void
    {
        add_action('template_redirect', [self::class, 'redirectLegacyRoute'], 0);
    }

    public static function migratePages(): void
    {
        foreach (self::PAGES as $option => $definition) {
            $pageId = (int) get_option($option);
            $page = get_post($pageId);
            if (!$page instanceof \WP_Post || $page->post_type !== 'page') {
                continue;
            }

            $update = ['ID' => $pageId];
            if (in_array($page->post_name, [$definition['legacy_slug'], $definition['slug']], true)) {
                $update['post_name'] = $definition['slug'];
            }
            if (in_array(trim($page->post_title), [$definition['legacy_title'], $definition['title']], true)) {
                $update['post_title'] = $definition['title'];
            }
            if (count($update) === 1) {
                continue;
            }

            $result = wp_update_post($update, true);
            if (is_wp_error($result)) {
                throw new \RuntimeException($result->get_error_message());
            }
        }
    }

    public static function redirectLegacyRoute(): void
    {
        if (is_admin() || !isset($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'])) {
            return;
        }

        $method = strtoupper(sanitize_key(wp_unslash((string) $_SERVER['REQUEST_METHOD'])));
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return;
        }

        $path = (string) wp_parse_url(wp_unslash((string) $_SERVER['REQUEST_URI']), PHP_URL_PATH);
        $homePath = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
        $relativePath = trim($path, '/');
        if ($homePath !== '' && str_starts_with($relativePath, $homePath . '/')) {
            $relativePath = substr($relativePath, strlen($homePath) + 1);
        }

        foreach (self::PAGES as $option => $definition) {
            if ($relativePath !== $definition['legacy_slug']) {
                continue;
            }

            $pageId = (int) get_option($option);
            $target = $pageId > 0 ? get_permalink($pageId) : home_url('/' . $definition['slug'] . '/');
            if (!is_string($target) || $target === '') {
                return;
            }

            $query = [];
            if (isset($_SERVER['QUERY_STRING'])) {
                wp_parse_str(wp_unslash((string) $_SERVER['QUERY_STRING']), $query);
            }
            if ($query !== []) {
                $target = add_query_arg($query, $target);
            }

            wp_safe_redirect($target, 301, 'Petshop localized WooCommerce routes');
            exit;
        }
    }

    /** @return array<string, string> */
    public static function slugs(): array
    {
        $slugs = [];
        foreach (self::PAGES as $definition) {
            $slugs[$definition['legacy_slug']] = $definition['slug'];
        }

        return $slugs;
    }
}
