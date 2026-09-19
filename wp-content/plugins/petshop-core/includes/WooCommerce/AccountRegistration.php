<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class AccountRegistration
{
    public static function bootstrap(): void
    {
        add_action('woocommerce_register_form', [self::class, 'renderFields']);
        add_filter('woocommerce_registration_errors', [self::class, 'validateRegistration'], 10, 3);
        add_action('woocommerce_created_customer', [self::class, 'saveCustomer'], 10, 1);
        add_filter('woocommerce_billing_fields', [self::class, 'addBillingAddressFields']);
        add_action('woocommerce_edit_account_form_fields', [self::class, 'renderAccountFields']);
        add_action('woocommerce_save_account_details_errors', [self::class, 'validateAccountDetails'], 10, 2);
        add_action('woocommerce_save_account_details', [self::class, 'saveAccountDetails'], 10, 1);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAccountAssets']);
    }
    public static function enqueueAccountAssets(): void
    {
        if (!is_account_page() || is_user_logged_in()) {
            return;
        }

        $jsRelative = 'assets/js/account-registration.js';
        $jsPath = plugin_dir_path(PETSHOP_CORE_FILE) . $jsRelative;

        $cssRelative = 'assets/css/account-registration.css';
        $cssPath = plugin_dir_path(PETSHOP_CORE_FILE) . $cssRelative;

        wp_enqueue_style(
            'petshop-account-registration',
            plugins_url($cssRelative, PETSHOP_CORE_FILE),
            [],
            is_file($cssPath) ? (string) filemtime($cssPath) : '1.0.0'
        );

        wp_enqueue_script(
            'petshop-account-registration',
            plugins_url($jsRelative, PETSHOP_CORE_FILE),
            [],
            is_file($jsPath) ? (string) filemtime($jsPath) : '1.0.0',
            true
        );
    }


    public static function renderFields(): void
    {
        $states = WC()->countries->get_states('BR');

        woocommerce_form_field('password_confirm', [
            'type' => 'password',
            'required' => true,
            'label' => 'Confirmar senha',
        ]);


        woocommerce_form_field('billing_first_name', [
            'type' => 'text',
            'required' => true,
            'label' => 'Nome',
        ], self::postValue('billing_first_name'));

        woocommerce_form_field('billing_last_name', [
            'type' => 'text',
            'required' => true,
            'label' => 'Sobrenome',
        ], self::postValue('billing_last_name'));

        woocommerce_form_field('billing_phone', [
            'type' => 'tel',
            'required' => true,
            'label' => 'Telefone com DDD',
            'placeholder' => '(51) 99999-9999',
        ], self::postValue('billing_phone'));

        woocommerce_form_field('petshop_person_type', [
            'type' => 'select',
            'required' => true,
            'label' => 'Tipo de pessoa',
            'options' => [
                '' => 'Selecione',
                'PF' => 'Pessoa física',
                'PJ' => 'Pessoa jurídica',
            ],
        ], self::postValue('petshop_person_type'));

        woocommerce_form_field('petshop_document', [
            'type' => 'text',
            'required' => true,
            'label' => 'CPF ou CNPJ',
            'placeholder' => 'Digite apenas números',
        ], self::postValue('petshop_document'));

        woocommerce_form_field('billing_postcode', [
            'type' => 'text',
            'required' => true,
            'label' => 'CEP',
            'placeholder' => '00000-000',
        ], self::postValue('billing_postcode'));

        woocommerce_form_field('billing_address_1', [
            'type' => 'text',
            'required' => true,
            'label' => 'Logradouro',
        ], self::postValue('billing_address_1'));

        woocommerce_form_field('billing_number', [
            'type' => 'text',
            'required' => true,
            'label' => 'Número',
        ], self::postValue('billing_number'));

        woocommerce_form_field('billing_address_2', [
            'type' => 'text',
            'required' => false,
            'label' => 'Complemento',
        ], self::postValue('billing_address_2'));

        woocommerce_form_field('billing_neighborhood', [
            'type' => 'text',
            'required' => true,
            'label' => 'Bairro',
        ], self::postValue('billing_neighborhood'));

        woocommerce_form_field('billing_city', [
            'type' => 'text',
            'required' => true,
            'label' => 'Cidade',
        ], self::postValue('billing_city'));

        woocommerce_form_field('billing_state', [
            'type' => 'select',
            'required' => true,
            'label' => 'UF',
            'options' => ['' => 'Selecione'] + $states,
        ], self::postValue('billing_state'));

    }

    public static function validateRegistration(
        \WP_Error $errors,
        string $username,
        string $email
    ): \WP_Error {
 $nonce = isset($_POST['woocommerce-register-nonce']) && is_scalar($_POST['woocommerce-register-nonce'])
    ? sanitize_text_field(wp_unslash((string) $_POST['woocommerce-register-nonce']))
    : '';

if ($nonce === '' || !wp_verify_nonce($nonce, 'woocommerce-register')) {
    return $errors;
}
    $required = [
            'billing_first_name' => 'Informe seu nome.',
            'billing_last_name' => 'Informe seu sobrenome.',
            'billing_phone' => 'Informe seu telefone.',
            'petshop_person_type' => 'Escolha pessoa física ou jurídica.',
            'petshop_document' => 'Informe seu CPF ou CNPJ.',
            'billing_postcode' => 'Informe seu CEP.',
            'billing_address_1' => 'Informe o logradouro.',
            'billing_number' => 'Informe o número.',
            'billing_neighborhood' => 'Informe o bairro.',
            'billing_city' => 'Informe a cidade.',
            'billing_state' => 'Informe a UF.',
        ];

        foreach ($required as $field => $message) {
            if (self::postValue($field) === '') {
                $errors->add('petshop_' . $field, $message);
            }
        }

        $type = strtoupper(self::postValue('petshop_person_type'));
        $document = self::digits(self::postValue('petshop_document'));

        if ($type !== '' && !in_array($type, ['PF', 'PJ'], true)) {
            $errors->add('petshop_person_type_invalid', 'Tipo de pessoa inválido.');
        }

        if ($type === 'PF' && !self::isValidCpf($document)) {
            $errors->add('petshop_cpf_invalid', 'Informe um CPF válido.');
        }

        if ($type === 'PJ' && !self::isValidCnpj($document)) {
            $errors->add('petshop_cnpj_invalid', 'Informe um CNPJ válido.');
        }

        if ($document !== '') {
            $existing = get_users([
                'meta_key' => 'petshop_document',
                'meta_value' => $document,
                'number' => 1,
                'fields' => 'ids',
            ]);

            if ($existing !== []) {
                $errors->add('petshop_document_exists', 'Este CPF ou CNPJ já está cadastrado.');
            }
        }

        if (!self::isValidPhone(self::postValue('billing_phone'))) {
            $errors->add('petshop_phone_invalid', 'Informe um telefone brasileiro válido com DDD.');
        }

        if (strlen(self::digits(self::postValue('billing_postcode'))) !== 8) {
            $errors->add('petshop_postcode_invalid', 'Informe um CEP válido com 8 dígitos.');
        }

        $states = WC()->countries->get_states('BR');
        $state = strtoupper(self::postValue('billing_state'));

        if ($state !== '' && !isset($states[$state])) {
            $errors->add('petshop_state_invalid', 'Informe uma UF válida.');
        }

        $password = isset($_POST['password'])
            ? (string) wp_unslash($_POST['password'])
            : '';

        $confirmation = isset($_POST['password_confirm'])
            ? (string) wp_unslash($_POST['password_confirm'])
            : '';

        if ($password === '') {
            $errors->add('petshop_password_required', 'Escolha uma senha.');
        }

        if ($confirmation === '') {
            $errors->add('petshop_password_confirmation_required', 'Confirme sua senha.');
        } elseif ($password !== $confirmation) {
            $errors->add('petshop_password_mismatch', 'As senhas não são iguais.');
        }

        return $errors;
    }
    public static function renderAccountFields(): void
    {
        $customerId = get_current_user_id();

        if ($customerId <= 0) {
            return;
        }

        $type = isset($_POST['petshop_person_type'])
            ? strtoupper(self::postValue('petshop_person_type'))
            : (string) get_user_meta($customerId, 'petshop_person_type', true);

        $document = isset($_POST['petshop_document'])
            ? self::postValue('petshop_document')
            : (string) get_user_meta($customerId, 'petshop_document', true);

        woocommerce_form_field('petshop_person_type', [
            'type' => 'select',
            'required' => true,
            'label' => 'Tipo de pessoa',
            'options' => [
                '' => 'Selecione',
                'PF' => 'Pessoa física',
                'PJ' => 'Pessoa jurídica',
            ],
        ], $type);

        woocommerce_form_field('petshop_document', [
            'type' => 'text',
            'required' => true,
            'label' => 'CPF ou CNPJ',
            'placeholder' => 'Digite apenas números',
        ], $document);
    }
    public static function validateAccountDetails(\WP_Error $errors, \stdClass $user): void
    {
        $type = strtoupper(self::postValue('petshop_person_type'));
        $document = self::digits(self::postValue('petshop_document'));

        if ($type === '') {
            $errors->add(
                'petshop_person_type_required',
                'Escolha pessoa física ou jurídica.'
            );
        } elseif (!in_array($type, ['PF', 'PJ'], true)) {
            $errors->add(
                'petshop_person_type_invalid',
                'Tipo de pessoa inválido.'
            );
        }

        if ($document === '') {
            $errors->add(
                'petshop_document_required',
                'Informe seu CPF ou CNPJ.'
            );
        } elseif ($type === 'PF' && !self::isValidCpf($document)) {
            $errors->add(
                'petshop_cpf_invalid',
                'Informe um CPF válido.'
            );
        } elseif ($type === 'PJ' && !self::isValidCnpj($document)) {
            $errors->add(
                'petshop_cnpj_invalid',
                'Informe um CNPJ válido.'
            );
        }

        if ($document !== '') {
            $existing = get_users([
                'meta_key' => 'petshop_document',
                'meta_value' => $document,
                'number' => 1,
                'fields' => 'ids',
                'exclude' => [(int) $user->ID],
            ]);

            if ($existing !== []) {
                $errors->add(
                    'petshop_document_exists',
                    'Este CPF ou CNPJ já está cadastrado.'
                );
            }
        }
    }
    public static function saveAccountDetails(int $customerId): void
    {
        $type = strtoupper(self::postValue('petshop_person_type'));
        $document = self::digits(self::postValue('petshop_document'));

        update_user_meta($customerId, 'petshop_person_type', $type);
        update_user_meta($customerId, 'petshop_document', $document);

        if ($type === 'PF') {
            update_user_meta($customerId, 'billing_cpf', $document);
            delete_user_meta($customerId, 'billing_cnpj');
        }

        if ($type === 'PJ') {
            update_user_meta($customerId, 'billing_cnpj', $document);
            delete_user_meta($customerId, 'billing_cpf');
        }
    }
    public static function addBillingAddressFields(array $fields): array
    {
        $fields['billing_number'] = [
            'label' => 'Número',
            'required' => true,
            'class' => ['form-row-first'],
            'priority' => 55,
        ];

        $fields['billing_neighborhood'] = [
            'label' => 'Bairro',
            'required' => true,
            'class' => ['form-row-last'],
            'priority' => 65,
        ];

        return $fields;
    }

    public static function saveCustomer(int $customerId): void
    {
        if (
            !isset($_POST['petshop_person_type'])
            && !isset($_POST['billing_first_name'])
            && !isset($_POST['billing_phone'])
        ) {
            return;
        }
        $fields = [
            'billing_first_name',
            'billing_last_name',
            'billing_phone',
            'billing_postcode',
            'billing_address_1',
            'billing_number',
            'billing_address_2',
            'billing_neighborhood',
            'billing_city',
            'billing_state',
        ];

        foreach ($fields as $field) {
            update_user_meta($customerId, $field, self::postValue($field));
        }


        update_user_meta($customerId, 'billing_country', 'BR');

        $type = strtoupper(self::postValue('petshop_person_type'));
        $document = self::digits(self::postValue('petshop_document'));

        update_user_meta($customerId, 'petshop_person_type', $type);
        update_user_meta($customerId, 'petshop_document', $document);

        if ($type === 'PF') {
            update_user_meta($customerId, 'billing_cpf', $document);
            delete_user_meta($customerId, 'billing_cnpj');
        }

        if ($type === 'PJ') {
            update_user_meta($customerId, 'billing_cnpj', $document);
            delete_user_meta($customerId, 'billing_cpf');
        }

        update_user_meta(
            $customerId,
            'first_name',
            self::postValue('billing_first_name')
        );

        update_user_meta(
            $customerId,
            'last_name',
            self::postValue('billing_last_name')
        );
    }

    private static function postValue(string $key): string
    {
        if (!isset($_POST[$key])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_POST[$key]));
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private static function isValidPhone(string $phone): bool
    {
        $digits = self::digits($phone);

        if (
            (strlen($digits) === 12 || strlen($digits) === 13)
            && substr($digits, 0, 2) === '55'
        ) {
            $digits = substr($digits, 2);
        }

        if (!in_array(strlen($digits), [10, 11], true)) {
            return false;
        }

        $validDdds = [
            11,12,13,14,15,16,17,18,19,
            21,22,24,27,28,
            31,32,33,34,35,37,38,
            41,42,43,44,45,46,47,48,49,
            51,53,54,55,
            61,62,63,64,65,66,67,68,69,
            71,73,74,75,77,79,
            81,82,83,84,85,86,87,88,89,
            91,92,93,94,95,96,97,98,99,
        ];

        $ddd = (int) substr($digits, 0, 2);

        if (!in_array($ddd, $validDdds, true)) {
            return false;
        }

        $firstNumber = $digits[2];

        if (strlen($digits) === 11) {
            return $firstNumber === '9';
        }

        return in_array($firstNumber, ['2', '3', '4', '5'], true);
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;

            for ($i = 0; $i < $position; $i++) {
                $sum += ((int) $cpf[$i]) * (($position + 1) - $i);
            }

            $digit = ((10 * $sum) % 11) % 10;

            if ($digit !== (int) $cpf[$position]) {
                return false;
            }
        }

        return true;
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weights1 = [5,4,3,2,9,8,7,6,5,4,3,2];
        $weights2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];

        $sum = 0;

        foreach ($weights1 as $i => $weight) {
            $sum += ((int) $cnpj[$i]) * $weight;
        }

        $digit1 = 11 - ($sum % 11);
        $digit1 = $digit1 >= 10 ? 0 : $digit1;

        if ($digit1 !== (int) $cnpj[12]) {
            return false;
        }

        $sum = 0;

        foreach ($weights2 as $i => $weight) {
            $sum += ((int) $cnpj[$i]) * $weight;
        }

        $digit2 = 11 - ($sum % 11);
        $digit2 = $digit2 >= 10 ? 0 : $digit2;

        return $digit2 === (int) $cnpj[13];
    }
}
