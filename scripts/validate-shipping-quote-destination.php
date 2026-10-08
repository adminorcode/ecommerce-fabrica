<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce.');
}

use Petshop\Core\WooCommerce\ShippingQuoteDestination;
use Petshop\Core\WooCommerce\ShippingQuotes;

$failures = [];
$fail = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$plugin = plugin_dir_path(PETSHOP_CORE_FILE);
$read = static function (string $relative) use ($plugin, $fail): string {
    $path = $plugin . $relative;
    $contents = is_file($path) ? file_get_contents($path) : false;
    $fail(is_string($contents), 'Arquivo ausente: ' . $relative);

    return is_string($contents) ? $contents : '';
};

$cartJs = $read('assets/js/cart-quantity-stability.js');
$quotesPhp = $read('includes/WooCommerce/ShippingQuotes.php');
$destinationPhp = $read('includes/WooCommerce/ShippingQuoteDestination.php');
$postcodePhp = $read('includes/WooCommerce/BrazilianPostcode.php');
$pluginPhp = $read('includes/Plugin.php');
$pdpJs = $read('assets/js/product-experience.js');

$fail(
    preg_match('/async updateCustomer\(postcode, nonce, current\) \{([\s\S]*?)\n    async updateItem/', $cartJs, $updateCustomer) === 1,
    'Carrinho nao expoe updateCustomer antes de updateItem.'
);
$updateCustomerBody = $updateCustomer[1] ?? '';
$fail(
    str_contains($updateCustomerBody, "country: 'BR', postcode, state: ''"),
    'Cotacao do carrinho precisa zerar a UF enviada para o servidor derivar do CEP.'
);
$fail(!str_contains($updateCustomerBody, 'city:'), 'Cotacao do carrinho nao pode enviar cidade.');
$fail(!str_contains($cartJs, 'viacep.com.br'), 'Calculadora de frete do carrinho nao pode consultar ViaCEP.');
$fail(!str_contains($pdpJs, 'viacep.com.br'), 'Calculadora de frete da PDP nao pode consultar ViaCEP.');
$fail(!str_contains($quotesPhp, 'viacep.com.br'), 'ShippingQuotes nao pode consultar ViaCEP.');
$fail(!str_contains($destinationPhp, 'viacep.com.br'), 'ShippingQuoteDestination nao pode consultar ViaCEP.');
$fail(!str_contains($postcodePhp, 'viacep.com.br'), 'Faixa de CEP nao pode consultar ViaCEP.');
$fail(str_contains($quotesPhp, 'self::destinationFor($postcode)'), 'PDP precisa montar o destino pela faixa de CEP.');
$fail(!str_contains($quotesPhp, "'state' => ''"), 'Destino da PDP nao pode voltar a ir sem UF.');
$fail(str_contains($pluginPhp, 'ShippingQuoteDestination::bootstrap()'), 'Plugin nao registra o destino da cotacao.');

