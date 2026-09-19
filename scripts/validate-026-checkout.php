<?php

defined('ABSPATH') || exit(1);

if (!function_exists('wp_delete_user')) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce ativo.');
}

use Petshop\Core\WooCommerce\AddressLookup;
use Petshop\Core\WooCommerce\CheckoutCustomerData;

$failures = [];

$record = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$record(has_action('template_redirect', [CheckoutCustomerData::class, 'hydrateCheckoutPage']) !== false, 'Hook frontend template_redirect nao registrado');
$record(has_filter('rest_request_before_callbacks', [CheckoutCustomerData::class, 'hydrateStoreApiRequest']) !== false, 'Hook REST antes dos callbacks da Store API nao registrado');
$record(has_action('wp_logout', [CheckoutCustomerData::class, 'clearTaggedSessionData']) !== false, 'Hook de limpeza de sessao apos logout nao registrado');
$record(has_action('woocommerce_init', [CheckoutCustomerData::class, 'registerCheckoutBlockFields']) !== false, 'Campos adicionais do Checkout Block nao registrados em woocommerce_init');
$record(has_action('woocommerce_set_additional_field_value', [CheckoutCustomerData::class, 'syncAdditionalFieldValue']) !== false, 'Compatibilidade de salvamento dos campos adicionais nao registrada');
$record(has_filter('woocommerce_get_default_value_for_petshop/number', [CheckoutCustomerData::class, 'defaultNumber']) !== false, 'Default do campo numero nao registrado');
$record(has_filter('woocommerce_get_default_value_for_petshop/neighborhood', [CheckoutCustomerData::class, 'defaultNeighborhood']) !== false, 'Default do campo bairro nao registrado');
$record(has_filter('woocommerce_get_default_value_for_petshop/person-type', [CheckoutCustomerData::class, 'defaultPersonType']) !== false, 'Default do campo PF/PJ nao registrado');
$record(has_filter('woocommerce_get_default_value_for_petshop/document', [CheckoutCustomerData::class, 'defaultDocument']) !== false, 'Default do campo CPF/CNPJ nao registrado');
$record(has_action('wp_enqueue_scripts', [AddressLookup::class, 'enqueue']) !== false, 'Lookup ViaCEP unico do petshop-core nao registrado');

$cepFixture = static function ($preempt, string $cep) {
    if ($cep === '01001000') {
        return [
            'logradouro' => 'Praca da Se',
            'bairro' => 'Se',
            'localidade' => 'Sao Paulo',
            'uf' => 'SP',
            'complemento' => 'lado impar',
        ];
    }

    if ($cep === '99999999') {
        return new WP_Error('petshop_cep_not_found', 'CEP não encontrado. Confira o número informado ou preencha o endereço manualmente.');
    }

    return $preempt;
};

add_filter('petshop_address_lookup_pre_http_result', $cepFixture, 10, 2);
try {
    $valid = AddressLookup::lookupCep('01001-000');
    $record(is_array($valid), 'CEP valido deveria retornar array');
    if (is_array($valid)) {
        $record(($valid['logradouro'] ?? '') === 'Praca da Se', 'ViaCEP nao retornou logradouro sanitizado');
        $record(($valid['bairro'] ?? '') === 'Se', 'ViaCEP nao retornou bairro sanitizado');
        $record(($valid['localidade'] ?? '') === 'Sao Paulo', 'ViaCEP nao retornou cidade sanitizada');
        $record(($valid['uf'] ?? '') === 'SP', 'ViaCEP nao retornou UF sanitizada');
        $record(($valid['complemento'] ?? '') === 'lado impar', 'ViaCEP nao retornou complemento quando disponivel');
        $record(!array_key_exists('numero', $valid) && !array_key_exists('number', $valid), 'ViaCEP nao deve preencher numero');
    }

    $invalid = AddressLookup::lookupCep('123');
    $record(is_wp_error($invalid) && $invalid->get_error_code() === 'petshop_invalid_cep', 'CEP incompleto deveria ser recusado');

    $notFound = AddressLookup::lookupCep('99999999');
    $record(
        is_wp_error($notFound)
            && $notFound->get_error_code() === 'petshop_cep_not_found'
            && str_contains($notFound->get_error_message(), 'preencha o endereço manualmente'),
        'CEP inexistente deveria avisar em pt-BR e permitir endereco manual'
    );
} finally {
    remove_filter('petshop_address_lookup_pre_http_result', $cepFixture, 10);
}

