<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce\PaymentDiscount;

defined('ABSPATH') || exit;

/**
 * Mini-cart line view: the full price stays visible and the payment-method
 * price is shown beside it. The charged total still follows Surface.
 */
final class MiniCartPrice
{
    public const NAMESPACE = 'petshop-payment-discount-price';

    public static function bootstrap(): void
    {
        add_action('woocommerce_blocks_loaded', [self::class, 'registerEndpointData']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueScript']);
    }

    public static function registerEndpointData(): void
    {
        if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }
        if (!class_exists(\Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::class)) {
            return;
        }

        woocommerce_store_api_register_endpoint_data([
            'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
            'namespace' => self::NAMESPACE,
            'data_callback' => [self::class, 'itemData'],
            'schema_callback' => [self::class, 'itemSchema'],
            'schema_type' => ARRAY_A,
        ]);
    }

    /**
     * @param array<string, mixed> $cartItem
     * @return array{visible: bool, label: string, full: string, discounted: string, charged_is_discounted: bool}
     */
    public static function itemData(array $cartItem): array
    {
        $empty = ['visible' => false, 'label' => '', 'full' => '', 'discounted' => '', 'charged_is_discounted' => false];
        $product = $cartItem['data'] ?? null;
        if (!$product instanceof \WC_Product || !Settings::hasGateways()) {
            return $empty;
        }

        $percent = ProductMeta::effectivePercent($product);
        if ((float) $percent <= 0) {
            return $empty;
        }

        $base = isset($cartItem[Cart::BASE_KEY]) && is_scalar($cartItem[Cart::BASE_KEY])
            ? Money::normalize((string) $cartItem[Cart::BASE_KEY])
            : Money::normalize((string) $product->get_price());
        if (Money::toMinor($base) <= 0) {
            return $empty;
        }

        $discounted = Money::discounted($base, $percent);

        return [
            'visible' => true,
            'label' => Settings::label(),
            'full' => self::plain($base),
            'discounted' => self::plain($discounted),
            'charged_is_discounted' => Money::toMinor((string) $product->get_price()) === Money::toMinor($discounted),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function itemSchema(): array
    {
        $field = static fn (string $description, string $type): array => [
            'description' => $description,
            'type' => $type,
            'context' => ['view', 'edit'],
            'readonly' => true,
        ];

        return [
            'visible' => $field('Whether the payment price is shown.', 'boolean'),
            'label' => $field('Payment-method price label.', 'string'),
            'full' => $field('Undiscounted unit price.', 'string'),
            'discounted' => $field('Unit price after the payment percent.', 'string'),
            'charged_is_discounted' => $field('Whether the cart unit price is already the payment price.', 'boolean'),
        ];
    }

    public static function enqueueScript(): void
    {
        if (is_admin() || !wp_script_is('wc-blocks-checkout', 'registered')) {
            return;
        }

        $relative = 'assets/js/payment-discount-mini-cart.js';
        $path = plugin_dir_path(PETSHOP_CORE_FILE) . $relative;
        wp_enqueue_script(
            'petshop-payment-discount-mini-cart',
            plugins_url($relative, PETSHOP_CORE_FILE),
            ['wc-blocks-checkout'],
            is_file($path) ? (string) filemtime($path) : '1.0.0',
            true
        );
    }

    private static function plain(string $amount): string
    {
        $html = function_exists('wc_price') ? wc_price($amount) : $amount;
        $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
