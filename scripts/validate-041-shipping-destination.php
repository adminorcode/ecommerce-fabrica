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
$blocksIntegration = $read('includes/WooCommerce/CartBlocksIntegration.php');
$assetsIntegration = $read('includes/WooCommerce/CartShippingQuoteBlocksIntegration.php');
$destination = $read('includes/WooCommerce/ShippingQuoteDestination.php');
$quotes = $read('includes/WooCommerce/ShippingQuotes.php');
$addressLookup = $read('assets/js/address-lookup.js');

$fail(!str_contains($extension, 'woocommerce_store_api_register_update_callback'), 'Callback público Store API de cotação não registrado.');
$fail(!str_contains($extension, "'namespace' => self::NAMESPACE"), 'Namespace da extensão de cotação ausente.');
$fail(!str_contains($blocksIntegration, 'woocommerce_blocks_cart_block_registration'), 'Integração do Cart Block não foi registrada no hook oficial.');
$fail(!str_contains($assetsIntegration, 'implements IntegrationInterface'), 'Assets do Cart Block não implementam a interface oficial do WooCommerce.');
$fail(!str_contains($assetsIntegration, "'wc-blocks-checkout'"), 'Assets do Cart Block não declaram a dependência pública wc-blocks-checkout.');
$fail(str_contains($extension, 'set_meta_data') || str_contains($extension, '->save()'), 'Origem da cotação não pode gravar metadados permanentes do cliente.');
$fail(str_contains($destination, "'city'"), 'Destino de cotação não pode fabricar cidade.');
$fail(str_contains($quotes, 'persistPostcode'), 'Prévia da PDP não pode persistir CEP no cliente ou sessão.');
$fail(str_contains($addressLookup, 'receiveCart'), 'ViaCEP não pode aplicar carrinho manualmente.');
$fail(str_contains($addressLookup, 'update-customer'), 'ViaCEP não pode postar um update-customer paralelo.');
$fail(!str_contains($addressLookup, 'consumeQuotePreferenceAtCheckout') || !str_contains($addressLookup, 'setQuoteDestination'), 'Checkout direto deve consumir a preferência da PDP pelo adaptador público.');
$fakeAutofill = 'https://example.test/wp-content/plugins/virtuaria-correios/public/js/autofill.min.js';
wp_register_script('virtuaria-correios-autofill', $fakeAutofill);
wp_enqueue_script('virtuaria-correios-autofill');
$checkoutContext = static fn (): bool => true;
add_filter('woocommerce_is_checkout', $checkoutContext);
\Petshop\Core\WooCommerce\AddressLookup::disableDuplicateAutofill();
remove_filter('woocommerce_is_checkout', $checkoutContext);
$fail(wp_script_is('virtuaria-correios-autofill', 'enqueued'), 'Autofill concorrente não removido no checkout.');
$fail(is_file($plugin . 'assets/js/cart-quantity-guard.js'), 'Guard de quantidade legado ainda está disponível.');
$fail(is_file($plugin . 'assets/js/cart-quantity-stability.js'), 'Estabilidade de quantidade legada ainda está disponível.');
$fail(BrazilianPostcode::normalize('01310-100') !== '01310100', 'Normalização de CEP SP inválida.');
$fail(BrazilianPostcode::stateFromPostcode('91210-320') !== 'RS', 'Derivação de UF RS inválida.');
$fail(ShippingQuoteDestination::forPostcode('01310-100') !== ['country' => 'BR', 'state' => 'SP', 'postcode' => '01310100'], 'Destino normalizado SP inválido.');