$forceUnavailable = static function ($preempt, array $args, string $url) {
    if (str_contains($url, 'viacep.com.br')) {
        return new WP_Error('sentinela_026', 'timeout');
    }

    return $preempt;
};
add_filter('pre_http_request', $forceUnavailable, 10, 3);
try {
    $unavailable = AddressLookup::lookupCep('02020000');
    $record(
        is_wp_error($unavailable)
            && $unavailable->get_error_code() === 'petshop_viacep_unavailable'
            && str_contains($unavailable->get_error_message(), 'Preencha o endereço manualmente.'),
        'ViaCEP indisponivel deveria retornar aviso manual em pt-BR'
    );
} finally {
    remove_filter('pre_http_request', $forceUnavailable, 10);
}

$assetPath = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/address-lookup.js';
$assetSource = is_file($assetPath) ? (string) file_get_contents($assetPath) : '';
$addressLookupPath = plugin_dir_path(PETSHOP_CORE_FILE) . 'includes/WooCommerce/AddressLookup.php';
$addressLookupSource = is_file($addressLookupPath) ? (string) file_get_contents($addressLookupPath) : '';
$record(str_contains($addressLookupSource, "rest_url('wc/store/v1/cart')"), 'PHP nao localiza URL do cart Store API via rest_url');
$record(str_contains($addressLookupSource, "rest_url('wc/store/v1/cart/update-customer')"), 'PHP nao localiza URL update-customer via rest_url');
$record(!str_contains($assetSource, "window.location.origin") && !str_contains($assetSource, "'/wp-json/"), 'JS nao deve hardcodar /wp-json nem window.location.origin para Store API');
$record(str_contains($assetSource, 'storeApiUpdateCustomerUrl'), 'JS de ViaCEP nao usa URL localizada do update-customer');
$record(str_contains($assetSource, 'address-line2'), 'JS de ViaCEP nao contempla complemento');
$record(str_contains($assetSource, 'petshopAutoComplement'), 'JS nao diferencia complemento automatico de complemento manual');
$record(str_contains($assetSource, 'petshop/number'), 'JS nao sincroniza numero adicional com Store API');
$record(str_contains($assetSource, 'petshop/neighborhood'), 'JS nao sincroniza bairro adicional com Store API');
$record(str_contains($assetSource, 'aria-live'), 'Mensagem de CEP nao anuncia resultado para leitores de tela');

$makeSession = static function (): object {
    return new class {
        /** @var array<string, mixed> */
        public array $data = [];
        public int $order_awaiting_payment = 0;

        public function get(string $key, $default = null)
        {
            return $this->data[$key] ?? $default;
        }

        public function set(string $key, $value): void
        {
            $this->data[$key] = $value;
        }

        public function __unset(string $key): void
        {
            unset($this->data[$key]);
        }

        public function unset(string $key): void
        {
            unset($this->data[$key]);
        }
    };
};

$createUser = static function (string $kind): int {
    $login = 'ticket026-' . strtolower($kind) . '-' . wp_generate_password(8, false, false);
    $email = $login . '@example.com';
    $userId = wp_insert_user([
        'user_login' => $login,
        'user_email' => $email,
        'user_pass' => wp_generate_password(24, true, true),
    ]);

    if (is_wp_error($userId)) {
        throw new RuntimeException($userId->get_error_message());
    }

    $isPf = $kind === 'PF';
    $document = $isPf ? '12345678909' : '11222333000181';
    $meta = [
        'billing_first_name' => 'Cliente',
        'billing_last_name' => $kind,
        'billing_email' => $email,
        'billing_phone' => '(11) 98888-7777',
        'billing_country' => 'BR',
        'billing_postcode' => $isPf ? '01001000' : '01310930',
        'billing_address_1' => $isPf ? 'Praca da Se' : 'Avenida Paulista',
        'billing_number' => $isPf ? '123' : '987',
        'billing_address_2' => $isPf ? 'lado impar' : 'conjunto 42',
        'billing_neighborhood' => $isPf ? 'Se' : 'Bela Vista',
        'billing_city' => 'Sao Paulo',
        'billing_state' => 'SP',
        'petshop_person_type' => $kind,
        'petshop_document' => $document,
        $isPf ? 'billing_cpf' : 'billing_cnpj' => $document,
    ];

    foreach ($meta as $key => $value) {
        update_user_meta((int) $userId, $key, $value);
    }

    return (int) $userId;
};

