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

$originalPost = $_POST;
try {
    $_POST = [];
    $errors = new WP_Error();
    AccountRegistration::validateRegistration($errors, '', 'teste025@example.com');
    $record(
        $errors->get_error_codes() === [],
        'woocommerce_registration_errors nao deveria validar requisicoes sem nonce do formulario de cadastro'
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
    'billing_phone',
    'petshop_person_type',
    'petshop_document',
    'billing_postcode',
    'billing_address_1',
    'billing_number',
    'billing_neighborhood',
    'billing_city',
    'billing_state',
    'password_confirm',
] as $fieldName) {
    $record(
        str_contains($registrationHtml, 'name="' . $fieldName . '"'),
        'Cadastro de Minha conta sem o campo ' . $fieldName
    );
}

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

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Validacao PHP do Plano 025 reprovada.');
}

WP_CLI::success('Cadastro com senha escolhida (Plano 025) aprovado.');
