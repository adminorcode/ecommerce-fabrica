<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

final class StorefrontWishlist
{
    use WishlistStorage;

    public const ENDPOINT = 'lista-de-desejos';
    public const PAGE_SLUG = 'lista-de-desejos';
    private const META_KEY = 'petshop_wishlist_product_ids';
    private const SCRIPT_HANDLE = 'petshop-wishlist';

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerEndpoint'], 20);
        add_filter('woocommerce_get_query_vars', [self::class, 'registerQueryVar']);
        add_filter('woocommerce_account_menu_items', [self::class, 'registerAccountMenuItem']);
        add_action('template_redirect', [self::class, 'redirectLegacyPage'], 1);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [self::class, 'renderAccountEndpoint']);
        add_filter(
            'blocksy:options:woocommerce:archive:card-type:output_product_toolbar',
            [self::class, 'appendToolbarButton'],
            20
        );
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('wp_login', [self::class, 'mergeIdsForUser'], 10, 2);
        add_shortcode('petshop_wishlist', [self::class, 'renderShortcode']);
        add_action('wp_ajax_petshop_toggle_wishlist', [self::class, 'ajaxToggle']);
        add_action('wp_ajax_nopriv_petshop_toggle_wishlist', [self::class, 'ajaxToggle']);
        add_action('wp_ajax_petshop_merge_wishlist', [self::class, 'ajaxMerge']);
        add_action('wp_ajax_petshop_render_wishlist', [self::class, 'ajaxRender']);
        add_action('wp_ajax_nopriv_petshop_render_wishlist', [self::class, 'ajaxRender']);
    }

    public static function registerEndpoint(): void
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_PAGES);
    }

    /**
     * @param array<string, string> $vars
     * @return array<string, string>
     */
    public static function registerQueryVar(array $vars): array
    {
        $vars[self::ENDPOINT] = self::ENDPOINT;

        return $vars;
    }

    /**
     * @param array<string, string> $items
     * @return array<string, string>
     */
    public static function registerAccountMenuItem(array $items): array
    {
        $logout = $items['customer-logout'] ?? null;
        unset($items['customer-logout']);

        $items[self::ENDPOINT] = __('Lista de desejos', 'petshop-core');

        if ($logout !== null) {
            $items['customer-logout'] = $logout;
        }

        return $items;
    }

    public static function renderAccountEndpoint(): void
    {
        $pageId = (int) get_theme_mod('petshop_wishlist_page', 0);
        $page = $pageId > 0 ? get_post($pageId) : get_page_by_path(self::PAGE_SLUG);

        if (
            $page instanceof \WP_Post
            && $page->post_status === 'publish'
            && has_shortcode((string) $page->post_content, 'petshop_wishlist')
        ) {
            echo apply_filters('the_content', (string) $page->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        echo self::renderWishlistSection(false);
    }

    public static function redirectLegacyPage(): void
    {
        $pageId = (int) get_theme_mod('petshop_wishlist_page', 0);
        $isLegacyPage = $pageId > 0 ? is_page($pageId) : is_page(self::PAGE_SLUG);

        if (!$isLegacyPage) {
            return;
        }

        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
            return;
        }

        if (wp_safe_redirect(self::getAccountEndpointUrl(), 301, 'Petshop canonical wishlist')) {
            exit;
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderShortcode(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            [
                'empty' => __(
                    'Você ainda não salvou produtos. Explore a loja e toque no coração dos itens que desejar.',
                    'petshop-core'
                ),
            ],
            $attributes,
            'petshop_wishlist'
        );

        return self::renderWishlistSection(true, (string) $attributes['empty']);
    }

    public static function getPageUrl(): string
    {
        return self::getAccountEndpointUrl();
    }

    public static function getAccountEndpointUrl(): string
    {
        if (!function_exists('wc_get_account_endpoint_url')) {
            return home_url('/minha-conta/' . self::ENDPOINT . '/');
        }

        return (string) wc_get_account_endpoint_url(self::ENDPOINT);
    }

    /**
     * @param string[] $components
     * @return string[]
     */
    public static function appendToolbarButton(array $components): array
    {
        global $product;

        if (!$product instanceof \WC_Product) {
            return $components;
        }

        $components[] = self::renderToggleButton($product);

        return $components;
    }

    public static function enqueueAssets(): void
    {
        if (!self::shouldEnqueue()) {
            return;
        }

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            plugins_url('assets/js/wishlist.js', PETSHOP_CORE_FILE),
            [],
            (string) get_option('petshop_storefront_version', '1'),
            true
        );

        wp_localize_script(self::SCRIPT_HANDLE, 'petshopWishlist', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('petshop_wishlist'),
            'loggedIn' => is_user_logged_in(),
            'productIds' => self::getStoredIds(),
            'pageUrl' => self::getPageUrl(),
            'labels' => [
                'add' => __('Adicionar à lista de desejos', 'petshop-core'),
                'remove' => __('Remover da lista de desejos', 'petshop-core'),
            ],
        ]);
    }

    public static function ajaxToggle(): void
    {
        check_ajax_referer('petshop_wishlist', 'nonce');

        $productId = absint($_POST['productId'] ?? 0);
        if ($productId <= 0 || !self::isValidProduct($productId)) {
            wp_send_json_error(['message' => __('Produto inválido.', 'petshop-core')], 400);
        }

        $ids = self::getStoredIds();
        $active = in_array($productId, $ids, true);

        if ($active) {
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $productId));
        } else {
            $ids[] = $productId;
        }

        $ids = self::sanitizeProductIds($ids);
        self::persistIds($ids);

        wp_send_json_success([
            'productId' => $productId,
            'active' => !$active,
            'productIds' => $ids,
        ]);
    }

    public static function ajaxMerge(): void
    {
        check_ajax_referer('petshop_wishlist', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('É necessário estar logado.', 'petshop-core')], 401);
        }

        $incoming = self::parseIdsFromRequest();
        $merged = self::sanitizeProductIds(array_merge(self::getStoredIds(), $incoming));
        self::persistIds($merged);

        wp_send_json_success([
            'productIds' => $merged,
            'html' => self::renderProductGrid($merged),
        ]);
    }

    public static function ajaxRender(): void
    {
        check_ajax_referer('petshop_wishlist', 'nonce');

        $ids = self::sanitizeProductIds(self::parseIdsFromRequest());
        if ($ids === [] && is_user_logged_in()) {
            $ids = self::getStoredIds();
        }

        wp_send_json_success([
            'productIds' => $ids,
            'html' => self::renderProductGrid($ids),
        ]);
    }

    public static function mergeIdsForUser(string $userLogin, \WP_User $user): void
    {
        unset($userLogin);

        $incoming = self::parseIdsFromRequest(false);
        if ($incoming === []) {
            return;
        }

        $merged = self::sanitizeProductIds(array_merge(self::getStoredIdsForUser((int) $user->ID), $incoming));
        update_user_meta((int) $user->ID, self::META_KEY, $merged);
    }

    private static function renderWishlistSection(bool $wrapSection, string $emptyMessage = ''): string
    {
        if ($emptyMessage === '') {
            $emptyMessage = __(
                'Você ainda não salvou produtos. Explore a loja e toque no coração dos itens que desejar.',
                'petshop-core'
            );
        }

        $ids = self::getStoredIds();
        $grid = $ids !== [] ? self::renderProductGrid($ids) : '';

        ob_start();

        if ($wrapSection) {
            echo '<section class="petshop-section petshop-wishlist-page" data-empty="';
            echo esc_attr($emptyMessage);
            echo '">';
        } else {
            echo '<div class="petshop-wishlist-account" data-empty="';
            echo esc_attr($emptyMessage);
            echo '">';
        }

        echo '<div class="petshop-wishlist-page__products">';
        if ($grid !== '') {
            echo $grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo '<p class="petshop-wishlist-page__empty">' . esc_html($emptyMessage) . '</p>';
        }
        echo '</div>';

        echo $wrapSection ? '</section>' : '</div>';

        return (string) ob_get_clean();
    }

    /**
     * @param list<int> $ids
     */
    private static function renderProductGrid(array $ids): string
    {
        $ids = self::sanitizeProductIds($ids);
        if ($ids === []) {
            return '';
        }

        $html = do_shortcode(
            sprintf(
                '[products limit="%d" columns="4" ids="%s" paginate="false" orderby="post__in"]',
                count($ids),
                esc_attr(implode(',', $ids))
            )
        );

        return preg_match('/class="[^"]*\bproduct\b[^"]*"/', $html) ? $html : '';
    }

    private static function renderToggleButton(\WC_Product $product): string
    {
        $productId = $product->get_id();
        $active = in_array($productId, self::getStoredIds(), true);

        return sprintf(
            '<button type="button" class="petshop-wishlist-toggle%s" data-product-id="%d" aria-pressed="%s" aria-label="%s">%s</button>',
            $active ? ' is-active' : '',
            $productId,
            $active ? 'true' : 'false',
            esc_attr($active ? __('Remover da lista de desejos', 'petshop-core') : __('Adicionar à lista de desejos', 'petshop-core')),
            self::heartIconSvg()
        );
    }

    private static function heartIconSvg(): string
    {
        return '<svg class="petshop-wishlist-toggle__icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 20.25s-7.5-4.35-9.75-8.1C.75 9.15 2.1 5.4 5.7 4.5c2.1-.525 4.05.45 5.55 2.1 1.5-1.65 3.45-2.625 5.55-2.1 3.6.9 4.95 4.65 3.45 7.65C19.5 15.9 12 20.25 12 20.25z" fill="none" stroke="currentColor" stroke-width="1.75"/></svg>';
    }
}
