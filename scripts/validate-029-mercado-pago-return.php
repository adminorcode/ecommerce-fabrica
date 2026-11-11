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
if (has_action('woocommerce_checkout_order_processed', [MercadoPagoReturn::class, 'handleClassicCheckoutOrderProcessed']) === false) {
    $failures[] = 'Hook woocommerce_checkout_order_processed nao registrado.';
}
if (has_action('wp', [MercadoPagoReturn::class, 'markUncacheableReturnRequest']) === false) {
    $failures[] = 'Hook wp de no-cache do retorno nao registrado.';
}
if (has_filter('woocommerce_available_payment_gateways', [MercadoPagoReturn::class, 'filterAvailablePaymentGateways']) !== false) {
    $failures[] = 'Filtro que removia o gateway Mercado Pago ainda esta registrado.';
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

$candidateGateway = new class {
    /** @var array<string, string> */
    public array $settings = [
        'enabled' => 'yes',
        'auto_return' => 'no',
        'success_url' => '',
    ];
};
$returnToken = bin2hex(random_bytes(16));
$applied = MercadoPagoReturn::applyPreferenceReturnSettings($candidateGateway, $returnToken);
$homeHost = strtolower(trim((string) (wp_parse_url(home_url('/'), PHP_URL_HOST) ?: ''), '[]'));
$homeScheme = strtolower((string) (wp_parse_url(home_url('/'), PHP_URL_SCHEME) ?: ''));
$mustRefuseLocal = $homeScheme !== 'https' || in_array($homeHost, ['localhost', '127.0.0.1', '::1'], true);
if ($mustRefuseLocal && $applied) {
    $failures[] = 'Ambiente HTTP/local nao deveria gravar back_urls do Mercado Pago.';
}
if ($mustRefuseLocal && ($candidateGateway->settings['success_url'] ?? '') !== '') {
    $failures[] = 'Ambiente HTTP/local gravou success_url.';
}
if ($mustRefuseLocal && ($candidateGateway->settings['enabled'] ?? '') !== 'yes') {
    $failures[] = 'Ambiente HTTP/local alterou opcao alheia do gateway.';
}
if (!$mustRefuseLocal && !$applied) {
    $failures[] = 'Ambiente HTTPS publico deveria gravar back_urls e auto_return na instancia do gateway.';
}
if (!$mustRefuseLocal) {
    foreach ($expectedTypes as $type) {
        $configured = (string) ($candidateGateway->settings[$type . '_url'] ?? '');
        $query = [];
        wp_parse_str((string) (wp_parse_url($configured, PHP_URL_QUERY) ?: ''), $query);
        if (($query[MercadoPagoReturn::QUERY_VAR] ?? '') !== $type || ($query[MercadoPagoReturn::TOKEN_QUERY_ARG] ?? '') !== $returnToken) {
            $failures[] = "URL {$type} da preferencia nao ficou exclusiva deste pagamento.";
        }
    }
    if (($candidateGateway->settings['auto_return'] ?? '') !== 'yes') {
        $failures[] = 'auto_return da preferencia deveria ser yes.';
    }
    if (($candidateGateway->settings['enabled'] ?? '') !== 'yes') {
        $failures[] = 'Gravacao das back_urls removeu opcao alheia do gateway.';
    }
}

$moduleFile = WP_PLUGIN_DIR . '/petshop-core/includes/WooCommerce/MercadoPagoReturn.php';
$source = is_file($moduleFile) ? (string) file_get_contents($moduleFile) : '';
foreach (['nocache_headers()', 'DONOTCACHEPAGE', 'Vary: Cookie'] as $required) {
    if (!str_contains($source, $required)) {
        $failures[] = "Endpoint de retorno 029 nao impede cache compartilhado ({$required}).";
    }
}
foreach (['woocommerce_get_cancel_order_url', 'external_reference', 'payment_id', 'merchant_order_id', 'payment_complete(', 'unset($gateways'] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        $failures[] = "Padrao proibido encontrado no modulo 029: {$forbidden}";
    }
}

if ($failures !== []) {
    WP_CLI::error('Gate 029 falhou: ' . implode(' | ', $failures));
}

WP_CLI::success('Gate 029: retorno Mercado Pago registrado, back_urls gravadas so em HTTPS publico e resposta de retorno marcada como nao cacheavel.');