$fail(
    has_action('woocommerce_store_api_cart_update_customer_from_request', [ShippingQuoteDestination::class, 'applyStateFromRequest']) !== false,
    'Hook de UF do update-customer ausente.'
);
$fail(
    has_filter('woocommerce_customer_get_shipping_city', [ShippingQuoteDestination::class, 'cityDuringQuote']) !== false,
    'Filtro da cidade transitoria ausente.'
);
$fail(
    has_action('woocommerce_before_calculate_totals', [ShippingQuoteDestination::class, 'beginCalculation']) !== false,
    'Abertura do calculo de frete ausente.'
);
$fail(
    has_action('woocommerce_after_calculate_totals', [ShippingQuoteDestination::class, 'endCalculation']) !== false,
    'Fechamento do calculo de frete ausente.'
);
$fail(
    has_filter('woocommerce_cart_shipping_packages', [ShippingQuoteDestination::class, 'fillPackageCity']) !== false,
    'A releitura do frete nao completa a cidade transitoria no pacote.'
);
$fail(
    has_action('woocommerce_store_api_cart_update_customer_from_request', [ShippingQuoteDestination::class, 'keepCartQuantities']) !== false,
    'A cotacao de frete nao protege a quantidade do carrinho.'
);
$fail(
    preg_match("/form.addEventListener\\('submit', async \\(event\\) => \\{([\\s\\S]*?)return true;/", $cartJs, $submitMatch) === 1,
    'Submit do CEP do carrinho nao encontrado.'
);
$submit = $submitMatch[1] ?? '';
$fail(str_contains($submit, 'await settleQuantities()'), 'O CEP precisa esperar a quantidade gravar antes de cotar.');
$fail(!str_contains($submit, 'receiveCart'), 'A resposta do frete nao pode substituir o carrinho.');
$fail(str_contains($submit, 'applyFreightToCart(after.body)'), 'O frete confirmado precisa atualizar somente a entrega.');
$fail(str_contains($cartJs, 'items: current.items'), 'A aplicacao do frete precisa manter os itens que ja estao no carrinho.');
$fail(str_contains($cartJs, 'showStoredPostcode'), 'O campo de CEP precisa mostrar o CEP ja gravado na sessao.');
$fail(
    str_contains($cartJs, 'shipping_address?.postcode'),
    'O CEP exibido tem de vir do endereco de entrega ja gravado.'
);
$fail(
    strpos($submit, 'if (rates.length === 0)') !== false
    && strpos($submit, 'if (rates.length === 0)') < strpos($submit, 'applyFreightToCart(after.body)'),
    'CEP sem opcao de entrega nao pode alterar o carrinho.'
);

$customer = WC()->customer;
$fail($customer instanceof WC_Customer, 'WC()->customer indisponivel.');

