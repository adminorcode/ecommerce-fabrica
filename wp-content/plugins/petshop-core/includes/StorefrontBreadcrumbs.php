<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

final class StorefrontBreadcrumbs
{
    private static bool $rendered = false;

    public static function bootstrap(): void
    {
        add_action('blocksy:content:top', [self::class, 'maybeRender'], 5);
        add_filter('woocommerce_get_breadcrumb', [self::class, 'filterWooCommerceTrail'], 10, 2);
        add_filter('woocommerce_page_title', [self::class, 'filterWooCommercePageTitle']);
        add_filter('the_title', [self::class, 'filterWooCommercePageHeading'], 10, 2);
    }

    public static function maybeRender(): void
    {
        if (self::$rendered || is_front_page()) {
            return;
        }

        if (
            class_exists('WooCommerce')
            && (is_woocommerce() || is_cart() || is_checkout() || is_account_page())
        ) {
            self::$rendered = true;
            woocommerce_breadcrumb(self::woocommerceArgs());

            return;
        }

        if (is_page() || is_search()) {
            self::$rendered = true;
            self::renderContextualTrail();
        }
    }

    /**
     * @return array<string, string>
     */
    private static function woocommerceArgs(): array
    {
        $label = esc_attr__('Trilha de navegação', 'petshop-core');

        return [
            'delimiter' => '',
            'wrap_before' => '<nav class="petshop-breadcrumbs" aria-label="' . $label . '"><div class="ct-container"><ol class="petshop-breadcrumbs__list">',
            'wrap_after' => '</ol></div></nav>',
            'before' => '<li class="petshop-breadcrumbs__item">',
            'after' => '</li>',
            'home' => _x('Início', 'breadcrumb', 'petshop-core'),
        ];
    }

    /**
     * @param array<int, array{0: string, 1?: string}> $crumbs
     * @return array<int, array{0: string, 1?: string}>
     */
    public static function filterWooCommerceTrail(array $crumbs, \WC_Breadcrumb $breadcrumb): array
    {
        unset($breadcrumb);

        if (!function_exists('wc_get_page_permalink')) {
            return $crumbs;
        }

        $shopUrl = wc_get_page_permalink('shop');
        $shopLabel = self::shopLabel();

        foreach ($crumbs as $index => $crumb) {
            $url = $crumb[1] ?? '';
            if ($url !== '' && isset(self::woocommercePageUrls()[$url])) {
                $crumbs[$index][0] = self::woocommercePageUrls()[$url];
            }
        }

        $lastIndex = count($crumbs) - 1;
        if ($lastIndex >= 0) {
            $crumbs[$lastIndex][0] = self::currentWooCommercePageLabel($crumbs[$lastIndex][0]);
        }

        if (
            $shopUrl !== ''
            && (is_product_category() || is_product_tag() || is_product())
        ) {
            $hasShop = false;
            foreach ($crumbs as $crumb) {
                if (($crumb[1] ?? '') === $shopUrl) {
                    $hasShop = true;
                    break;
                }
            }

            if (!$hasShop) {
                array_splice($crumbs, 1, 0, [[$shopLabel, $shopUrl]]);
            }
        }

        return $crumbs;
    }

    public static function filterWooCommercePageTitle(string $title): string
    {
        if (is_shop()) {
            return self::shopLabel();
        }

        if (is_cart()) {
            return __('Carrinho', 'petshop-core');
        }

        if (is_checkout()) {
            return __('Finalizar compra', 'petshop-core');
        }

        if (is_account_page()) {
            return __('Minha conta', 'petshop-core');
        }

        return $title;
    }

    public static function filterWooCommercePageHeading(string $title, int $postId = 0): string
    {
        if (is_admin() || $postId <= 0) {
            return $title;
        }

        foreach (self::woocommercePageIds() as $pageId => $label) {
            if ($postId === $pageId) {
                return $label;
            }
        }

        return $title;
    }

    /**
     * @return array<int, string>
     */
    private static function woocommercePageIds(): array
    {
        $ids = [];

        foreach (self::woocommercePageUrls() as $url => $label) {
            $pageId = url_to_postid($url);
            if ($pageId > 0) {
                $ids[$pageId] = $label;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, string>
     */
    private static function woocommercePageUrls(): array
    {
        static $urls = null;

        if ($urls !== null) {
            return $urls;
        }

        $urls = [];
        $definitions = [
            'shop' => __('Loja', 'petshop-core'),
            'cart' => __('Carrinho', 'petshop-core'),
            'checkout' => __('Finalizar compra', 'petshop-core'),
            'myaccount' => __('Minha conta', 'petshop-core'),
        ];

        foreach ($definitions as $pageKey => $label) {
            $url = wc_get_page_permalink($pageKey);
            if (is_string($url) && $url !== '') {
                $urls[$url] = $label;
            }
        }

        return $urls;
    }

    private static function currentWooCommercePageLabel(string $fallback): string
    {
        if (is_cart()) {
            return __('Carrinho', 'petshop-core');
        }

        if (is_checkout()) {
            return __('Finalizar compra', 'petshop-core');
        }

        if (is_account_page()) {
            return __('Minha conta', 'petshop-core');
        }

        if (is_shop()) {
            return self::shopLabel();
        }

        return $fallback;
    }

    private static function shopLabel(): string
    {
        $shopId = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
        if ($shopId > 0) {
            $title = trim(get_the_title($shopId));
            if ($title !== '' && strcasecmp($title, 'Shop') !== 0) {
                return $title;
            }
        }

        return __('Loja', 'petshop-core');
    }

    private static function renderContextualTrail(): void
    {
        if (is_search()) {
            self::renderItems(self::searchTrail());

            return;
        }

        if (is_page()) {
            self::renderItems(self::pageTrail());

            return;
        }
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function searchTrail(): array
    {
        $items = [
            [_x('Início', 'breadcrumb', 'petshop-core'), home_url('/')],
        ];

        if (class_exists('WooCommerce')) {
            $items[] = [self::shopLabel(), wc_get_page_permalink('shop')];
        }

        $query = trim(get_search_query());
        $items[] = [
            $query !== ''
                ? sprintf(
                    /* translators: %s: search query */
                    __('Busca por "%s"', 'petshop-core'),
                    $query
                )
                : __('Busca', 'petshop-core'),
            '',
        ];

        return $items;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function pageTrail(): array
    {
        $pageId = get_queried_object_id();
        $items = [
            [_x('Início', 'breadcrumb', 'petshop-core'), home_url('/')],
        ];

        foreach (array_reverse(get_post_ancestors($pageId)) as $ancestorId) {
            $items[] = [get_the_title($ancestorId), (string) get_permalink($ancestorId)];
        }

        $items[] = [get_the_title($pageId), ''];

        return $items;
    }

    /**
     * @param list<array{0: string, 1: string}> $items
     */
    private static function renderItems(array $items): void
    {
        if ($items === []) {
            return;
        }

        echo '<nav class="petshop-breadcrumbs" aria-label="' . esc_attr__('Trilha de navegação', 'petshop-core') . '">';
        echo '<div class="ct-container"><ol class="petshop-breadcrumbs__list">';

        $lastIndex = count($items) - 1;
        foreach ($items as $index => $item) {
            [$label, $url] = $item;
            echo '<li class="petshop-breadcrumbs__item">';
            if ($index !== $lastIndex && $url !== '') {
                echo '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
            } else {
                echo '<span aria-current="page">' . esc_html($label) . '</span>';
            }
            echo '</li>';
        }

        echo '</ol></div></nav>';
    }
}