$assertHydrated = static function (WC_Customer $customer, object $session, string $kind, string $email) use ($record): void {
    $isPf = $kind === 'PF';
    $record($customer->get_billing_first_name() === 'Cliente', $kind . ': checkout nao hidratou nome');
    $record($customer->get_billing_last_name() === $kind, $kind . ': checkout nao hidratou sobrenome');
    $record($customer->get_billing_email() === $email, $kind . ': checkout nao hidratou e-mail');
    $record($customer->get_billing_phone() === '(11) 98888-7777', $kind . ': checkout nao hidratou telefone');
    $record($customer->get_billing_postcode() === ($isPf ? '01001000' : '01310930'), $kind . ': checkout nao hidratou CEP');
    $record($customer->get_billing_address_1() === ($isPf ? 'Praca da Se' : 'Avenida Paulista'), $kind . ': checkout nao hidratou rua');
    $record($customer->get_billing_address_2() === ($isPf ? 'lado impar' : 'conjunto 42'), $kind . ': checkout nao hidratou complemento');
    $record($customer->get_billing_city() === 'Sao Paulo', $kind . ': checkout nao hidratou cidade');
    $record($customer->get_billing_state() === 'SP', $kind . ': checkout nao hidratou estado');
    $record($customer->get_shipping_postcode() === $customer->get_billing_postcode(), $kind . ': shipping nao herdou CEP de billing');
    $record($customer->get_shipping_address_1() === $customer->get_billing_address_1(), $kind . ': shipping nao herdou rua de billing');
    $record(($session->data['billing_number'] ?? '') === ($isPf ? '123' : '987'), $kind . ': numero nao hidratado na sessao');
    $record(($session->data['shipping_number'] ?? '') === ($isPf ? '123' : '987'), $kind . ': numero shipping nao herdou billing');
    $record(($session->data['billing_neighborhood'] ?? '') === ($isPf ? 'Se' : 'Bela Vista'), $kind . ': bairro nao hidratado na sessao');
    $record(($session->data['shipping_neighborhood'] ?? '') === ($isPf ? 'Se' : 'Bela Vista'), $kind . ': bairro shipping nao herdou billing');
    $record(($session->data['billing_persontype'] ?? '') === ($isPf ? '1' : '2'), $kind . ': tipo pessoa nao mapeado');
    $record(($session->data[$isPf ? 'billing_cpf' : 'billing_cnpj'] ?? '') === ($isPf ? '12345678909' : '11222333000181'), $kind . ': documento especifico nao hidratado');
        $record(($session->data['billing_document'] ?? '') === ($isPf ? '12345678909' : '11222333000181'), $kind . ': documento unificado nao hidratado');

    $record(CheckoutCustomerData::defaultNumber('', 'billing', $customer) === ($isPf ? '123' : '987'), $kind . ': default do numero nao leu billing_number');
    $record(CheckoutCustomerData::defaultNumber('', 'shipping', $customer) === ($isPf ? '123' : '987'), $kind . ': default do numero shipping nao herdou billing_number');
    $record(CheckoutCustomerData::defaultNeighborhood('', 'billing', $customer) === ($isPf ? 'Se' : 'Bela Vista'), $kind . ': default do bairro nao leu billing_neighborhood');
    $record(CheckoutCustomerData::defaultNeighborhood('', 'shipping', $customer) === ($isPf ? 'Se' : 'Bela Vista'), $kind . ': default do bairro shipping nao herdou billing_neighborhood');
    $record(CheckoutCustomerData::defaultPersonType('', 'other', $customer) === $kind, $kind . ': default PF/PJ nao leu petshop_person_type');
    $record(CheckoutCustomerData::defaultDocument('', 'other', $customer) === ($isPf ? '12345678909' : '11222333000181'), $kind . ': default CPF/CNPJ nao leu petshop_document');
};

