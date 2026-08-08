<?php

declare(strict_types=1);

namespace Petshop\Core\Provisioning;

defined('ABSPATH') || exit;

trait StorefrontPages
{
    private static function ensurePage(string $slug, string $title, string $content): int
    {
        $existing = get_page_by_path($slug);
        if ($existing instanceof \WP_Post) {
            return (int) $existing->ID;
        }

        $pageId = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_name' => $slug,
            'post_title' => $title,
            'post_content' => $content,
            'meta_input' => ['_petshop_managed_page' => 1],
        ], true);

        if (is_wp_error($pageId)) {
            throw new \RuntimeException($pageId->get_error_message());
        }

        return (int) $pageId;
    }

    private static function wishlistPageContent(): string
    {
        return '<!-- wp:paragraph --><p>Produtos que você salvou para comprar depois.</p><!-- /wp:paragraph -->'
            . '<!-- wp:shortcode -->[petshop_wishlist]<!-- /wp:shortcode -->';
    }

    private static function migrateWishlistPage(int $pageId): void
    {
        if ($pageId <= 0) {
            return;
        }

        $page = get_post($pageId);
        if (!$page instanceof \WP_Post) {
            return;
        }

        $legacy = [
            '<!-- wp:heading --><h2 class="wp-block-heading">Lista de desejos</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Produtos que você salvou para comprar depois.</p><!-- /wp:paragraph -->'
            . '<!-- wp:shortcode -->[petshop_wishlist]<!-- /wp:shortcode -->',
            '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">Lista de desejos</h2>' . "\n"
            . '<!-- /wp:heading -->' . "\n\n"
            . '<!-- wp:paragraph -->' . "\n" . '<p>Produtos que você salvou para comprar depois.</p>' . "\n"
            . '<!-- /wp:paragraph -->' . "\n\n"
            . '<!-- wp:shortcode -->' . "\n" . '[petshop_wishlist]' . "\n" . '<!-- /wp:shortcode -->',
        ];

        $current = trim((string) $page->post_content);
        if (!in_array($current, $legacy, true) && !in_array(str_replace(["\r\n", "\r"], "\n", $current), array_map(
            static fn (string $value): string => str_replace(["\r\n", "\r"], "\n", $value),
            $legacy
        ), true)) {
            return;
        }

        $updated = wp_update_post([
            'ID' => $pageId,
            'post_content' => self::wishlistPageContent(),
        ], true);

        if (is_wp_error($updated)) {
            throw new \RuntimeException($updated->get_error_message());
        }
    }


    private static function upgradeWooCommerceBlocks(): void
    {
        $pages = [
            'woocommerce_cart_page_id' => '<!-- wp:woocommerce/cart /-->',
            'woocommerce_checkout_page_id' => '<!-- wp:woocommerce/checkout /-->',
        ];

        foreach ($pages as $option => $block) {
            $pageId = (int) get_option($option);
            $page = get_post($pageId);
            if (!$page instanceof \WP_Post) {
                continue;
            }

            $content = trim($page->post_content);
            if ($content === '' || in_array($content, ['[woocommerce_cart]', '[woocommerce_checkout]'], true)) {
                wp_update_post(['ID' => $pageId, 'post_content' => $block]);
            }
        }
    }
}
