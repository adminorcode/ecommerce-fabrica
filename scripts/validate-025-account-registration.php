<?php

defined('ABSPATH') || exit(1);

if (!function_exists('wp_delete_user')) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce ativo.');
}

use Petshop\Core\WooCommerce\AccountPrivacy;
use Petshop\Core\WooCommerce\AccountRegistration;
use Petshop\Core\WooCommerce\AddressLookup;

$failures = [];

$record = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$record(
    get_option('woocommerce_registration_generate_password') === 'no',
    'WooCommerce deveria manter woocommerce_registration_generate_password=no'
);
$record(
    get_option('petshop_account_options_025_configured') === '1',
    'Lock petshop_account_options_025_configured deveria estar ativo'
);

$record(
    has_filter('woocommerce_registration_errors', [AccountRegistration::class, 'validateRegistration']) !== false,
    'Hook de validacao do cadastro nao registrado'
);
$record(
    has_filter('woocommerce_form_field_args', [AccountRegistration::class, 'pairRegisterPasswordField']) !== false,
    'Senha do cadastro deveria entrar no grid de duas colunas'
);
$record(
    has_filter('woocommerce_billing_fields', [AccountRegistration::class, 'addBillingAddressFields']) !== false,
    'Campos adicionais de endereco nao registrados'
);
$record(
    has_action('woocommerce_edit_account_form_fields', [AccountRegistration::class, 'renderAccountFields']) !== false,
    'Campos PF/PJ nao registrados em Detalhes da conta'
);
$record(
    has_action('woocommerce_save_account_details', [AccountRegistration::class, 'saveAccountDetails']) !== false,
    'Persistencia PF/PJ de Detalhes da conta nao registrada'
);
$record(
    has_filter('woocommerce_privacy_export_customer_personal_data', [AccountPrivacy::class, 'exportCustomerData']) !== false,
    'Exportador de privacidade adicional nao registrado'
);
$record(
    has_filter('woocommerce_privacy_erase_personal_data_customer', [AccountPrivacy::class, 'eraseCustomerData']) !== false,
    'Apagador de privacidade adicional nao registrado'
);
$record(
    has_action(
        'woocommerce_store_api_checkout_update_order_from_request',
        [AccountRegistration::class, 'validateCheckoutAccountPassword']
    ) !== false,
    'Validacao server-side de senha do Checkout Block nao registrada'
);

$originalPost = $_POST;
try {
    $_POST = [];
    $errors = new WP_Error();
    AccountRegistration::validateRegistration($errors, '', 'teste025@example.com');
    $record(
        $errors->get_error_codes() === ['petshop_nonce_invalid'],
        'Requisicao sem nonce deveria falhar so com petshop_nonce_invalid'
    );
    $record(
        !in_array('petshop_billing_first_name', $errors->get_error_codes(), true),
        'Requisicao sem nonce nao deveria validar campos do cadastro'
    );
} finally {
    $_POST = $originalPost;
}

ob_start();
AccountRegistration::renderFields();
$registrationHtml = (string) ob_get_clean();
foreach ([
    'billing_first_name',
    'billing_last_name',
    'password_confirm',
] as $fieldName) {
    $record(
        str_contains($registrationHtml, 'name="' . $fieldName . '"'),
        "Campo {$fieldName} ausente no cadastro"
    );
}
foreach ([
    'billing_phone',
    'petshop_person_type',
    'petshop_document',
    'billing_postcode',
    'billing_address_1',
    'billing_number',
    'billing_neighborhood',
    'billing_city',
    'billing_state',
] as $fieldName) {
    $record(
        !str_contains($registrationHtml, 'name="' . $fieldName . '"'),
        "Campo {$fieldName} nao deveria aparecer no cadastro inicial"
    );
}
$record(
    str_contains($registrationHtml, 'form-row-first')
        && str_contains($registrationHtml, 'form-row-last'),
    'Cadastro deveria usar grid de duas colunas (form-row-first/last)'
);

$billingFields = AccountRegistration::addBillingAddressFields([]);
$record(
    isset($billingFields['billing_number']) && !empty($billingFields['billing_number']['required']),
    'billing_number deveria existir e ser obrigatorio'
);
$record(
    isset($billingFields['billing_neighborhood']) && !empty($billingFields['billing_neighborhood']['required']),
    'billing_neighborhood deveria existir e ser obrigatorio'
);

$invalidCep = AddressLookup::lookupCep('123');
$record(
    is_wp_error($invalidCep) && $invalidCep->get_error_code() === 'petshop_invalid_cep',
    'CEP com menos de 8 digitos deveria ser recusado sem consulta externa'
);

