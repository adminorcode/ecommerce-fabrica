<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

/** Registers the Cart Block integration and the visible CEP inner block. */
final class CartBlocksIntegration
{
    private const MIGRATION_OPTION = 'petshop_cart_shipping_quote_block_v1';
    private const WRAPPER_REPAIR_OPTION = 'petshop_cart_shipping_quote_wrapper_v2';
    private const POSITION_OPTION = 'petshop_cart_shipping_quote_position_v3';

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerBlock']);
        add_action('init', [self::class, 'registerSharedAssets'], 5);
        add_action('init', [self::class, 'migrateCartPage'], 30);
        add_action('init', [self::class, 'repairCartQuoteWrapper'], 31);
        add_action('init', [self::class, 'positionCartQuoteBeforeSummary'], 32);
        add_action('woocommerce_blocks_cart_block_registration', [self::class, 'registerCartBlockIntegration']);
    }

    public static function registerSharedAssets(): void
    {
        foreach (['quote-preference', 'cart-request-coordinator', 'cart-operations'] as $asset) {
            $relative = 'assets/src/shared/' . $asset . '.js';
            wp_register_script('petshop-' . $asset, plugins_url($relative, PETSHOP_CORE_FILE),
                $asset === 'cart-operations' ? ['wc-blocks-data-store', 'wc-blocks-checkout', 'wp-data', 'petshop-quote-preference', 'petshop-cart-request-coordinator']
                    : ($asset === 'cart-request-coordinator' ? ['wp-api-fetch'] : []),
                (string) filemtime(plugin_dir_path(PETSHOP_CORE_FILE) . $relative), true);
        }
        wp_add_inline_script('petshop-quote-preference', 'window.petshopCartOperationsConfig=' . wp_json_encode([
            'storeKey' => home_url('/'),
            'account' => get_current_user_id(),
            'cartUpdateItemUrl' => rest_url('wc/store/v1/cart/update-item'),
        ]) . ';', 'before');
    }

    /** Registers after WooCommerce has loaded IntegrationInterface and the Cart block registry. */
    public static function registerCartBlockIntegration(object $registry): void
    {
        if (!interface_exists(\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface::class)
            || !method_exists($registry, 'register')) {
            return;
        }

        $registry->register(new CartShippingQuoteBlocksIntegration());
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
        $result = self::containsQuoteBlock($blocks) ? 'already-present' : self::appendToCartTotals($blocks);
        if ($result === 'missing-parent') {
            return;
        }

        $updated = serialize_blocks($blocks);
        if ($updated === $content && $result !== 'already-present') {
            return;
        }

        if ($result === 'inserted') {
            $updatedId = wp_update_post(wp_slash(['ID' => $pageId, 'post_content' => $updated]), true);
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
    private static function containsQuoteBlock(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'petshop/cart-shipping-quote'
                || self::containsQuoteBlock($block['innerBlocks'] ?? [])) return true;
        }
        return false;
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
                self::appendInnerSlot($block['innerContent']);
                self::moveQuoteFirst($block);

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

    /** Insert inside the saved wrapper, before its closing tag. */
    private static function appendInnerSlot(array &$content): void
    {
        $last = array_key_last($content);
        if ($last !== null && is_string($content[$last])
            && preg_match('~(</div>\s*)$~', $content[$last], $match)) {
            $closing = $match[1];
            $content[$last] = substr($content[$last], 0, -strlen($closing));
            $content[] = null;
            $content[] = $closing;
            return;
        }
        $content[] = null;
    }

    /** Repair only the malformed slot produced by v1; never recreate removed blocks. */
    public static function repairCartQuoteWrapper(): void
    {
        if (get_option(self::WRAPPER_REPAIR_OPTION, '') !== '') return;
        $migration = get_option(self::MIGRATION_OPTION, []);
        $pageId = function_exists('wc_get_page_id') ? (int) wc_get_page_id('cart') : 0;
        if (!$pageId || !is_array($migration) || (int) ($migration['page_id'] ?? 0) !== $pageId) return;
        $content = (string) get_post_field('post_content', $pageId);
        $blocks = parse_blocks($content);
        $changed = self::repairWrapperSlots($blocks);
        if ($changed) {
            $saved = wp_update_post(wp_slash(['ID' => $pageId, 'post_content' => serialize_blocks($blocks)]), true);
            if (is_wp_error($saved) || (int) $saved !== $pageId) return;
        }
        update_option(self::WRAPPER_REPAIR_OPTION, ['version' => 2, 'page_id' => $pageId,
            'previous_content' => $content, 'repaired' => $changed, 'applied_at' => time()], false);
    }

    private static function repairWrapperSlots(array &$blocks): bool
    {
        $changed = false;
        foreach ($blocks as &$block) {
            if (($block['blockName'] ?? '') === 'woocommerce/cart-totals-block') {
                $children = $block['innerBlocks'] ?? [];
                $lastChild = $children ? end($children) : null;
                $slots = $block['innerContent'] ?? [];
                $last = array_key_last($slots);
                // parse_blocks adds a trailing empty/whitespace fragment after
                // the last inner block even when v1 appended its slot at EOF.
                while ($last !== null && $last > 0 && is_string($slots[$last]) && trim($slots[$last]) === '') $last--;
                if (($lastChild['blockName'] ?? '') === 'petshop/cart-shipping-quote'
                    && $last !== null && $slots[$last] === null && $last > 0
                    && is_string($slots[$last - 1]) && preg_match('~</div>\s*$~', $slots[$last - 1])) {
                    $trailing = implode('', array_slice($slots, $last + 1));
                    $block['innerContent'] = array_slice($slots, 0, $last);
                    self::appendInnerSlot($block['innerContent']);
                    $block['innerContent'][array_key_last($block['innerContent'])] .= $trailing;
                    $changed = true;
                }
            }
            if (!empty($block['innerBlocks'])) $changed = self::repairWrapperSlots($block['innerBlocks']) || $changed;
        }
        return $changed;
    }

    /** Restore the original CEP position once, preserving subsequent editor changes. */
    public static function positionCartQuoteBeforeSummary(): void
    {
        if (get_option(self::POSITION_OPTION, '') !== '') return;
        $pageId = function_exists('wc_get_page_id') ? (int) wc_get_page_id('cart') : 0;
        if ($pageId <= 0) return;
        $content = (string) get_post_field('post_content', $pageId);
        $blocks = parse_blocks($content);
        $changed = self::positionQuoteBlocks($blocks);
        if ($changed) {
            $saved = wp_update_post(wp_slash(['ID' => $pageId, 'post_content' => serialize_blocks($blocks)]), true);
            if (is_wp_error($saved) || (int) $saved !== $pageId) return;
        }
        update_option(self::POSITION_OPTION, ['page_id' => $pageId, 'previous_content' => $content,
            'moved' => $changed, 'applied_at' => time()], false);
    }

    private static function positionQuoteBlocks(array &$blocks): bool
    {
        $changed = false;
        foreach ($blocks as &$block) {
            if (($block['blockName'] ?? '') === 'woocommerce/cart-totals-block') {
                $changed = self::moveQuoteFirst($block) || $changed;
            }
            if (!empty($block['innerBlocks'])) $changed = self::positionQuoteBlocks($block['innerBlocks']) || $changed;
        }
        return $changed;
    }

    private static function moveQuoteFirst(array &$block): bool
    {
        $children = $block['innerBlocks'] ?? [];
        foreach ($children as $index => $child) {
            if (($child['blockName'] ?? '') !== 'petshop/cart-shipping-quote') continue;
            if ($index === 0) return false;
            $slots = $block['innerContent'] ?? [];
            $positions = array_keys($slots, null, true);
            if (!isset($positions[$index])) return false;
            array_splice($slots, $positions[$index], 1);
            array_splice($slots, $positions[0], 0, [null]);
            array_splice($children, $index, 1);
            array_unshift($children, $child);
            $block['innerBlocks'] = $children;
            $block['innerContent'] = $slots;
            return true;
        }
        return false;
    }

    /** @param array<string, mixed> $attributes */
    public static function renderBlock(array $attributes = [], string $content = '', ?\WP_Block $block = null): string
    {
        unset($attributes, $content, $block);

        return '<section ' . get_block_wrapper_attributes([
            'class' => 'petshop-cart-shipping',
            'aria-label' => __('Calcular entrega', 'petshop-core'),
        ]) . '></section>';
    }
}
