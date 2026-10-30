<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce\PaymentDiscount;

defined('ABSPATH') || exit;

final class Cart
{
    public const SURCHARGE_KEY = 'petshop_personalization_surcharge';
    public const SESSION_METHOD = 'petshop_payment_discount_method';
    public const BASE_KEY = 'petshop_payment_discount_base';
    public const PERCENT_KEY = 'petshop_payment_discount_percent';

    /** @var array<string, string> */
    private static array $baseByKey = [];

    /** @var array<string, string> */
    private static array $percentByKey = [];

    private static bool $running = false;

    public static function bootstrap(): void
    {
        add_action('woocommerce_before_calculate_totals', [self::class, 'adjust'], 9990);
        add_action('woocommerce_cart_calculate_fees', [self::class, 'addPersonalizationFee']);
    }

    public static function selectedMethod(): string
    {
        if (!function_exists('WC') || !WC()->session) {
            return '';
        }

        $own = WC()->session->get(self::SESSION_METHOD);
        if (is_string($own) && $own !== '') {
            return sanitize_key($own);
        }

        $chosen = WC()->session->get('chosen_payment_method');

        return is_string($chosen) ? sanitize_key($chosen) : '';
    }

    public static function remember(string $method): void
    {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $method = sanitize_key($method);
        WC()->session->set(self::SESSION_METHOD, $method);
        WC()->session->set('chosen_payment_method', $method);
    }

    public static function applies(): bool
    {
        return Surface::allowsCurrentRequest() && Settings::allows(self::selectedMethod());
    }

    public static function adjust(\WC_Cart $cart): void
    {
        if (self::$running) {
            return;
        }

        self::$running = true;
        try {
            $apply = self::applies();
            $live = [];
            foreach ($cart->get_cart() as $key => $item) {
                $product = $item['data'] ?? null;
                if (!$product instanceof \WC_Product) {
                    continue;
                }

                $live[(string) $key] = true;
                $base = self::captureBase((string) $key, $product);
                $percent = $apply ? ProductMeta::effectivePercent($product) : '0.00';
                $next = (float) $percent > 0 ? Money::discounted($base, $percent) : $base;
                $product->set_price($next);
                $cart->cart_contents[$key][self::BASE_KEY] = $base;
                $cart->cart_contents[$key][self::PERCENT_KEY] = $percent;
                self::$percentByKey[(string) $key] = $percent;
            }
            foreach (array_keys(self::$baseByKey) as $cachedKey) {
                if (!isset($live[$cachedKey])) {
                    unset(self::$baseByKey[$cachedKey], self::$percentByKey[$cachedKey]);
                }
            }
        } finally {
            self::$running = false;
        }
    }

    public static function addPersonalizationFee(\WC_Cart $cart): void
    {
        $total = 0;
        foreach ($cart->get_cart() as $item) {
            $raw = $item[self::SURCHARGE_KEY] ?? '';
            if (!is_scalar($raw)) {
                continue;
            }
            $unit = Money::normalize((string) $raw);
            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
            $total += Money::toMinor(Money::multiply($unit, max(1, $quantity)));
        }

        if ($total <= 0) {
            return;
        }

        $cart->add_fee(__('Personalização', 'petshop-core'), (float) Money::fromMinor($total), false);
    }

    private static function captureBase(string $key, \WC_Product $product): string
    {
        $current = Money::normalize((string) $product->get_price());
        $cached = self::$baseByKey[$key] ?? null;
        if ($cached === null) {
            self::$baseByKey[$key] = $current;

            return $current;
        }

        $previousPercent = self::$percentByKey[$key] ?? '0.00';
        $discounted = (float) $previousPercent > 0 ? Money::discounted($cached, $previousPercent) : $cached;
        if ($current !== $cached && $current !== $discounted) {
            self::$baseByKey[$key] = $current;

            return $current;
        }

        return $cached;
    }
}