$forceUnavailable = static function ($preempt, array $args, string $url) {
    if (str_contains($url, 'viacep.com.br')) {
        return new WP_Error('sentinela_025', 'indisponivel');
    }

    return $preempt;
};
add_filter('pre_http_request', $forceUnavailable, 10, 3);
try {
    $unavailable = AddressLookup::lookupCep('01001000');
    $record(
        is_wp_error($unavailable)
            && $unavailable->get_error_code() === 'petshop_viacep_unavailable'
            && str_contains($unavailable->get_error_message(), 'Preencha o endereco manualmente.') === false
            && str_contains($unavailable->get_error_message(), 'Preencha o endereço manualmente.'),
        'Falha do ViaCEP deveria retornar aviso em pt-BR e permitir preenchimento manual'
    );
} finally {
    remove_filter('pre_http_request', $forceUnavailable, 10);
}

$login = 'ticket025-gate-' . wp_generate_password(10, false, false);
$email = $login . '@example.com';
$userId = wp_insert_user([
    'user_login' => $login,
    'user_email' => $email,
    'user_pass' => wp_generate_password(24, true, true),
    'first_name' => 'Gate',
    'last_name' => '025',
]);

if (is_wp_error($userId)) {
    $failures[] = 'Nao foi possivel criar usuario temporario para gate de privacidade: ' . $userId->get_error_message();
} else {
    try {
        $meta = [
            'petshop_person_type' => 'PJ',
            'petshop_document' => '11222333000181',
            'billing_number' => '456',
            'billing_neighborhood' => 'Se',
            'billing_cpf' => '',
            'billing_cnpj' => '11222333000181',
        ];
        foreach ($meta as $key => $value) {
            update_user_meta((int) $userId, $key, $value);
        }

        $customer = new WC_Customer((int) $userId);
        $exported = AccountPrivacy::exportCustomerData([], $customer);
        $exportedByName = [];
        foreach ($exported as $item) {
            if (isset($item['name'], $item['value'])) {
                $exportedByName[(string) $item['name']] = (string) $item['value'];
            }
        }

        $record(($exportedByName['Tipo de pessoa'] ?? '') === 'PJ', 'Exportador sem Tipo de pessoa');
        $record(($exportedByName['CPF ou CNPJ'] ?? '') === '11222333000181', 'Exportador sem documento');
        $record(($exportedByName['Número do endereço de cobrança'] ?? '') === '456', 'Exportador sem numero');
        $record(($exportedByName['Bairro do endereço de cobrança'] ?? '') === 'Se', 'Exportador sem bairro');
        $record(($exportedByName['CNPJ de cobrança'] ?? '') === '11222333000181', 'Exportador sem CNPJ de cobranca');

        $erase = AccountPrivacy::eraseCustomerData(['items_removed' => false, 'messages' => []], $customer);
        $record(!empty($erase['items_removed']), 'Apagador de privacidade nao marcou items_removed');
        foreach (array_keys($meta) as $key) {
            $record(!metadata_exists('user', (int) $userId, $key), 'Apagador nao removeu ' . $key);
        }
    } finally {
        wp_delete_user((int) $userId);
    }
}

$corePath = plugin_dir_path(PETSHOP_CORE_FILE);
foreach ([
    'assets/js/account-registration.js',
    'assets/css/account-registration.css',
    'assets/js/checkout-account-password-confirmation.js',
] as $relativePath) {
    $record(is_file($corePath . $relativePath), 'Asset ausente: ' . $relativePath);
}

$guestAccountPath = $corePath . 'includes/WooCommerce/GuestAccount.php';
$guestAccountSource = is_file($guestAccountPath) ? (string) file_get_contents($guestAccountPath) : '';
$record(str_contains($guestAccountSource, 'name="password_confirm"'), 'GuestAccount sem confirmacao de senha');
$record(str_contains($guestAccountSource, 'wc_set_customer_auth_cookie'), 'GuestAccount sem login imediato');
$record(str_contains($guestAccountSource, '$order->set_customer_id'), 'GuestAccount sem associacao do pedido ao cliente');
$record(
    str_contains($guestAccountSource, "'account', 'create_failed'"),
    'GuestAccount deveria redirecionar falha de criacao em vez de wp_die'
);
$record(
    str_contains($guestAccountSource, "'account', 'link_failed'"),
    'GuestAccount deveria redirecionar falha de vinculo em vez de wp_die'
);

