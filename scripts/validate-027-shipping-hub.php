<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce.');
}

use Petshop\Core\StorefrontCatalog;
use Petshop\Core\WooCommerce\ShippingQuotes;

$failures = [];
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

StorefrontCatalog::maybeEnsureCategories();

$productId = (int) wc_get_product_id_by_sku('PLAN013-SIMPLE');
$product = $productId > 0 ? wc_get_product($productId) : null;
if (!$product instanceof WC_Product) {
    WP_CLI::error('Gate 027 falhou: produto fixture PLAN013-SIMPLE ausente.');
}

$postcode = '94010450';
$originalCustomer = WC()->customer instanceof WC_Customer ? WC()->customer : null;
$billingPostcodeBefore = $originalCustomer instanceof WC_Customer ? $originalCustomer->get_billing_postcode() : '';
$billingCountryBefore = $originalCustomer instanceof WC_Customer ? $originalCustomer->get_billing_country() : '';
$labels = [
    'Virtuaria PAC',
    'Melhor Envio Correios SEDEX',
    'Melhor Envio Jadlog Package',
    'Retirada local parceira',
];

$injectRates = static function (array $rates) use ($labels): array {
    foreach ($labels as $index => $label) {
        $rate = new WC_Shipping_Rate('petshop_gate_027_' . $index, $label, 27 + $index, [], 'petshop_gate_027');
        $rate->add_meta_data('Prazo', ($index + 2) . ' dias uteis');
        $rates[$rate->get_id()] = $rate;
    }
    return $rates;
};
add_filter('woocommerce_package_rates', $injectRates, 1000);

try {
    $quotes = ShippingQuotes::quote($product, $postcode);
} finally {
    remove_filter('woocommerce_package_rates', $injectRates, 1000);
}

$rateLabels = array_column($quotes['rates'], 'label');
foreach ($labels as $label) {
    if (!in_array($label, $rateLabels, true)) {
        $failures[] = 'Taxa ativa removida indevidamente: ' . $label;
    }
}

foreach ($quotes['rates'] as $rate) {
    $costText = (string) ($rate['costText'] ?? '');
    $line = ((string) ($rate['label'] ?? '')) . ': ' . $costText;
    if (preg_match('/&#\d+;|&#|&nbsp;/', $line) === 1) {
        $failures[] = 'Preco contem entidade HTML: ' . $line;
    }
    if (str_contains((string) ($rate['id'] ?? ''), 'petshop_gate_027') && !str_contains($costText, 'R$')) {
        $failures[] = 'Preco BRL sem R$: ' . $line;
    }
    if (str_contains((string) ($rate['id'] ?? ''), 'petshop_gate_027') && trim((string) ($rate['deliveryEstimate'] ?? '')) === '') {
        $failures[] = 'Prazo nao serializado para: ' . (string) ($rate['label'] ?? '');
    }
}

if (WC()->customer instanceof WC_Customer) {
    if (WC()->customer->get_shipping_postcode() !== $postcode || WC()->customer->get_shipping_country() !== 'BR') {
        $failures[] = 'CEP calculado na PDP nao persistiu no cliente WooCommerce.';
    }
    if (WC()->customer->get_billing_postcode() !== $billingPostcodeBefore || WC()->customer->get_billing_country() !== $billingCountryBefore) {
        $failures[] = 'Simulacao de frete alterou dados de cobranca do cliente.';
    }
} else {
    $failures[] = 'WC()->customer indisponivel para validar persistencia de CEP.';
}

$userId = wp_insert_user([
    'user_login' => 'gate027_' . wp_generate_password(8, false),
    'user_pass' => wp_generate_password(16, true),
    'user_email' => 'gate027-' . wp_generate_password(8, false) . '@example.test',
]);
if (is_wp_error($userId)) {
    $failures[] = 'Nao foi possivel criar usuario temporario para validar user meta.';
} else {
    update_user_meta((int) $userId, 'shipping_postcode', '11111111');
    update_user_meta((int) $userId, 'billing_postcode', '22222222');
    $loggedCustomer = new WC_Customer((int) $userId);
    WC()->customer = $loggedCustomer;
    ShippingQuotes::quote($product, $postcode);
    if (get_user_meta((int) $userId, 'shipping_postcode', true) !== '11111111' || get_user_meta((int) $userId, 'billing_postcode', true) !== '22222222') {
        $failures[] = 'Simulacao de frete salvou CEP no cadastro do usuario logado.';
    }
    wp_delete_user((int) $userId);
    if ($originalCustomer instanceof WC_Customer) {
        WC()->customer = $originalCustomer;
    }
}

$melhorEnvioActive = is_plugin_active('melhor-envio-cotacao/melhor-envio-beta.php');

$uploads = wp_upload_dir();
$gateDir = rtrim((string) ($uploads['basedir'] ?? ''), '/\\') . '/petshop-gates';
if (!is_dir($gateDir) && !wp_mkdir_p($gateDir)) {
    $failures[] = 'Nao foi possivel gravar o fixture do gate browser 027.';
} else {
    $written = file_put_contents(
        $gateDir . '/027.json',
        wp_json_encode([
            'productPath' => (string) wp_parse_url((string) get_permalink($product->get_id()), PHP_URL_PATH),
            'postcode' => $postcode,
            'labels' => $labels,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    if ($written === false) {
        $failures[] = 'Falha ao escrever uploads/petshop-gates/027.json.';
    }
}

if ($failures !== []) {
    WP_CLI::error('Gate 027 falhou: ' . implode(' | ', $failures));
}

WP_CLI::success('Gate 027: hub WooCommerce preserva taxas ativas, preco sem entidade HTML, prazo e CEP persistente.');
if (!$melhorEnvioActive) {
    WP_CLI::warning('Plugin melhor-envio-cotacao nao esta ativo neste runtime; instale/ative no painel ou via WP-CLI para validar servicos reais do Melhor Envio.');
}
