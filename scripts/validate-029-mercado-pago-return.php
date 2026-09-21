<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce.');
}

use Petshop\Core\WooCommerce\MercadoPagoReturn;

$failures = [];

if (!class_exists(MercadoPagoReturn::class)) {
    $failures[] = 'Modulo MercadoPagoReturn nao carregou.';
}

if (has_filter('woocommerce_payment_successful_result', [MercadoPagoReturn::class, 'handlePaymentSuccessfulResult']) === false) {
    $failures[] = 'Filtro woocommerce_payment_successful_result nao registrado.';
}
if (has_action('woocommerce_store_api_checkout_order_processed', [MercadoPagoReturn::class, 'handleStoreApiCheckoutOrderProcessed']) === false) {
    $failures[] = 'Hook woocommerce_store_api_checkout_order_processed nao registrado.';
}
if (has_filter('woocommerce_available_payment_gateways', [MercadoPagoReturn::class, 'filterAvailablePaymentGateways']) === false) {
    $failures[] = 'Filtro woocommerce_available_payment_gateways nao registrado.';
}
if (has_filter('query_vars', [MercadoPagoReturn::class, 'registerQueryVar']) === false) {
    $failures[] = 'Filtro query_vars nao registrado.';
}
if (has_action('template_redirect', [MercadoPagoReturn::class, 'handleReturnRequest']) === false) {
    $failures[] = 'Hook template_redirect nao registrado.';
}

$expectedTypes = ['success', 'pending', 'failure'];
foreach ($expectedTypes as $type) {
    $url = MercadoPagoReturn::returnUrl($type);
    $query = [];
    wp_parse_str((string) (wp_parse_url($url, PHP_URL_QUERY) ?: ''), $query);
    if (($query[MercadoPagoReturn::QUERY_VAR] ?? '') !== $type) {
        $failures[] = "URL {$type} nao contem query var namespaced correta.";
    }
}

$withoutMercadoPago = [
    'cod' => new class {
    },
];
if (MercadoPagoReturn::filterAvailablePaymentGateways($withoutMercadoPago) !== $withoutMercadoPago) {
    $failures[] = 'Gateway ausente do Mercado Pago deveria deixar demais gateways intactos.';
}

$candidateGateway = new class($expectedTypes) {
    /** @param array<int, string> $types */
    public function __construct(private readonly array $types)
    {
    }

    public function get_option(string $key): string
    {
        if ($key === 'auto_return') {
            return 'yes';
        }

        foreach ($this->types as $type) {
            if ($key === $type . '_url') {
                return MercadoPagoReturn::returnUrl($type);
            }
        }

        return '';
    }
};

$filtered = MercadoPagoReturn::filterAvailablePaymentGateways([
    MercadoPagoReturn::GATEWAY_ID => $candidateGateway,
    'cod' => new class {
    },
]);
$homeHost = strtolower(trim((string) (wp_parse_url(home_url('/'), PHP_URL_HOST) ?: ''), '[]'));
$homeScheme = strtolower((string) (wp_parse_url(home_url('/'), PHP_URL_SCHEME) ?: ''));
$mustFailClosed = $homeScheme !== 'https' || in_array($homeHost, ['localhost', '127.0.0.1', '::1'], true);
if ($mustFailClosed && array_key_exists(MercadoPagoReturn::GATEWAY_ID, $filtered)) {
    $failures[] = 'Ambiente HTTP/local deveria remover o gateway Mercado Pago.';
}
if (!$mustFailClosed && !array_key_exists(MercadoPagoReturn::GATEWAY_ID, $filtered)) {
    $failures[] = 'Ambiente HTTPS publico com settings exatos deveria preservar o gateway Mercado Pago.';
}
if (!array_key_exists('cod', $filtered)) {
    $failures[] = 'Guard removeu gateway nao relacionado.';
}

$moduleFile = WP_PLUGIN_DIR . '/petshop-core/includes/WooCommerce/MercadoPagoReturn.php';
$source = is_file($moduleFile) ? (string) file_get_contents($moduleFile) : '';
if (!str_contains($source, 'nocache_headers()')) {
    $failures[] = 'Endpoint de retorno 029 nao envia headers no-cache.';
}
foreach (['woocommerce_get_cancel_order_url', 'external_reference', 'payment_id', 'merchant_order_id', 'payment_complete('] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        $failures[] = "Padrao proibido encontrado no modulo 029: {$forbidden}";
    }
}

if ($failures !== []) {
    WP_CLI::error('Gate 029 falhou: ' . implode(' | ', $failures));
}

WP_CLI::success('Gate 029: modulo de retorno Mercado Pago registrado, endpoints determinísticos e guard fail-closed verificados sem plugin Mercado Pago local.');
