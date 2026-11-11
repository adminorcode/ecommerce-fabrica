<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce\PaymentDiscount;

defined('ABSPATH') || exit;

/**
 * Price math in minor currency units, half-up, at WooCommerce decimal precision.
 */
final class Money
{
    public static function decimals(): int
    {
        if (function_exists('wc_get_price_decimals')) {
            return max(0, (int) wc_get_price_decimals());
        }

        return 2;
    }

    public static function factor(): int
    {
        return 10 ** self::decimals();
    }

    public static function normalize(string $amount): string
    {
        $amount = trim(str_replace(',', '.', $amount));
        if ($amount === '' || !is_numeric($amount)) {
            return self::fromMinor(0);
        }

        return self::fromMinor(self::toMinor($amount));
    }

    public static function toMinor(string $amount): int
    {
        $amount = trim(str_replace(',', '.', $amount));
        if ($amount === '' || !is_numeric($amount)) {
            return 0;
        }

        return (int) round(((float) $amount) * self::factor(), 0, PHP_ROUND_HALF_UP);
    }

    public static function fromMinor(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);
        $factor = self::factor();
        $whole = intdiv($minor, $factor);
        $fraction = $minor % $factor;

        return $sign . $whole . '.' . str_pad((string) $fraction, self::decimals(), '0', STR_PAD_LEFT);
    }

    public static function basisPoints(string $percent): int
    {
        $parsed = Percent::parse($percent);
        if ($parsed['status'] !== 'value') {
            return 0;
        }

        return (int) round(((float) $parsed['value']) * 100, 0, PHP_ROUND_HALF_UP);
    }

    public static function discounted(string $amount, string $percent): string
    {
        $basis = self::basisPoints($percent);
        $minor = self::toMinor($amount);
        if ($basis <= 0) {
            return self::fromMinor($minor);
        }
        if ($basis >= 10000) {
            return self::fromMinor(0);
        }

        $result = (int) round($minor * (10000 - $basis) / 10000, 0, PHP_ROUND_HALF_UP);

        return self::fromMinor($result);
    }

    public static function discountAmount(string $amount, string $percent): string
    {
        return self::fromMinor(self::toMinor($amount) - self::toMinor(self::discounted($amount, $percent)));
    }

    public static function add(string $left, string $right): string
    {
        return self::fromMinor(self::toMinor($left) + self::toMinor($right));
    }

    public static function multiply(string $amount, int $quantity): string
    {
        $quantity = max(0, $quantity);

        return self::fromMinor(self::toMinor($amount) * $quantity);
    }
}
