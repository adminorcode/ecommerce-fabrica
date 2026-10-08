<?php

use Petshop\Core\WooCommerce\BrazilianPostcode;
use Petshop\Core\WooCommerce\ShippingQuoteDestination;

defined('ABSPATH') || exit;

$failures = [];
$plugin = plugin_dir_path(PETSHOP_CORE_FILE);
$read = static fn (string $relative): string => (string) file_get_contents($plugin . $relative);
$fail = static function (bool $condition, string $message) use (&$failures): void {
    if ($condition) {
        $failures[] = $message;
    }
};

$extension = $read('includes/WooCommerce/ShippingQuoteCartExtension.php');
$destination = $read('includes/WooCommerce/ShippingQuoteDestination.php');
$quotes = $read('includes/WooCommerce/ShippingQuotes.php');
$addressLookup = $read('assets/js/address-lookup.js');

$fail(!str_contains($extension, 'woocommerce_store_api_register_update_callback'), 'Callback público Store API de cotação não registrado.');
$fail(!str_contains($extension, "'namespace' => self::NAMESPACE"), 'Namespace da extensão de cotação ausente.');
$fail(str_contains($extension, 'set_meta_data') || str_contains($extension, '->save()'), 'Origem da cotação não pode gravar metadados permanentes do cliente.');
$fail(str_contains($destination, "'city'"), 'Destino de cotação não pode fabricar cidade.');
$fail(str_contains($quotes, 'persistPostcode'), 'Prévia da PDP não pode persistir CEP no cliente ou sessão.');
$fail(str_contains($addressLookup, 'receiveCart'), 'ViaCEP não pode aplicar carrinho manualmente.');
$fail(str_contains($addressLookup, 'update-customer'), 'ViaCEP não pode postar um update-customer paralelo.');
$fail(is_file($plugin . 'assets/js/cart-quantity-guard.js'), 'Guard de quantidade legado ainda está disponível.');
$fail(is_file($plugin . 'assets/js/cart-quantity-stability.js'), 'Estabilidade de quantidade legada ainda está disponível.');
$fail(BrazilianPostcode::normalize('01310-100') !== '01310100', 'Normalização de CEP SP inválida.');
$fail(BrazilianPostcode::stateFromPostcode('91210-320') !== 'RS', 'Derivação de UF RS inválida.');
$fail(ShippingQuoteDestination::forPostcode('01310-100') !== ['country' => 'BR', 'state' => 'SP', 'postcode' => '01310100'], 'Destino normalizado SP inválido.');

if ($failures !== []) {
    fwrite(STDERR, "Plano 041 — destino de frete: FALHOU\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Plano 041 — destino de frete: OK\n");