$staleUsers = get_users([
    'search' => '*ticket025*',
    'search_columns' => ['user_login', 'user_email'],
    'number' => 50,
    'fields' => 'ids',
]);

foreach ($staleUsers as $staleId) {
    wp_delete_user((int) $staleId);
}

$registrationPayload = static function (array $overrides = []): array {
    return array_merge([
        'woocommerce-register-nonce' => wp_create_nonce('woocommerce-register'),
        'billing_first_name' => 'Maria',
        'billing_last_name' => 'Silva',
        'billing_phone' => '(51) 99999-9999',
        'petshop_person_type' => 'PF',
        'petshop_document' => '39053344705',
        'billing_postcode' => '01001-000',
        'billing_address_1' => 'Praca da Se',
        'billing_number' => '100',
        'billing_address_2' => '',
        'billing_neighborhood' => 'Se',
        'billing_city' => 'Sao Paulo',
        'billing_state' => 'SP',
        'password' => 'Senha025@Forte',
        'password_confirm' => 'Senha025@Forte',
    ], $overrides);
};

$originalPost = $_POST;

try {
    $_POST = $registrationPayload(['petshop_document' => '', 'petshop_person_type' => '']);
    $emptyDocumentErrors = new WP_Error();
    AccountRegistration::validateRegistration($emptyDocumentErrors, '', 'vazio025@example.com');
    $emptyCodes = $emptyDocumentErrors->get_error_codes();
    $record(
        !in_array('petshop_petshop_document', $emptyCodes, true)
            && !in_array('petshop_document_required', $emptyCodes, true),
        'Documento vazio deveria ser aceito no cadastro'
    );
    $record(
        !in_array('petshop_cpf_invalid', $emptyCodes, true)
            && !in_array('petshop_cnpj_invalid', $emptyCodes, true),
        'Documento vazio nao deveria gerar segundo erro de CPF/CNPJ invalido'
    );

    $_POST = $registrationPayload([
        'billing_phone' => '',
        'petshop_person_type' => '',
        'petshop_document' => '',
        'billing_postcode' => '',
        'billing_address_1' => '',
        'billing_number' => '',
        'billing_neighborhood' => '',
        'billing_city' => '',
        'billing_state' => '',
    ]);
    $minimalErrors = new WP_Error();
    AccountRegistration::validateRegistration($minimalErrors, '', 'minimo025@example.com');
    $record(
        $minimalErrors->get_error_codes() === [],
        'Cadastro com nome, e-mail e senha nao deveria exigir telefone, documento nem endereco: ' . implode(',', $minimalErrors->get_error_codes())
    );

    $_POST = $registrationPayload();
    $validPfErrors = new WP_Error();
    AccountRegistration::validateRegistration($validPfErrors, '', 'pf025@example.com');
    $record(
        $validPfErrors->get_error_codes() === [],
        'Cadastro PF valido nao deveria gerar erro: ' . implode(',', $validPfErrors->get_error_codes())
    );

    $_POST = $registrationPayload([
        'petshop_person_type' => 'PJ',
        'petshop_document' => '04252011000110',
    ]);
    $validPjErrors = new WP_Error();
    AccountRegistration::validateRegistration($validPjErrors, '', 'pj025@example.com');
    $record(
        $validPjErrors->get_error_codes() === [],
        'Cadastro PJ valido nao deveria gerar erro: ' . implode(',', $validPjErrors->get_error_codes())
    );

    $persistLogin = 'ticket025-persist-' . wp_generate_password(8, false, false);
    $persistId = wp_insert_user([
        'user_login' => $persistLogin,
        'user_email' => $persistLogin . '@example.com',
        'user_pass' => 'Senha025@Forte',
        'first_name' => 'Maria',
        'last_name' => 'Silva',
    ]);

    if (is_wp_error($persistId)) {
        $failures[] = 'Nao foi possivel criar usuario para persistencia: ' . $persistId->get_error_message();
    } else {
        try {
            $_POST = $registrationPayload();
            AccountRegistration::saveCustomer((int) $persistId);
            $record(
                (string) get_user_meta((int) $persistId, 'petshop_person_type', true) === 'PF',
                'saveCustomer nao gravou tipo PF'
            );
            $record(
                (string) get_user_meta((int) $persistId, 'petshop_document', true) === '39053344705',
                'saveCustomer nao gravou CPF'
            );
            $record(
                (string) get_user_meta((int) $persistId, 'billing_cpf', true) === '39053344705',
                'saveCustomer nao gravou billing_cpf'
            );
            $record(
                (string) get_user_meta((int) $persistId, 'billing_phone', true) === '(51) 99999-9999',
                'saveCustomer nao gravou telefone'
            );
            $record(
                (string) get_user_meta((int) $persistId, 'billing_number', true) === '100',
                'saveCustomer nao gravou numero'
            );
            $record(
                (string) get_user_meta((int) $persistId, 'billing_neighborhood', true) === 'Se',
                'saveCustomer nao gravou bairro'
            );

            $_POST = $registrationPayload([
                'petshop_document' => '39053344705',
            ]);
            $duplicateErrors = new WP_Error();
            AccountRegistration::validateRegistration($duplicateErrors, '', 'dup025@example.com');
            $record(
                in_array('petshop_document_exists', $duplicateErrors->get_error_codes(), true),
                'CPF ja cadastrado deveria ser recusado'
            );
            AccountRegistration::releaseDocumentLock();
        } finally {
            wp_delete_user((int) $persistId);
        }
    }
} finally {
    $_POST = $originalPost;
}