if ($customer instanceof WC_Customer) {
    $billingBefore = [
        $customer->get_billing_country(),
        $customer->get_billing_state(),
        $customer->get_billing_postcode(),
        $customer->get_billing_city(),
        $customer->get_billing_address_1(),
    ];

    $apply = static function (WC_Customer $customer, array $shipping): void {
        $request = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
        $request->set_param('shipping_address', $shipping);
        ShippingQuoteDestination::applyStateFromRequest($customer, $request);
    };

    $customer->set_shipping_country('BR');
    $customer->set_shipping_state('');
    $customer->set_shipping_city('');
    $customer->set_shipping_address_1('');
    $customer->set_shipping_postcode('');
    $apply($customer, ['country' => 'BR', 'postcode' => '01310-100', 'state' => '', 'city' => '', 'address_1' => '']);
    $fail($customer->get_shipping_state() === 'SP', 'CEP 01310-100 deveria definir UF SP.');
    $fail(trim((string) $customer->get_shipping_city()) === '', 'Cotacao gravou cidade no cliente.');
    $fail(trim((string) $customer->get_shipping_address_1()) === '', 'Cotacao gravou logradouro no cliente.');

    $customer->set_shipping_state('RJ');
    $apply($customer, ['country' => 'BR', 'postcode' => '01310100', 'state' => 'RJ', 'city' => '', 'address_1' => '']);
    $fail($customer->get_shipping_state() === 'SP', 'UF antiga deveria ser substituida pelo CEP novo.');

    $customer->set_shipping_state('RJ');
    $apply($customer, [
        'country' => 'BR',
        'postcode' => '01310-100',
        'state' => 'RJ',
        'city' => 'Rio de Janeiro',
        'address_1' => 'Avenida Atlantica',
    ]);
    $fail($customer->get_shipping_state() === 'RJ', 'Endereco completo nao deveria ter a UF trocada.');

    $customer->set_shipping_state('');
    $apply($customer, [
        'country' => 'BR',
        'postcode' => '20040-020',
        'state' => '',
        'city' => 'Sao Paulo',
        'address_1' => 'Avenida Paulista',
    ]);
    $fail($customer->get_shipping_state() === 'RJ', 'UF vazia deveria ser preenchida pelo CEP mesmo com rua ja digitada.');

    $customer->set_shipping_country('US');
    $customer->set_shipping_state('');
    $apply($customer, ['country' => 'US', 'postcode' => '01310-100', 'state' => '', 'city' => '', 'address_1' => '']);
    $fail($customer->get_shipping_state() === '', 'Pais diferente de BR nao deveria receber UF de CEP.');

    $customer->set_shipping_country('BR');
    $customer->set_shipping_state('');
    $apply($customer, ['country' => 'BR', 'postcode' => '00000-000', 'state' => '', 'city' => '', 'address_1' => '']);
    $fail($customer->get_shipping_state() === '', 'CEP desconhecido nao deveria inventar UF.');

    $billingAfter = [
        $customer->get_billing_country(),
        $customer->get_billing_state(),
        $customer->get_billing_postcode(),
        $customer->get_billing_city(),
        $customer->get_billing_address_1(),
    ];
    $fail($billingAfter === $billingBefore, 'Cotacao de frete alterou o endereco de cobranca.');

    $customer->set_shipping_country('BR');
    $customer->set_shipping_state('SP');
    $customer->set_shipping_postcode('01310100');
    $customer->set_shipping_city('');
    $customer->set_shipping_address_1('');
    ShippingQuoteDestination::beginCalculation();
    ShippingQuoteDestination::beginCalculation();
    ShippingQuoteDestination::endCalculation();
    $fail($customer->get_shipping_city() === ' ', 'Cidade transitoria deveria continuar enquanto o calculo estiver aninhado.');
    ShippingQuoteDestination::endCalculation();
    $fail(trim((string) $customer->get_shipping_city()) === '', 'Cidade transitoria vazou depois do calculo.');

    $customer->set_shipping_state('RJ');
    ShippingQuoteDestination::beginCalculation();
    $mismatched = $customer->get_shipping_city();
    ShippingQuoteDestination::endCalculation();
    $fail($mismatched === '', 'Cidade transitoria nao pode aparecer quando a UF nao bate com o CEP.');

    $productId = (int) wc_get_product_id_by_sku('PLAN013-SIMPLE');
    $product = $productId > 0 ? wc_get_product($productId) : null;
    $fail($product instanceof WC_Product && $product->needs_shipping(), 'Produto fixture PLAN013-SIMPLE ausente ou sem frete.');

    if ($product instanceof WC_Product && $product->needs_shipping()) {
        $destination = null;
        $capture = static function (array $packages) use (&$destination): array {
            $destination = $packages[0]['destination'] ?? null;

            return $packages;
        };
        add_filter('woocommerce_cart_shipping_packages', $capture, 9999);
        $addressRequirement = get_option('woocommerce_shipping_cost_requires_address');
        $cartContext = WC()->cart->cart_context;
        update_option('woocommerce_shipping_cost_requires_address', 'yes');
        WC()->cart->cart_context = 'store-api';

        try {
            WC()->cart->empty_cart();
            $fail((bool) WC()->cart->add_to_cart($product->get_id(), 1), 'Nao foi possivel adicionar o produto ao carrinho.');

            $customer->set_shipping_country('BR');
            $customer->set_shipping_state('SP');
            $customer->set_shipping_postcode('01310100');
            $customer->set_shipping_city('');
            $customer->set_shipping_address_1('');
            remove_filter('woocommerce_customer_get_shipping_city', [ShippingQuoteDestination::class, 'cityDuringQuote'], 10);
            WC()->cart->calculate_totals();
            $fail(!WC()->cart->has_calculated_shipping(), 'Sem a cidade transitoria o frete nao pode calcular so com CEP.');
            add_filter('woocommerce_customer_get_shipping_city', [ShippingQuoteDestination::class, 'cityDuringQuote'], 10, 2);

            $customer->set_shipping_state('RJ');
            $destination = null;
            WC()->cart->calculate_totals();
            $fail(!WC()->cart->has_calculated_shipping(), 'Frete calculou com UF diferente do CEP.');

            $customer->set_shipping_state('SP');
            $customer->set_shipping_city('');
            WC()->cart->calculate_totals();
            $fail(WC()->cart->has_calculated_shipping(), 'Carrinho nao calculou frete so com CEP e UF.');
            $fail(is_array($destination), 'Pacote de frete do carrinho nao foi montado.');
            $fail(($destination['state'] ?? '') === 'SP', 'Pacote do carrinho saiu sem UF SP.');
            $fail(($destination['city'] ?? null) === ' ', 'Pacote do carrinho precisa da cidade transitoria, nao de um municipio.');
            $fail(trim((string) ($destination['address_1'] ?? $destination['address'] ?? '')) === '', 'Pacote do carrinho levou logradouro.');
            $fail(trim((string) $customer->get_shipping_city()) === '', 'Calculo gravou a cidade transitoria no cliente.');
            $fail(trim((string) $customer->get_shipping_address_1()) === '', 'Calculo gravou logradouro no cliente.');

            $cartKey = array_key_first(WC()->cart->get_cart());
            if (is_string($cartKey) && $cartKey !== '') {
                $quantityBeforeQuote = (int) (WC()->cart->get_cart_item($cartKey)['quantity'] ?? 0);
                ShippingQuoteDestination::keepCartQuantities($customer, new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer'));
                WC()->cart->set_quantity($cartKey, $quantityBeforeQuote + 1, false);
                ShippingQuoteDestination::restoreCartQuantities(WC()->cart);
                $fail(
                    (int) (WC()->cart->get_cart_item($cartKey)['quantity'] ?? 0) === $quantityBeforeQuote,
                    'A cotacao de frete alterou a quantidade do carrinho.'
                );
            } else {
                $fail(false, 'Carrinho vazio na protecao de quantidade.');
            }

            $reread = WC()->cart->get_shipping_packages();
            $fail(($reread[0]['destination']['city'] ?? null) === ' ', 'A releitura do frete perdeu a cidade transitoria.');
            $quotedPackages = WC()->shipping()->calculate_shipping($reread);
            $rereadRates = 0;
            foreach ($quotedPackages as $package) {
                if (is_array($package['rates'] ?? null)) {
                    $rereadRates += count($package['rates']);
                }
            }
            $fail($rereadRates > 0, 'A releitura da Store API devolveu o frete sem opcoes.');
            $fail(trim((string) $customer->get_shipping_city()) === '', 'A releitura gravou cidade no cliente.');

            $customer->set_shipping_city('');
            WC()->cart->calculate_totals();
            $fail(WC()->cart->has_calculated_shipping(), 'Recalculo da quantidade derrubou o frete.');
            $fail(trim((string) $customer->get_shipping_city()) === '', 'Recalculo gravou cidade no cliente.');
            $fail($customer->get_shipping_state() === 'SP', 'Recalculo perdeu a UF derivada do CEP.');
        } finally {
            update_option('woocommerce_shipping_cost_requires_address', $addressRequirement);
            WC()->cart->cart_context = $cartContext;
            add_filter('woocommerce_customer_get_shipping_city', [ShippingQuoteDestination::class, 'cityDuringQuote'], 10, 2);
            remove_filter('woocommerce_cart_shipping_packages', $capture, 9999);
            WC()->cart->empty_cart();
            $customer->set_shipping_city('');
            $customer->set_shipping_address_1('');
            $customer->set_shipping_postcode('');
            $customer->set_shipping_state('');
            $customer->set_shipping_country('BR');
        }
    }
}

if ($failures !== []) {
    WP_CLI::error('Gate do destino de frete falhou: ' . implode(' | ', $failures));
}

WP_CLI::success('Gate do destino de frete: UF pela faixa do CEP, sem ViaCEP, sem cidade no checkout, e o recalculo preserva a cotacao.');