$clearCustomerAddress = static function (WC_Customer $customer): void {
    foreach (['first_name', 'last_name', 'country', 'postcode', 'address_1', 'address_2', 'city', 'state', 'phone', 'email'] as $field) {
        $setter = 'set_billing_' . $field;
        if (method_exists($customer, $setter)) {
            $customer->{$setter}('');
        }
    }

    foreach (['country', 'postcode', 'address_1', 'address_2', 'city', 'state'] as $field) {
        $setter = 'set_shipping_' . $field;
        if (method_exists($customer, $setter)) {
            $customer->{$setter}('');
        }
    }
};

$originalCustomer = WC()->customer ?? null;
$originalSession = WC()->session ?? null;
$originalUserId = get_current_user_id();
$createdUsers = [];

try {
    foreach (['PF', 'PJ'] as $kind) {
        $userId = $createUser($kind);
        $createdUsers[] = $userId;
        $customer = new WC_Customer($userId);
        $session = $makeSession();
        $email = (string) get_userdata($userId)->user_email;

        wp_set_current_user($userId);
        WC()->customer = $customer;
        WC()->session = $session;

        $clearCustomerAddress($customer);

        add_filter('woocommerce_is_checkout', '__return_true');
        do_action('template_redirect');
        remove_filter('woocommerce_is_checkout', '__return_true');
        $assertHydrated($customer, $session, $kind, $email);

        $restCustomer = new WC_Customer($userId);
        $restSession = $makeSession();
        WC()->customer = $restCustomer;
        WC()->session = $restSession;
        $clearCustomerAddress($restCustomer);
        apply_filters('rest_request_before_callbacks', null, [], new WP_REST_Request('GET', '/wc/store/v1/cart'));
        $assertHydrated($restCustomer, $restSession, $kind, $email);

        $updateCustomer = new WC_Customer($userId);
        $updateSession = $makeSession();
        WC()->customer = $updateCustomer;
        WC()->session = $updateSession;
        $clearCustomerAddress($updateCustomer);
        apply_filters('rest_request_before_callbacks', null, [], new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer'));
        $record($updateCustomer->get_billing_first_name() === '', $kind . ': update-customer nao deve hidratar nome pela bridge 026');
        $record($updateCustomer->get_billing_postcode() === '', $kind . ': update-customer nao deve hidratar CEP pela bridge 026');
        $record(($updateSession->data['billing_number'] ?? '') === '', $kind . ': update-customer nao deve hidratar numero pela bridge 026');
    }

    $pfUser = $createdUsers[0];
    $contaminatedSession = $makeSession();
    $contaminatedSession->set('petshop_checkout_customer_data_user_id', (string) $pfUser);
    $contaminatedSession->set('billing_document', '12345678909');
    $contaminatedSession->set('billing_number', '123');
    wp_set_current_user(0);
    WC()->session = $contaminatedSession;
    WC()->customer = new WC_Customer(0);
    apply_filters('rest_request_before_callbacks', null, [], new WP_REST_Request('GET', '/wc/store/v1/cart'));
    $record(($contaminatedSession->data['billing_document'] ?? '') === '', 'Visitante nao limpou documento marcado de usuario anterior');
    $record(($contaminatedSession->data['billing_number'] ?? '') === '', 'Visitante nao limpou numero marcado de usuario anterior');
} catch (Throwable $error) {
    $failures[] = 'Falha ao montar fixture de cliente 026: ' . $error->getMessage();
} finally {
    WC()->customer = $originalCustomer;
    WC()->session = $originalSession;
    wp_set_current_user((int) $originalUserId);
    foreach ($createdUsers as $userId) {
        wp_delete_user((int) $userId);
    }
}

$productDetailsPath = plugin_dir_path(PETSHOP_CORE_FILE) . 'includes/WooCommerce/ProductDetails.php';
$productDetailsSource = is_file($productDetailsPath) ? (string) file_get_contents($productDetailsPath) : '';
$record(!str_contains($productDetailsSource, 'viacep.com.br'), 'PDP ProductDetails nao deveria chamar ViaCEP');

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Validacao PHP do Plano 026 reprovada.');
}

WP_CLI::success('Checkout com dados salvos e ViaCEP (Plano 026) aprovado.');
