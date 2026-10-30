<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce\PaymentDiscount;

defined('ABSPATH') || exit;

final class OrderItem
{
    public const META_PERCENT = 'petshop_payment_discount_percent';
    public const META_AMOUNT = 'petshop_payment_discount_amount';

    public static function bootstrap(): void
    {
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'snapshot'], 20, 3);
        add_filter('woocommerce_order_item_display_meta_key', [self::class, 'displayKey'], 10, 2);
        add_filter('woocommerce_order_item_display_meta_value', [self::class, 'displayValue'], 10, 2);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [self::class, 'hideOnStorefront'], 20);
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function snapshot(\WC_Order_Item_Product $item, string $cartItemKey, array $values): void
    {
        unset($cartItemKey);
        $percent = isset($values[Cart::PERCENT_KEY]) && is_scalar($values[Cart::PERCENT_KEY])
            ? Percent::normalizeStored((string) $values[Cart::PERCENT_KEY])
            : '0.00';
        if ((float) $percent <= 0) {
            return;
        }

        $base = isset($values[Cart::BASE_KEY]) && is_scalar($values[Cart::BASE_KEY])
            ? Money::normalize((string) $values[Cart::BASE_KEY])
            : '0.00';
        $quantity = isset($values['quantity']) ? (int) $values['quantity'] : 1;
        $amount = Money::multiply(Money::discountAmount($base, $percent), max(1, $quantity));

        $item->add_meta_data(self::META_PERCENT, $percent, true);
        $item->add_meta_data(self::META_AMOUNT, $amount, true);
    }

    public static function displayKey(string $key, \WC_Meta_Data $meta): string
    {
        if ($meta->key === self::META_PERCENT) {
            return __('Porcentagem do meio', 'petshop-core');
        }
        if ($meta->key === self::META_AMOUNT) {
            return __('Valor do desconto do meio', 'petshop-core');
        }

        return $key;
    }

    public static function displayValue(mixed $value, \WC_Meta_Data $meta): mixed
    {
        if ($meta->key === self::META_PERCENT && is_scalar($value)) {
            return esc_html((string) $value) . '%';
        }
        if ($meta->key === self::META_AMOUNT && is_scalar($value) && function_exists('wc_price')) {
            return wc_price((string) $value);
        }

        return $value;
    }

    /**
     * @param array<int, \stdClass> $formatted
     * @return array<int, \stdClass>
     */
    public static function hideOnStorefront(array $formatted): array
    {
        if (is_admin()) {
            return $formatted;
        }

        foreach ($formatted as $id => $meta) {
            if (!isset($meta->key)) {
                continue;
            }
            if (in_array($meta->key, [self::META_PERCENT, self::META_AMOUNT], true)) {
                unset($formatted[$id]);
            }
        }

        return $formatted;
    }
}
