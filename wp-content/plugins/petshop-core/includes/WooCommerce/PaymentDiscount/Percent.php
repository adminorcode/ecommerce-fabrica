<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce\PaymentDiscount;

defined('ABSPATH') || exit;

/**
 * Payment-method percentage stored on a product or variation.
 */
final class Percent
{
    /**
     * @return array{status: string, value: string}
     */
    public static function parse(mixed $raw): array
    {
        if (!is_scalar($raw)) {
            return ['status' => 'invalid', 'value' => ''];
        }

        $text = str_replace(' ', '', trim((string) $raw));
        if ($text === '') {
            return ['status' => 'empty', 'value' => ''];
        }

        $text = str_replace(',', '.', $text);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $text)) {
            return ['status' => 'invalid', 'value' => ''];
        }

        $value = (float) $text;
        if ($value < 0 || $value > 100) {
            return ['status' => 'invalid', 'value' => ''];
        }

        return ['status' => 'value', 'value' => number_format($value, 2, '.', '')];
    }

    public static function normalizeStored(mixed $raw): string
    {
        $parsed = self::parse(is_scalar($raw) ? (string) $raw : '');

        return $parsed['status'] === 'value' ? $parsed['value'] : '0.00';
    }

    /**
     * A variation above zero replaces the parent. Empty or zero inherits the parent.
     */
    public static function effective(string $own, string $parent): string
    {
        $ownValue = self::normalizeStored($own);
        if ((float) $ownValue > 0) {
            return $ownValue;
        }

        $parentValue = self::normalizeStored($parent);
        if ((float) $parentValue > 0) {
            return $parentValue;
        }

        return '0.00';
    }
}
