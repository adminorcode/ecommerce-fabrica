<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class AccountRegistration
{
    public const CHECKOUT_EXTENSION_NAMESPACE = 'petshop-account';

    private static string $documentLockName = '';

    public static function bootstrap(): void
    {
        add_action('woocommerce_register_form', [self::class, 'renderFields']);
        add_filter('woocommerce_form_field_args', [self::class, 'pairRegisterPasswordField'], 10, 3);
        add_filter('woocommerce_registration_errors', [self::class, 'validateRegistration'], 10, 3);
        add_action('woocommerce_created_customer', [self::class, 'saveCustomer'], 10, 1);
        add_filter('woocommerce_billing_fields', [self::class, 'addBillingAddressFields']);
        add_action('woocommerce_edit_account_form_fields', [self::class, 'renderAccountFields']);
        add_action('woocommerce_save_account_details_errors', [self::class, 'validateAccountDetails'], 10, 2);
        add_action('woocommerce_save_account_details', [self::class, 'saveAccountDetails'], 10, 1);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAccountAssets']);
        add_action(
            'woocommerce_store_api_checkout_update_order_from_request',
            [self::class, 'validateCheckoutAccountPassword'],
            5,
            2
        );

        if (did_action('woocommerce_blocks_loaded') > 0) {
            self::registerCheckoutEndpointData();
        } else {
            add_action('woocommerce_blocks_loaded', [self::class, 'registerCheckoutEndpointData']);
        }
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

        wp_localize_script(
            'petshop-account-registration',
            'petshopAccountRegistration',
            [
                'createAccount' => __('Criar conta', 'petshop-core'),
                'backToLogin' => __('Voltar para entrar', 'petshop-core'),
            ]
        );
    }

    public static function renderFields(): void
    {
        if (!function_exists('woocommerce_form_field')) {
            return;
        }

        woocommerce_form_field('password_confirm', [
            'type' => 'password',
            'required' => true,
            'label' => __('Confirmar senha', 'petshop-core'),
            'class' => ['form-row-last'],
        ]);

        woocommerce_form_field('billing_first_name', [
            'type' => 'text',
            'required' => true,
            'label' => __('Nome', 'petshop-core'),
            'class' => ['form-row-first'],
        ], self::postValue('billing_first_name'));

        woocommerce_form_field('billing_last_name', [
            'type' => 'text',
            'required' => true,
            'label' => __('Sobrenome', 'petshop-core'),
            'class' => ['form-row-last'],
        ], self::postValue('billing_last_name'));
    }

    public static function validateRegistration(
        \WP_Error $errors,
        string $username,
        string $email
    ): \WP_Error {
        unset($username, $email);

        $nonce = isset($_POST['woocommerce-register-nonce']) && is_scalar($_POST['woocommerce-register-nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['woocommerce-register-nonce']))
            : '';

        if ($nonce === '' || !wp_verify_nonce($nonce, 'woocommerce-register')) {
            $errors->add(
                'petshop_nonce_invalid',
                __('Sessão expirada. Recarregue a página e tente novamente.', 'petshop-core')
            );

            return $errors;
        }

        $required = [
            'billing_first_name' => __('Informe seu nome.', 'petshop-core'),
            'billing_last_name' => __('Informe seu sobrenome.', 'petshop-core'),
        ];

        foreach ($required as $field => $message) {
            if (self::postValue($field) === '') {
                $errors->add('petshop_' . $field, $message);
            }
        }

        $type = strtoupper(self::postValue('petshop_person_type'));
        $document = self::digits(self::postValue('petshop_document'));
        $phone = self::postValue('billing_phone');
        $postcode = self::postValue('billing_postcode');

        if ($type !== '' && !in_array($type, ['PF', 'PJ'], true)) {
            $errors->add(
                'petshop_person_type_invalid',
                __('Tipo de pessoa inválido.', 'petshop-core')
            );
        }

        self::addDocumentFormatErrors($errors, $type, $document);
        self::assertUniqueDocument($errors, $document);

        if ($phone !== '' && !self::isValidPhone($phone)) {
            $errors->add(
                'petshop_phone_invalid',
                __('Informe um telefone brasileiro válido com DDD.', 'petshop-core')
            );
        }

        if ($postcode !== '' && strlen(self::digits($postcode)) !== 8) {
            $errors->add(
                'petshop_postcode_invalid',
                __('Informe um CEP válido com 8 dígitos.', 'petshop-core')
            );
        }

        $states = function_exists('WC') && WC() !== null
            ? WC()->countries->get_states('BR')
            : [];
        $state = strtoupper(self::postValue('billing_state'));

        if ($state !== '' && is_array($states) && !isset($states[$state])) {
            $errors->add(
                'petshop_state_invalid',
                __('Informe uma UF válida.', 'petshop-core')
            );
        }

        $password = isset($_POST['password']) && is_scalar($_POST['password'])
            ? (string) wp_unslash($_POST['password'])
            : '';

        $confirmation = isset($_POST['password_confirm']) && is_scalar($_POST['password_confirm'])
            ? (string) wp_unslash($_POST['password_confirm'])
            : '';

        if ($password === '') {
            $errors->add(
                'petshop_password_required',
                __('Escolha uma senha.', 'petshop-core')
            );
        }

        if ($confirmation === '') {
            $errors->add(
                'petshop_password_confirmation_required',
                __('Confirme sua senha.', 'petshop-core')
            );
        } elseif ($password !== $confirmation) {
            $errors->add(
                'petshop_password_mismatch',
                __('As senhas não são iguais.', 'petshop-core')
            );
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $args
     * @param mixed $value
     * @return array<string, mixed>
     */
    public static function pairRegisterPasswordField(array $args, string $key, $value): array
    {
        unset($value);

        if ($key !== 'password' || !is_account_page() || is_user_logged_in()) {
            return $args;
        }

        $fieldId = isset($args['id']) && is_scalar($args['id']) ? (string) $args['id'] : '';

        if ($fieldId !== '' && $fieldId !== 'reg_password') {
            return $args;
        }

        $classes = isset($args['class']) && is_array($args['class']) ? $args['class'] : [];
        $classes = array_values(array_filter(
            $classes,
            static fn($class): bool => is_string($class)
                && !in_array($class, ['form-row-wide', 'woocommerce-form-row--wide'], true)
        ));
        $classes[] = 'form-row-first';
        $classes[] = 'woocommerce-form-row--first';
        $args['class'] = $classes;

        return $args;
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
            'required' => false,
            'label' => __('Tipo de pessoa', 'petshop-core'),
            'options' => [
                '' => __('Selecione', 'petshop-core'),
                'PF' => __('Pessoa física', 'petshop-core'),
                'PJ' => __('Pessoa jurídica', 'petshop-core'),
            ],
        ], $type);

        woocommerce_form_field('petshop_document', [
            'type' => 'text',
            'required' => false,
            'label' => __('CPF ou CNPJ', 'petshop-core'),
            'placeholder' => __('Digite apenas números', 'petshop-core'),
        ], $document);
    }
    public static function validateAccountDetails(\WP_Error $errors, \stdClass $user): void
    {
        $type = strtoupper(self::postValue('petshop_person_type'));
        $document = self::digits(self::postValue('petshop_document'));

        if ($type !== '' && !in_array($type, ['PF', 'PJ'], true)) {
            $errors->add(
                'petshop_person_type_invalid',
                __('Tipo de pessoa inválido.', 'petshop-core')
            );
        }

        self::addDocumentFormatErrors($errors, $type, $document);
        self::assertUniqueDocument($errors, $document, [(int) $user->ID]);
    }
    public static function saveAccountDetails(int $customerId): void
    {
        $type = strtoupper(self::postValue('petshop_person_type'));
        $document = self::digits(self::postValue('petshop_document'));

        if ($document !== '' && self::documentBelongsToAnotherUser($document, [$customerId])) {
            self::releaseDocumentLock();
            return;
        }

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

        self::releaseDocumentLock();
    }
    public static function addBillingAddressFields(array $fields): array
    {
        $fields['billing_number'] = [
            'label' => __('Número', 'petshop-core'),
            'required' => true,
            'class' => ['form-row-first'],
            'priority' => 55,
        ];

        $fields['billing_neighborhood'] = [
            'label' => __('Bairro', 'petshop-core'),
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

        if ($document !== '' && self::documentBelongsToAnotherUser($document, [$customerId])) {
            $document = '';
            $type = '';
        }

        update_user_meta($customerId, 'petshop_person_type', $type);
        update_user_meta($customerId, 'petshop_document', $document);

        if ($type === 'PF' && $document !== '') {
            update_user_meta($customerId, 'billing_cpf', $document);
            delete_user_meta($customerId, 'billing_cnpj');
        }

        if ($type === 'PJ' && $document !== '') {
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

        self::releaseDocumentLock();
    }

    public static function registerCheckoutEndpointData(): void
    {
        if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }

        if (!class_exists(\Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::class)) {
            return;
        }

        woocommerce_store_api_register_endpoint_data([
            'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
            'namespace' => self::CHECKOUT_EXTENSION_NAMESPACE,
            'data_callback' => [self::class, 'checkoutExtensionData'],
            'schema_callback' => [self::class, 'checkoutExtensionSchema'],
            'schema_type' => ARRAY_A,
        ]);
    }

    /**
     * @return array{password_confirm: string}
     */
    public static function checkoutExtensionData(): array
    {
        return [
            'password_confirm' => '',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function checkoutExtensionSchema(): array
    {
        return [
            'password_confirm' => [
                'description' => __(
                    'Confirmação da senha da conta criada no checkout.',
                    'petshop-core'
                ),
                'type' => 'string',
                'context' => ['view', 'edit'],
                'readonly' => false,
            ],
        ];
    }

    public static function validateCheckoutAccountPassword(
        \WC_Order $order,
        \WP_REST_Request $request
    ): void {
        unset($order);

        if (is_user_logged_in() || !self::requestCreatesCheckoutAccount($request)) {
            return;
        }

        $password = self::requestScalar($request, 'customer_password');
        $confirmation = self::requestPasswordConfirmation($request);

        if ($password === '') {
            self::throwCheckoutError(
                'petshop_password_required',
                __('Escolha uma senha.', 'petshop-core')
            );
        }

        if ($confirmation === '') {
            self::throwCheckoutError(
                'petshop_password_confirmation_required',
                __('Confirme sua senha.', 'petshop-core')
            );
        }

        if ($password !== $confirmation) {
            self::throwCheckoutError(
                'petshop_password_mismatch',
                __('As senhas não são iguais.', 'petshop-core')
            );
        }
    }

    private static function requestCreatesCheckoutAccount(\WP_REST_Request $request): bool
    {
        if (filter_var($request->get_param('create_account'), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $registrationRequired = function_exists('WC')
            && WC() !== null
            && WC()->checkout() !== null
            && filter_var(WC()->checkout()->is_registration_required(), FILTER_VALIDATE_BOOLEAN);

        return $registrationRequired && self::requestScalar($request, 'customer_password') !== '';
    }

    private static function addDocumentFormatErrors(
        \WP_Error $errors,
        string $type,
        string $document
    ): void {
        if ($document === '') {
            return;
        }

        if ($type === 'PF' || ($type === '' && strlen($document) === 11)) {
            if (!self::isValidCpf($document)) {
                $errors->add(
                    'petshop_cpf_invalid',
                    __('Informe um CPF válido.', 'petshop-core')
                );
            }

            return;
        }

        if ($type === 'PJ' || ($type === '' && strlen($document) === 14)) {
            if (!self::isValidCnpj($document)) {
                $errors->add(
                    'petshop_cnpj_invalid',
                    __('Informe um CNPJ válido.', 'petshop-core')
                );
            }

            return;
        }

        $errors->add(
            'petshop_document_invalid',
            __('Informe um CPF ou CNPJ válido.', 'petshop-core')
        );
    }

    /**
     * @param list<int> $excludeIds
     */
    private static function assertUniqueDocument(
        \WP_Error $errors,
        string $document,
        array $excludeIds = []
    ): void {
        if ($document === '') {
            return;
        }

        if (!self::acquireDocumentLock($document)) {
            $errors->add(
                'petshop_document_busy',
                __('Não foi possível validar o CPF ou CNPJ agora. Tente novamente.', 'petshop-core')
            );
            return;
        }

        if (self::documentBelongsToAnotherUser($document, $excludeIds)) {
            $errors->add(
                'petshop_document_exists',
                __('Este CPF ou CNPJ já está cadastrado.', 'petshop-core')
            );
            self::releaseDocumentLock();
        }
    }

    /**
     * @param list<int> $excludeIds
     */
    private static function documentBelongsToAnotherUser(string $document, array $excludeIds = []): bool
    {
        if ($document === '') {
            return false;
        }

        $query = [
            'meta_key' => 'petshop_document',
            'meta_value' => $document,
            'number' => 1,
            'fields' => 'ids',
        ];

        if ($excludeIds !== []) {
            $query['exclude'] = $excludeIds;
        }

        return get_users($query) !== [];
    }

    private static function acquireDocumentLock(string $document): bool
    {
        if ($document === '') {
            return true;
        }

        $name = 'petshop_doc_' . hash('sha256', $document);

        if (self::$documentLockName === $name) {
            return true;
        }

        if (self::$documentLockName !== '') {
            self::releaseDocumentLock();
        }

        global $wpdb;

        $got = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, 5));

        if ((string) $got !== '1') {
            return false;
        }

        self::$documentLockName = $name;
        add_action('shutdown', [self::class, 'releaseDocumentLock'], 1);

        return true;
    }

    public static function releaseDocumentLock(): void
    {
        if (self::$documentLockName === '') {
            return;
        }

        global $wpdb;

        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::$documentLockName));
        self::$documentLockName = '';
    }

    private static function requestPasswordConfirmation(\WP_REST_Request $request): string
    {
        $extensions = $request->get_param('extensions');

        if (!is_array($extensions)) {
            return '';
        }

        $payload = $extensions[self::CHECKOUT_EXTENSION_NAMESPACE] ?? null;

        if (!is_array($payload) || !isset($payload['password_confirm']) || !is_scalar($payload['password_confirm'])) {
            return '';
        }

        return (string) wp_unslash((string) $payload['password_confirm']);
    }

    private static function requestScalar(\WP_REST_Request $request, string $key): string
    {
        $value = $request->get_param($key);

        if (!is_scalar($value)) {
            return '';
        }

        return (string) wp_unslash((string) $value);
    }

    private static function throwCheckoutError(string $code, string $message): void
    {
        if (!class_exists(\Automattic\WooCommerce\StoreApi\Exceptions\RouteException::class)) {
            throw new \RuntimeException($message);
        }

        throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
            $code,
            $message,
            400
        );
    }

    private static function postValue(string $key): string
    {
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
            return '';
        }

        return sanitize_text_field(wp_unslash((string) $_POST[$key]));
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
