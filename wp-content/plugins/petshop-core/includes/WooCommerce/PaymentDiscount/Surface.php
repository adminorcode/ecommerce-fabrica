<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce\PaymentDiscount;

defined('ABSPATH') || exit;

/**
 * The charged total includes the payment discount on cart and checkout.
 * The mini-cart still shows the full price and the payment price beside it.
 */
final class Surface
{
    public static function allowsCurrentRequest(): bool
    {
        if (self::isOrderReceived() || self::isPayPage()) {
            return false;
        }

        if (function_exists('is_cart') && is_cart()) {
            return true;
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return true;
        }

        $route = self::restRoute();
        if ($route === '' || !self::isStoreApi($route)) {
            return false;
        }
        if (str_contains($route, '/checkout')) {
            return true;
        }

        return self::isCartOrCheckoutUrl(self::referer());
    }

    private static function isOrderReceived(): bool
    {
        return function_exists('is_order_received_page') && is_order_received_page();
    }

    private static function isPayPage(): bool
    {
        return function_exists('is_checkout_pay_page') && is_checkout_pay_page();
    }

    private static function restRoute(): string
    {
        if (isset($GLOBALS['wp']) && is_object($GLOBALS['wp']) && isset($GLOBALS['wp']->query_vars['rest_route'])) {
            return (string) $GLOBALS['wp']->query_vars['rest_route'];
        }

        // Only the REST query var is reliable. REQUEST_URI includes the query
        // string and can false-match Store API path fragments on storefront URLs.
        return '';
    }

    private static function isStoreApi(string $route): bool
    {
        return str_contains($route, '/wc/store/') || str_contains($route, 'wc/store/');
    }

    private static function referer(): string
    {
        return isset($_SERVER['HTTP_REFERER']) && is_string($_SERVER['HTTP_REFERER'])
            ? $_SERVER['HTTP_REFERER']
            : '';
    }

    private static function isCartOrCheckoutUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        if ($path === '') {
            return false;
        }

        $cart = function_exists('wc_get_cart_url') ? (string) wp_parse_url(wc_get_cart_url(), PHP_URL_PATH) : '';
        $checkout = function_exists('wc_get_checkout_url') ? (string) wp_parse_url(wc_get_checkout_url(), PHP_URL_PATH) : '';
        $path = untrailingslashit($path);

        foreach ([$cart, $checkout] as $candidate) {
            $candidate = untrailingslashit($candidate);
            if ($candidate !== '' && ($path === $candidate || str_starts_with($path, $candidate . '/'))) {
                return true;
            }
        }

        return false;
    }
}