$forceComplement = static function ($preempt, array $args, string $url) {
    if (!str_contains($url, 'viacep.com.br')) {
        return $preempt;
    }

    return [
        'headers' => [],
        'body' => wp_json_encode([
            'logradouro' => 'Praca da Se',
            'bairro' => 'Se',
            'localidade' => 'Sao Paulo',
            'uf' => 'SP',
            'complemento' => 'lado impar',
        ]),
        'response' => [
            'code' => 200,
            'message' => 'OK',
        ],
        'cookies' => [],
        'filename' => null,
    ];
};
add_filter('pre_http_request', $forceComplement, 10, 3);
try {
    $withComplement = AddressLookup::lookupCep('01001000');
    $record(
        is_array($withComplement)
            && ($withComplement['complemento'] ?? '') === 'lado impar'
            && ($withComplement['logradouro'] ?? '') === 'Praca da Se',
        'ViaCEP deveria devolver complemento quando a API enviar valor'
    );
} finally {
    remove_filter('pre_http_request', $forceComplement, 10);
}

$checkoutOrder = new WC_Order();
$mismatchRequest = new WP_REST_Request('POST', '/wc/store/v1/checkout');
$mismatchRequest->set_param('create_account', true);
$mismatchRequest->set_param('customer_password', 'Senha025@Forte');
$mismatchRequest->set_param('extensions', [
    AccountRegistration::CHECKOUT_EXTENSION_NAMESPACE => [
        'password_confirm' => 'Senha025@ERRADA',
    ],
]);

$mismatchCode = '';

try {
    AccountRegistration::validateCheckoutAccountPassword($checkoutOrder, $mismatchRequest);
} catch (\Automattic\WooCommerce\StoreApi\Exceptions\RouteException $exception) {
    $mismatchCode = $exception->getErrorCode();
} catch (Throwable $exception) {
    $mismatchCode = $exception->getMessage();
}

$record(
    $mismatchCode === 'petshop_password_mismatch',
    'Checkout Block deveria recusar senhas diferentes no servidor'
);

$matchRequest = new WP_REST_Request('POST', '/wc/store/v1/checkout');
$matchRequest->set_param('create_account', true);
$matchRequest->set_param('customer_password', 'Senha025@Forte');
$matchRequest->set_param('extensions', [
    AccountRegistration::CHECKOUT_EXTENSION_NAMESPACE => [
        'password_confirm' => 'Senha025@Forte',
    ],
]);

$matchThrew = false;

try {
    AccountRegistration::validateCheckoutAccountPassword($checkoutOrder, $matchRequest);
} catch (Throwable $exception) {
    $matchThrew = true;
    $failures[] = 'Senhas iguais no checkout nao deveriam falhar: ' . $exception->getMessage();
}

$record(!$matchThrew, 'Checkout Block deveria aceitar senha e confirmacao iguais');

$guestWithoutAccount = new WP_REST_Request('POST', '/wc/store/v1/checkout');
$guestWithoutAccount->set_param('create_account', false);
$guestWithoutAccount->set_param('customer_password', '');
$guestSkipped = true;

try {
    AccountRegistration::validateCheckoutAccountPassword($checkoutOrder, $guestWithoutAccount);
} catch (Throwable $exception) {
    $guestSkipped = false;
    $failures[] = 'Checkout visitante sem criar conta nao deveria validar senha: ' . $exception->getMessage();
}

$record($guestSkipped, 'Checkout visitante sem criar conta nao deve exigir confirmacao de senha');

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Validacao PHP do Plano 025 reprovada.');
}

WP_CLI::success('Cadastro com senha escolhida (Plano 025) aprovado.');