$originalCustomer = WC()->customer;
$originalSession = WC()->session;
$originalUser = get_current_user_id();
$userId = 0;
try {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $userId = wp_insert_user(['user_login' => 'gate041-' . wp_generate_password(12, false), 'user_pass' => wp_generate_password(32), 'user_email' => 'gate041-' . wp_generate_password(12, false) . '@example.test']);
    if (is_wp_error($userId)) throw new RuntimeException('Unable to create isolated customer');
    $account = new WC_Customer($userId);
    $account->set_shipping_country('BR'); $account->set_shipping_state('SP');
    $account->set_shipping_postcode('01310100'); $account->set_shipping_city('Sao Paulo');
    $account->set_shipping_address_1('Synthetic street SP'); $account->set_shipping_address_2('Synthetic complement');
    $account->update_meta_data('shipping_neighborhood', 'Synthetic SP');
    $account->save();
    wp_set_current_user($userId);
    WC()->session = new class extends WC_Session {};
    WC()->customer = new WC_Customer($userId, true);
    \Petshop\Core\WooCommerce\ShippingQuoteCartExtension::updateDestination(['action' => 'set_quote_destination', 'postcode' => '01310100']);
    $fail(WC()->customer->get_shipping_address_1() !== 'Synthetic street SP', 'Recalcular o mesmo CEP apagou endereço real.');
    \Petshop\Core\WooCommerce\ShippingQuoteCartExtension::updateDestination(['action' => 'set_quote_destination', 'postcode' => '91210320']);
    $fail(WC()->customer->get_shipping_city() !== '' || WC()->customer->get_shipping_address_1() !== '', 'CEP novo mantém geografia anterior.');
    WC()->customer = new WC_Customer($userId, true);
    do_action('woocommerce_load_cart_from_session');
    $contactRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    $contactRequest->set_param('shipping_address', ['phone' => '11999990000']);
    \Petshop\Core\WooCommerce\ShippingQuoteCartExtension::markAddressIntent(WC()->customer, $contactRequest);
    $fail(WC()->session->get('petshop_shipping_destination_origin') !== 'quote', 'Contato isolado encerrou invalidação de geografia.');
    \Petshop\Core\WooCommerce\CheckoutCustomerData::hydrateCurrentCustomer();
    $fail(WC()->customer->get_shipping_postcode() !== '91210320' || WC()->customer->get_shipping_state() !== 'RS'
        || WC()->customer->get_shipping_city() !== '' || WC()->customer->get_shipping_address_1() !== '' || WC()->customer->get_shipping_address_2() !== '', 'Reload autenticado recriou endereço híbrido.');
    $fail(\Petshop\Core\WooCommerce\CheckoutCustomerData::defaultNeighborhood('', 'shipping', WC()->customer) !== '', 'Cotação recuperou bairro da conta.');
    $fail((new WC_Customer($userId))->get_shipping_postcode() !== '01310100', 'Cotação alterou cadastro permanente.');
    WC()->customer->set_shipping_address_1('Synthetic manual RS');
    $manualRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    $manualRequest->set_param('shipping_address', ['address_1' => 'Synthetic manual RS', 'city' => '']);
    \Petshop\Core\WooCommerce\ShippingQuoteCartExtension::markAddressIntent(WC()->customer, $manualRequest);
    WC()->customer = new WC_Customer($userId, true);
    do_action('woocommerce_load_cart_from_session');
    $fail(WC()->customer->get_shipping_city() !== '' || WC()->customer->get_shipping_address_1() !== 'Synthetic manual RS'
        || WC()->customer->get_shipping_postcode() !== '91210320', 'Reload sobrescreveu endereço manual parcial.');
    foreach (['state', 'postcode', 'city', 'address_1', 'address_2'] as $field) {
        $setter = 'set_shipping_' . $field;
        WC()->customer->{$setter}('');
    }
    $manualRequest->set_param('shipping_address', ['state' => '', 'postcode' => '', 'city' => '', 'address_1' => '', 'address_2' => '']);
    \Petshop\Core\WooCommerce\ShippingQuoteCartExtension::markAddressIntent(WC()->customer, $manualRequest);
    WC()->customer = new WC_Customer($userId, true);
    do_action('woocommerce_load_cart_from_session');
    $fail(WC()->customer->get_shipping_postcode() !== '' || WC()->customer->get_shipping_address_1() !== '', 'Reload desfez limpeza explícita do endereço.');
    try {
        \Petshop\Core\WooCommerce\ShippingQuoteCartExtension::updateDestination(['action' => 'unknown', 'postcode' => '91210320']);
        $fail(true, 'Ação desconhecida aceita.');
    } catch (WC_REST_Exception $error) {
        $fail($error->getCode() !== 400, 'Ação desconhecida deveria receber erro 400.');
    }
} finally {
    WC()->customer = $originalCustomer;
    WC()->session = $originalSession;
    wp_set_current_user($originalUser);
    if (is_int($userId) && $userId > 0) wp_delete_user($userId);
}

if ($failures !== []) {
    fwrite(STDERR, "Plano 041 — destino de frete: FALHOU\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Plano 041 — destino de frete: OK\n");
