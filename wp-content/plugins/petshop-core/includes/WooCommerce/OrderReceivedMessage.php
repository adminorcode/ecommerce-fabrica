<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

use Petshop\Core\Settings\DefaultSettings;

defined('ABSPATH') || exit;

final class OrderReceivedMessage
{
    public const SETTING = 'petshop_order_received_text';

    public static function bootstrap(): void
    {
        add_filter('woocommerce_thankyou_order_received_text', [self::class, 'filterReceivedText'], 20, 2);
    }

    public static function sanitize(mixed $value): string
    {
        $raw = is_scalar($value) ? (string) $value : '';
        $text = function_exists('sanitize_text_field')
            ? sanitize_text_field($raw)
            : trim(strip_tags($raw));

        return $text === '' ? self::defaultText() : $text;
    }

    public static function text(): string
    {
        $stored = get_theme_mod(self::SETTING, null);
        if (!is_string($stored) || trim($stored) === '') {
            return self::defaultText();
        }

        return trim($stored);
    }

    public static function defaultText(): string
    {
        return trim((string) DefaultSettings::get(self::SETTING));
    }

    public static function filterReceivedText(mixed $text, mixed $order = null): mixed
    {
        unset($order);
        if (!is_string($text) || !self::isNativeReceivedMessage($text)) {
            return $text;
        }

        $replacement = self::text();

        return function_exists('esc_html') ? esc_html($replacement) : $replacement;
    }

    public static function isNativeReceivedMessage(string $text): bool
    {
        $normalized = self::normalize($text);
        foreach (self::nativeReceivedMessages() as $native) {
            if ($normalized === self::normalize($native)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function nativeReceivedMessages(): array
    {
        $messages = [
            'Thank you. Your order has been received.',
            'Obrigado. Seu pedido foi recebido.',
        ];
        if (function_exists('__')) {
            $messages[] = __('Thank you. Your order has been received.', 'woocommerce');
        }

        return $messages;
    }

    private static function normalize(string $text): string
    {
        $stripped = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($text) : strip_tags($text);
        $stripped = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $collapsed = preg_replace('/\s+/u', ' ', $stripped);

        return trim(is_string($collapsed) ? $collapsed : $stripped);
    }
}
