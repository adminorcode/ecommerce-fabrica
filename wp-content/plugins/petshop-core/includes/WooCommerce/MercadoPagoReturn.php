<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class MercadoPagoReturn
{
    public const GATEWAY_ID = 'woo-mercado-pago-basic';
    public const SESSION_KEY = 'petshop_mp_return_order';
    public const QUERY_VAR = 'petshop_mp_return';

    private const RETURN_TTL = 7200;
    private const RETURN_TYPES = ['success', 'pending', 'failure'];

    public static function bootstrap(): void
    {
        add_filter('woocommerce_payment_successful_result', [self::class, 'handlePaymentSuccessfulResult'], 10, 2);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'handleStoreApiCheckoutOrderProcessed'], 10, 1);
        add_filter('woocommerce_available_payment_gateways', [self::class, 'filterAvailablePaymentGateways'], 20, 1);
        add_filter('query_vars', [self::class, 'registerQueryVar']);
        add_action('template_redirect', [self::class, 'handleReturnRequest'], 0);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public static function handlePaymentSuccessfulResult(array $result, int $orderId): array
    {
        if (($result['result'] ?? '') !== 'success' || !function_exists('wc_get_order')) {
            return $result;
        }

        $order = wc_get_order($orderId);
        if ($order instanceof \WC_Order) {
            self::armReturnSession($order);
        }

        return $result;
    }

    public static function handleStoreApiCheckoutOrderProcessed(\WC_Order $order): void
    {
        self::armReturnSession($order);
    }

    /** @param array<string, mixed> $gateways @return array<string, mixed> */
    public static function filterAvailablePaymentGateways(array $gateways): array
    {
        if (!array_key_exists(self::GATEWAY_ID, $gateways)) {
            return $gateways;
        }

        $gateway = $gateways[self::GATEWAY_ID];
        if (!is_object($gateway) || !method_exists($gateway, 'get_option') || !self::hasExpectedGatewaySettings($gateway)) {
            unset($gateways[self::GATEWAY_ID]);
        }

        return $gateways;
    }

    /** @param array<int, string> $vars @return array<int, string> */
    public static function registerQueryVar(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return array_values(array_unique($vars));
    }

    public static function handleReturnRequest(): void
    {
        $type = self::currentReturnType();
        if ($type === null) {
            return;
        }

        $redirect = self::processReturn($type);
        nocache_headers();
        wp_safe_redirect($redirect, 302, 'Petshop Mercado Pago return');
        exit;
    }

    public static function returnUrl(string $type): string
    {
        if (!in_array($type, self::RETURN_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid Mercado Pago return type.');
        }

        return add_query_arg(self::QUERY_VAR, $type, home_url('/'));
    }

    public static function processReturn(string $type): string
    {
        if (!in_array($type, self::RETURN_TYPES, true)) {
            return self::safeFallbackUrl();
        }

        $order = self::returnOrderFromSession();
        $redirect = $order instanceof \WC_Order ? self::redirectForOrder($order, $type) : self::safeFallbackUrl();

        self::clearReturnSession();

        return $redirect;
    }

    public static function armReturnSession(\WC_Order $order): bool
    {
        if ($order->get_payment_method() !== self::GATEWAY_ID) {
            return false;
        }

        $session = self::session();
        if (!is_object($session) || !method_exists($session, 'set')) {
            return false;
        }

        if (method_exists($session, 'set_customer_session_cookie')) {
            $session->set_customer_session_cookie(true);
        }

        $orderId = (int) $order->get_id();
        $now = time();
        $current = self::sessionState();
        $state = [
            'order_id' => $orderId,
            'created_at' => $now,
            'gateway' => self::GATEWAY_ID,
            'ambiguous' => false,
        ];

        if ($current !== null && !self::isExpired($current, $now)) {
            $state['ambiguous'] = $current['ambiguous'] || (int) $current['order_id'] !== $orderId;
        }

        $session->set(self::SESSION_KEY, $state);
        self::saveSession($session);

        return true;
    }

    /** @return array{order_id: int, created_at: int, gateway: string, ambiguous: bool}|null */
    public static function sessionState(): ?array
    {
        $session = self::session();
        if (!is_object($session) || !method_exists($session, 'get')) {
            return null;
        }

        $state = $session->get(self::SESSION_KEY);
        if (!is_array($state)) {
            return null;
        }

        $orderId = (int) ($state['order_id'] ?? 0);
        $createdAt = (int) ($state['created_at'] ?? 0);
        $gateway = (string) ($state['gateway'] ?? '');
        if ($orderId <= 0 || $createdAt <= 0 || $gateway !== self::GATEWAY_ID) {
            return null;
        }

        return [
            'order_id' => $orderId,
            'created_at' => $createdAt,
            'gateway' => $gateway,
            'ambiguous' => (bool) ($state['ambiguous'] ?? false),
        ];
    }

    /** @param object $gateway */
    private static function hasExpectedGatewaySettings(object $gateway): bool
    {
        if ((string) $gateway->get_option('auto_return') !== 'yes') {
            return false;
        }

        foreach (self::RETURN_TYPES as $type) {
            $option = $type . '_url';
            $actual = (string) $gateway->get_option($option);
            $expected = self::returnUrl($type);
            if (!self::isPublicHttpsUrl($actual) || self::normalizeUrl($actual) !== self::normalizeUrl($expected)) {
                return false;
            }
        }

        return true;
    }

    private static function currentReturnType(): ?string
    {
        $raw = '';
        if (function_exists('get_query_var')) {
            $candidate = get_query_var(self::QUERY_VAR, '');
            if (!is_scalar($candidate)) {
                return null;
            }
            $raw = (string) $candidate;
        }
        if ($raw === '' && isset($_GET[self::QUERY_VAR])) {
            $candidate = wp_unslash($_GET[self::QUERY_VAR]);
            if (!is_scalar($candidate)) {
                return null;
            }
            $raw = (string) $candidate;
        }

        $type = sanitize_key($raw);

        return in_array($type, self::RETURN_TYPES, true) ? $type : null;
    }

    private static function returnOrderFromSession(): ?\WC_Order
    {
        $state = self::sessionState();
        if ($state === null || $state['ambiguous'] || self::isExpired($state, time()) || !function_exists('wc_get_order')) {
            return null;
        }

        $order = wc_get_order($state['order_id']);
        if (!$order instanceof \WC_Order || $order->get_payment_method() !== self::GATEWAY_ID) {
            return null;
        }

        return $order;
    }

    private static function redirectForOrder(\WC_Order $order, string $type): string
    {
        if ($type === 'failure') {
            if ($order->needs_payment()) {
                return $order->get_checkout_payment_url();
            }

            return is_user_logged_in() ? self::ordersUrl() : $order->get_checkout_order_received_url();
        }

        return is_user_logged_in() ? self::ordersUrl() : $order->get_checkout_order_received_url();
    }

    /** @param array{order_id: int, created_at: int, gateway: string, ambiguous: bool} $state */
    private static function isExpired(array $state, int $now): bool
    {
        return $state['created_at'] + self::RETURN_TTL < $now;
    }

    private static function safeFallbackUrl(): string
    {
        return is_user_logged_in() ? self::ordersUrl() : home_url('/');
    }

    private static function ordersUrl(): string
    {
        return wc_get_account_endpoint_url('orders');
    }

    private static function clearReturnSession(): void
    {
        $session = self::session();
        if (!is_object($session) || !method_exists($session, 'set')) {
            return;
        }

        $session->set(self::SESSION_KEY, null);
        self::saveSession($session);
    }

    private static function saveSession(object $session): void
    {
        if (method_exists($session, 'save_data')) {
            $session->save_data();
        }
    }

    private static function session(): ?object
    {
        if (!function_exists('WC')) {
            return null;
        }

        $woocommerce = WC();

        return is_object($woocommerce) && isset($woocommerce->session) ? $woocommerce->session : null;
    }

    private static function isPublicHttpsUrl(string $url): bool
    {
        $parts = wp_parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        return true;
    }

    private static function normalizeUrl(string $url): string
    {
        $parts = wp_parse_url(trim($url));
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? ':' . (string) $parts['port'] : '';
        $path = (string) ($parts['path'] ?? '/');
        $query = (string) ($parts['query'] ?? '');

        return $scheme . '://' . $host . $port . $path . ($query !== '' ? '?' . $query : '');
    }
}
