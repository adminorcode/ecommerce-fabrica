<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class MercadoPagoReturn
{
    public const GATEWAY_ID = 'woo-mercado-pago-basic';
    public const SESSION_KEY = 'petshop_mp_return_order';
    public const QUERY_VAR = 'petshop_mp_return';
    public const TOKEN_QUERY_ARG = 'petshop_mp_token';

    private const RETURN_TTL = 7200;
    private const RETURN_TYPES = ['success', 'pending', 'failure'];

    private static bool $returnMarkedUncacheable = false;

    public static function bootstrap(): void
    {
        add_filter('woocommerce_payment_successful_result', [self::class, 'handlePaymentSuccessfulResult'], 10, 2);
        add_action('woocommerce_checkout_order_processed', [self::class, 'handleClassicCheckoutOrderProcessed'], 10, 3);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'handleStoreApiCheckoutOrderProcessed'], 10, 1);
        add_filter('query_vars', [self::class, 'registerQueryVar']);
        add_action('wp', [self::class, 'markUncacheableReturnRequest'], 0);
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

    public static function handleClassicCheckoutOrderProcessed(mixed $orderId, mixed $postedData = null, mixed $order = null): void
    {
        unset($postedData);
        if (!$order instanceof \WC_Order && function_exists('wc_get_order')) {
            $order = wc_get_order((int) $orderId);
        }
        if ($order instanceof \WC_Order) {
            self::prepareCheckoutReturn($order);
        }
    }

    public static function handleStoreApiCheckoutOrderProcessed(\WC_Order $order): void
    {
        self::prepareCheckoutReturn($order);
    }

    public static function prepareCheckoutReturn(\WC_Order $order): void
    {
        if (!self::armReturnSession($order)) {
            return;
        }

        $state = self::sessionState();
        $gateway = self::checkoutProGateway();
        if ($state === null || $gateway === null) {
            return;
        }

        self::applyPreferenceReturnSettings($gateway, $state['token']);
    }

    /**
     * Writes Checkout Pro back_urls and auto_return on the gateway instance the
     * official plugin reads while creating the preference. Nothing is persisted:
     * each payment gets its own token, and localhost is never sent to Mercado Pago.
     */
    public static function applyPreferenceReturnSettings(object $gateway, string $token): bool
    {
        if (!self::isReturnToken($token) || !property_exists($gateway, 'settings') || !is_array($gateway->settings)) {
            return false;
        }

        if ($gateway->settings === [] && method_exists($gateway, 'init_settings')) {
            $gateway->init_settings();
        }
        if (!is_array($gateway->settings)) {
            return false;
        }

        $successUrl = self::returnUrl('success', $token);
        if (!self::isPublicHttpsUrl($successUrl)) {
            return false;
        }

        $gateway->settings['auto_return'] = 'yes';
        $gateway->settings['success_url'] = $successUrl;
        $gateway->settings['pending_url'] = self::returnUrl('pending', $token);
        $gateway->settings['failure_url'] = self::returnUrl('failure', $token);

        return true;
    }

    /** @param array<int, string> $vars @return array<int, string> */
    public static function registerQueryVar(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return array_values(array_unique($vars));
    }

    public static function markUncacheableReturnRequest(): void
    {
        if (self::currentReturnType() === null) {
            return;
        }

        self::markReturnUncacheable();
    }

    public static function handleReturnRequest(): void
    {
        $type = self::currentReturnType();
        if ($type === null) {
            return;
        }

        self::markReturnUncacheable();
        $redirect = self::processReturn($type);
        wp_safe_redirect($redirect, 302, 'Petshop Mercado Pago return');
        exit;
    }

    public static function markReturnUncacheable(): void
    {
        if (self::$returnMarkedUncacheable) {
            return;
        }
        self::$returnMarkedUncacheable = true;

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        nocache_headers();
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        header('Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private');
        header('Vary: Cookie', false);
    }

    public static function returnUrl(string $type, string $token = ''): string
    {
        if (!in_array($type, self::RETURN_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid Mercado Pago return type.');
        }

        $url = add_query_arg(self::QUERY_VAR, $type, home_url('/'));
        if ($token === '') {
            return $url;
        }
        if (!self::isReturnToken($token)) {
            throw new \InvalidArgumentException('Invalid Mercado Pago return token.');
        }

        return add_query_arg(self::TOKEN_QUERY_ARG, $token, $url);
    }

    public static function processReturn(string $type): string
    {
        if (!in_array($type, self::RETURN_TYPES, true)) {
            return self::safeFallbackUrl();
        }

        $order = self::returnOrderFromSession();
        $redirect = ($order instanceof \WC_Order && self::requestTokenMatchesSession())
            ? self::redirectForOrder($order, $type)
            : self::safeFallbackUrl();

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
        $ambiguous = false;
        $token = bin2hex(random_bytes(16));
        if ($current !== null && !self::isExpired($current, $now)) {
            $ambiguous = $current['ambiguous'] || (int) $current['order_id'] !== $orderId;
            if (!$ambiguous) {
                $token = $current['token'];
            }
        }

        $session->set(self::SESSION_KEY, [
            'order_id' => $orderId,
            'created_at' => $now,
            'gateway' => self::GATEWAY_ID,
            'ambiguous' => $ambiguous,
            'token' => $token,
        ]);
        self::saveSession($session);

        return true;
    }

    /** @return array{order_id: int, created_at: int, gateway: string, ambiguous: bool, token: string}|null */
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
        $token = strtolower((string) ($state['token'] ?? ''));
        if ($orderId <= 0 || $createdAt <= 0 || $gateway !== self::GATEWAY_ID || !self::isReturnToken($token)) {
            return null;
        }

        return [
            'order_id' => $orderId,
            'created_at' => $createdAt,
            'gateway' => $gateway,
            'ambiguous' => (bool) ($state['ambiguous'] ?? false),
            'token' => $token,
        ];
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

    private static function currentReturnToken(): string
    {
        if (!isset($_GET[self::TOKEN_QUERY_ARG])) {
            return '';
        }

        $candidate = wp_unslash($_GET[self::TOKEN_QUERY_ARG]);
        if (!is_scalar($candidate)) {
            return '';
        }

        $token = strtolower(sanitize_text_field((string) $candidate));

        return self::isReturnToken($token) ? $token : '';
    }

    private static function requestTokenMatchesSession(): bool
    {
        $state = self::sessionState();
        $token = self::currentReturnToken();
        if ($state === null || !self::isReturnToken($token)) {
            return false;
        }

        return hash_equals($state['token'], $token);
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

    /** @param array{order_id: int, created_at: int, gateway: string, ambiguous: bool, token: string} $state */
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

    private static function checkoutProGateway(): ?object
    {
        if (!function_exists('WC')) {
            return null;
        }

        $woocommerce = WC();
        if (!is_object($woocommerce) || !method_exists($woocommerce, 'payment_gateways')) {
            return null;
        }

        $registry = $woocommerce->payment_gateways();
        if (!is_object($registry) || !method_exists($registry, 'payment_gateways')) {
            return null;
        }

        $gateways = $registry->payment_gateways();
        $gateway = is_array($gateways) ? ($gateways[self::GATEWAY_ID] ?? null) : null;

        return is_object($gateway) ? $gateway : null;
    }

    private static function isReturnToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $token) === 1;
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
}
