<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

/** Registers the Cart Block integration and the visible CEP inner block. */
final class CartBlocksIntegration
{
    private const MIGRATION_OPTION = 'petshop_cart_shipping_quote_block_v1';

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerBlock']);
        add_action('init', [self::class, 'migrateCartPage'], 30);
    }

    public static function registerBlock(): void
    {
        $directory = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/src/cart-shipping-quote';
        if (!is_dir($directory)) {
            return;
        }

        register_block_type($directory, ['render_callback' => [self::class, 'renderBlock']]);
    }

    /** Inserts the inner block once and never recreates it after an editor removes it. */
    public static function migrateCartPage(): void
    {
        if (get_option(self::MIGRATION_OPTION, '') !== '') {
            return;
        }

        $pageId = function_exists('wc_get_page_id') ? (int) wc_get_page_id('cart') : 0;
        if ($pageId <= 0) {
            return;
        }

        $content = (string) get_post_field('post_content', $pageId);
        $blocks = parse_blocks($content);
        $result = self::appendToCartTotals($blocks);
        if ($result === 'missing-parent') {
            return;
        }

        $updated = serialize_blocks($blocks);
        if ($updated === $content) {
            return;
        }

        if ($result === 'inserted') {
            $updatedId = wp_update_post(['ID' => $pageId, 'post_content' => $updated], true);
            if (is_wp_error($updatedId) || (int) $updatedId !== $pageId) {
                return;
            }
        }
        update_option(self::MIGRATION_OPTION, [
            'version' => 1,
            'page_id' => $pageId,
            'previous_content_hash' => wp_hash($content),
            'previous_content' => $content,
            'applied_at' => time(),
        ], false);
    }

    /** @param array<int, array<string, mixed>> $blocks */
    private static function appendToCartTotals(array &$blocks): string
    {
        foreach ($blocks as &$block) {
            if (($block['blockName'] ?? '') === 'petshop/cart-shipping-quote') {
                return 'already-present';
            }

            if (($block['blockName'] ?? '') === 'woocommerce/cart-totals-block') {
                $block['innerBlocks'] = is_array($block['innerBlocks'] ?? null) ? $block['innerBlocks'] : [];
                $block['innerBlocks'][] = [
                    'blockName' => 'petshop/cart-shipping-quote',
                    'attrs' => [],
                    'innerBlocks' => [],
                    'innerHTML' => '',
                    'innerContent' => [],
                ];
                $block['innerContent'] = is_array($block['innerContent'] ?? null) ? $block['innerContent'] : [];
                $block['innerContent'][] = null;

                return 'inserted';
            }

            if (is_array($block['innerBlocks'] ?? null)) {
                $result = self::appendToCartTotals($block['innerBlocks']);
                if ($result !== 'missing-parent') {
                    return $result;
                }
            }
        }

        return 'missing-parent';
    }

    /** @param array<string, mixed> $attributes */
    public static function renderBlock(array $attributes = [], string $content = '', ?\WP_Block $block = null): string
    {
        unset($attributes, $content, $block);

        return '<section ' . get_block_wrapper_attributes([
            'class' => 'petshop-cart-shipping',
            'data-petshop-cart-shipping' => '',
            'aria-label' => __('Calcular entrega', 'petshop-core'),
        ]) . '></section>';
    }
}
