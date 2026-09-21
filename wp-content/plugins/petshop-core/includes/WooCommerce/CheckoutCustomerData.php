<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class CheckoutCustomerData
{
    private const SESSION_USER_KEY = 'petshop_checkout_customer_data_user_id';
    private const SESSION_HYDRATED_KEY = 'petshop_checkout_customer_data_hydrated';

    public static function bootstrap(): void
    {
        add_action('woocommerce_init', [self::class, 'registerCheckoutBlockFields']);
        add_action('template_redirect', [self::class, 'hydrateCheckoutPage'], 5);
        add_filter('rest_request_before_callbacks', [self::class, 'hydrateStoreApiRequest'], 5, 3);
        add_filter('rest_request_after_callbacks', [self::class, 'filterStoreApiCartResponse'], 10, 3);
        add_action('wp_logout', [self::class, 'clearTaggedSessionData']);
        add_action('woocommerce_set_additional_field_value', [self::class, 'syncAdditionalFieldValue'], 10, 4);
        add_filter('woocommerce_get_default_value_for_petshop/number', [self::class, 'defaultNumber'], 10, 3);
        add_filter('woocommerce_get_default_value_for_petshop/neighborhood', [self::class, 'defaultNeighborhood'], 10, 3);
        add_filter('woocommerce_get_default_value_for_petshop/person-type', [self::class, 'defaultPersonType'], 10, 3);
        add_filter('woocommerce_get_default_value_for_petshop/document', [self::class, 'defaultDocument'], 10, 3);
    }

    public static function registerCheckoutBlockFields(): void
    {
        if (!function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }

        woocommerce_register_additional_checkout_field([
            'id' => 'petshop/number',
            'label' => __('Número', 'petshop-core'),
            'location' => 'address',
            'type' => 'text',
            'required' => true,
            'attributes' => [
                'autocomplete' => 'off',
                'maxLength' => 20,
            ],
            'sanitize_callback' => [self::class, 'sanitizeShortText'],
        ]);

        woocommerce_register_additional_checkout_field([
            'id' => 'petshop/neighborhood',
            'label' => __('Bairro', 'petshop-core'),
            'location' => 'address',
            'type' => 'text',
            'required' => true,
            'attributes' => [
                'autocomplete' => 'address-level3',
                'maxLength' => 80,
            ],
            'sanitize_callback' => [self::class, 'sanitizeShortText'],
        ]);

        woocommerce_register_additional_checkout_field([
            'id' => 'petshop/person-type',
            'label' => __('Tipo de pessoa', 'petshop-core'),
            'location' => 'contact',
            'type' => 'select',
            'required' => true,
            'options' => [
                [
                    'value' => 'PF',
                    'label' => __('Pessoa física', 'petshop-core'),
                ],
                [
                    'value' => 'PJ',
                    'label' => __('Pessoa jurídica', 'petshop-core'),
                ],
            ],
            'sanitize_callback' => [self::class, 'sanitizePersonType'],
        ]);

        woocommerce_register_additional_checkout_field([
            'id' => 'petshop/document',
            'label' => __('CPF ou CNPJ', 'petshop-core'),
            'location' => 'contact',
            'type' => 'text',
            'required' => true,
            'attributes' => [
                'autocomplete' => 'off',
                'maxLength' => 18,
            ],
            'sanitize_callback' => [self::class, 'sanitizeDocument'],
        ]);
    }

    public static function hydrateCheckoutPage(): void
    {
        if (
            !function_exists('is_checkout')
            || !is_checkout()
            || (function_exists('is_order_received_page') && is_order_received_page())
            || (function_exists('is_checkout_pay_page') && is_checkout_pay_page())
        ) {
            return;
        }

        $userId = (int) get_current_user_id();

        if (self::sessionAlreadyHydrated($userId)) {
            return;
        }

        self::hydrateCurrentCustomer();
    }

    /**
     * @param mixed $response
     * @param array<mixed> $handler
     * @return mixed
     */
    public static function hydrateStoreApiRequest($response, array $handler, \WP_REST_Request $request)
    {
        unset($handler);

        if (!str_starts_with($request->get_route(), '/wc/store/v1/cart')) {
            return $response;
        }

        if (str_ends_with($request->get_route(), '/cart/update-customer')) {
            return $response;
        }

        if (!is_user_logged_in()) {
            self::clearTaggedSessionData();
            return $response;
        }

        if (self::sessionAlreadyHydrated((int) get_current_user_id())) {
            return $response;
        }

        self::hydrateCurrentCustomer();

        return $response;
    }

    /**
     * @param mixed $response
     * @param array<mixed> $handler
     * @return mixed
     */
    public static function filterStoreApiCartResponse($response, array $handler, \WP_REST_Request $request)
    {
        unset($handler);

        if (!str_starts_with($request->get_route(), '/wc/store/v1/cart')) {
            return $response;
        }

        if (str_ends_with($request->get_route(), '/cart/update-customer')) {
            return $response;
        }

        if (!is_user_logged_in()) {
            self::clearTaggedSessionData();
            return $response;
        }

        $userId = (int) get_current_user_id();

        if (self::sessionAlreadyHydrated($userId)) {
            if (!$response instanceof \WP_REST_Response) {
                return $response;
            }

            $data = $response->get_data();

            if (is_array($data)) {
                self::hydrateCartResponseData($data, false);
                $response->set_data($data);
            }

            return $response;
        }

        if (!$response instanceof \WP_REST_Response) {
            return $response;
        }

        $data = $response->get_data();

        if (!is_array($data)) {
            return $response;
        }

        self::hydrateCartResponseData($data, true);
        $response->set_data($data);
        self::markSessionHydrated($userId);

        return $response;
    }

    public static function hydrateCurrentCustomer(): void
    {
        if (!function_exists('WC')) {
            return;
        }

        if (!is_user_logged_in()) {
            self::clearTaggedSessionData();
            return;
        }

        $customer = WC()->customer ?? null;

        if (!$customer instanceof \WC_Customer) {
            return;
        }

        $userId = (int) get_current_user_id();

        self::hydrateBillingAddress($customer, $userId);
        self::hydrateShippingAddress($customer, $userId);
        self::hydrateBrazilianCheckoutSession($userId);
    }

    private static function hydrateBillingAddress(\WC_Customer $customer, int $userId): bool
    {
        $changed = false;

        foreach (self::billingFields() as $field) {
            $value = (string) get_user_meta($userId, 'billing_' . $field, true);

            if ($field === 'email' && $value === '') {
                $user = get_userdata($userId);
                $value = $user instanceof \WP_User ? (string) $user->user_email : '';
            }

            $changed = self::setCustomerFieldIfEmpty(
                $customer,
                'billing',
                $field,
                $value
            ) || $changed;
        }

        return $changed;
    }

    private static function hydrateShippingAddress(\WC_Customer $customer, int $userId): bool
    {
        $changed = false;

        foreach (self::shippingFields() as $field) {
            $shippingValue = (string) get_user_meta($userId, 'shipping_' . $field, true);
            $billingValue = (string) get_user_meta($userId, 'billing_' . $field, true);

            $changed = self::setCustomerFieldIfEmpty(
                $customer,
                'shipping',
                $field,
                $shippingValue !== '' ? $shippingValue : $billingValue
            ) || $changed;
        }

        return $changed;
    }

    private static function hydrateBrazilianCheckoutSession(int $userId): void
    {
        $session = WC()->session ?? null;

        if (!is_object($session) || !method_exists($session, 'get') || !method_exists($session, 'set')) {
            return;
        }

        $taggedUserId = (int) $session->get(self::SESSION_USER_KEY, 0);

        if ($taggedUserId > 0 && $taggedUserId !== $userId) {
            self::clearBridgeSessionValues($session);
        }

        $billingNumber = (string) get_user_meta($userId, 'billing_number', true);
        $shippingNumber = (string) get_user_meta($userId, 'shipping_number', true);
        $billingNeighborhood = (string) get_user_meta($userId, 'billing_neighborhood', true);
        $shippingNeighborhood = (string) get_user_meta($userId, 'shipping_neighborhood', true);
        $personType = self::normalizePersonType((string) get_user_meta($userId, 'petshop_person_type', true));
        $document = (string) get_user_meta($userId, 'petshop_document', true);
        $cpf = (string) get_user_meta($userId, 'billing_cpf', true);
        $cnpj = (string) get_user_meta($userId, 'billing_cnpj', true);

        if ($personType === '1' && $cpf === '' && $document !== '') {
            $cpf = $document;
        }

        if ($personType === '2' && $cnpj === '' && $document !== '') {
            $cnpj = $document;
        }

        foreach ([
            'billing_number' => $billingNumber,
            'shipping_number' => $shippingNumber !== '' ? $shippingNumber : $billingNumber,
            'billing_neighborhood' => $billingNeighborhood,
            'shipping_neighborhood' => $shippingNeighborhood !== '' ? $shippingNeighborhood : $billingNeighborhood,
            'billing_persontype' => $personType,
            'billing_document' => $document,
            'billing_cpf' => $cpf,
            'billing_cnpj' => $cnpj,
        ] as $key => $value) {
            self::setSessionValueIfEmpty($session, $key, $value);
        }

        $session->set(self::SESSION_USER_KEY, (string) $userId);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function hydrateCartResponseData(array &$data, bool $fromAccount): void
    {
        $session = function_exists('WC') ? (WC()->session ?? null) : null;
        $userId = is_user_logged_in() ? (int) get_current_user_id() : 0;

        foreach (['billing', 'shipping'] as $group) {
            $addressKey = $group . '_address';
            $data[$addressKey] = is_array($data[$addressKey] ?? null) ? $data[$addressKey] : [];
            if ($fromAccount) {
                self::mergeNativeAddressIntoResponse($data[$addressKey], $group, $userId);
                self::mergeAdditionalAddressIntoResponse($data[$addressKey], $group, $userId, $session);
            }
        }

        self::inheritBillingResponseWhenShippingEmpty($data);

        if ($fromAccount) {
            self::mergeContactAdditionalFieldsIntoResponse($data, $userId, $session);
        }
    }

    /**
     * @param array<string, mixed> $address
     */
    private static function mergeNativeAddressIntoResponse(array &$address, string $group, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        foreach (self::addressFieldsForResponse($group) as $field) {
            if ((string) ($address[$field] ?? '') !== '') {
                continue;
            }

            $value = (string) get_user_meta($userId, $group . '_' . $field, true);

            if ($group === 'shipping' && $value === '') {
                $value = (string) get_user_meta($userId, 'billing_' . $field, true);
            }

            if ($field === 'email' && $value === '') {
                $user = get_userdata($userId);
                $value = $user instanceof \WP_User ? (string) $user->user_email : '';
            }

            if ($value !== '') {
                $address[$field] = $value;
            }
        }

        if ((string) ($address['country'] ?? '') === '') {
            $address['country'] = 'BR';
        }
    }

    /**
     * @param array<string, mixed> $address
     */
    private static function mergeAdditionalAddressIntoResponse(array &$address, string $group, int $userId, ?object $session): void
    {
        foreach (['number', 'neighborhood'] as $field) {
            $key = 'petshop/' . $field;

            if ((string) ($address[$key] ?? '') !== '') {
                continue;
            }

            $value = self::sessionValue($session, $group . '_' . $field);

            if ($value === '' && $group === 'shipping') {
                $value = self::sessionValue($session, 'billing_' . $field);
            }

            if ($value === '' && $userId > 0) {
                $value = (string) get_user_meta($userId, $group . '_' . $field, true);
            }

            if ($value === '' && $group === 'shipping' && $userId > 0) {
                $value = (string) get_user_meta($userId, 'billing_' . $field, true);
            }

            if ($value !== '') {
                $address[$key] = $value;
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function inheritBillingResponseWhenShippingEmpty(array &$data): void
    {
        $billing = is_array($data['billing_address'] ?? null) ? $data['billing_address'] : [];
        $shipping = is_array($data['shipping_address'] ?? null) ? $data['shipping_address'] : [];

        foreach (['first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone', 'petshop/number', 'petshop/neighborhood'] as $field) {
            if ((string) ($shipping[$field] ?? '') === '' && (string) ($billing[$field] ?? '') !== '') {
                $shipping[$field] = $billing[$field];
            }
        }

        $data['shipping_address'] = $shipping;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function mergeContactAdditionalFieldsIntoResponse(array &$data, int $userId, ?object $session): void
    {
        $data['additional_fields'] = is_array($data['additional_fields'] ?? null) ? $data['additional_fields'] : [];

        $personType = self::sanitizePersonType(self::sessionValue($session, 'billing_persontype'));

        if ($personType === '' && $userId > 0) {
            $personType = self::sanitizePersonType((string) get_user_meta($userId, 'petshop_person_type', true));
        }

        $document = self::sessionValue($session, 'billing_document');

        if ($document === '' && $userId > 0) {
            $document = (string) get_user_meta($userId, 'petshop_document', true);
        }

        if ($document === '' && $userId > 0) {
            $document = $personType === 'PJ'
                ? (string) get_user_meta($userId, 'billing_cnpj', true)
                : (string) get_user_meta($userId, 'billing_cpf', true);
        }

        if ((string) ($data['additional_fields']['petshop/person-type'] ?? '') === '' && $personType !== '') {
            $data['additional_fields']['petshop/person-type'] = $personType;
        }

        if ((string) ($data['additional_fields']['petshop/document'] ?? '') === '' && $document !== '') {
            $data['additional_fields']['petshop/document'] = $document;
        }
    }

    /**
     * @param mixed $value
     * @param mixed $wcObject
     */
    public static function syncAdditionalFieldValue(string $key, $value, string $group, $wcObject): void
    {
        if (!is_object($wcObject) || !method_exists($wcObject, 'update_meta_data')) {
            return;
        }

        $value = is_scalar($value) ? (string) $value : '';

        if ($key === 'petshop/number' && in_array($group, ['billing', 'shipping'], true)) {
            $wcObject->update_meta_data(self::legacyAddressMetaKey($wcObject, $group, 'number'), sanitize_text_field($value), true);
            self::setCheckoutFieldValue($key, $value, $group, $wcObject);
            return;
        }

        if ($key === 'petshop/neighborhood' && in_array($group, ['billing', 'shipping'], true)) {
            $wcObject->update_meta_data(self::legacyAddressMetaKey($wcObject, $group, 'neighborhood'), sanitize_text_field($value), true);
            self::setCheckoutFieldValue($key, $value, $group, $wcObject);
            return;
        }

        if ($group !== 'other') {
            return;
        }

        if ($key === 'petshop/person-type') {
            $personType = self::sanitizePersonType($value);
            $wcObject->update_meta_data('petshop_person_type', $personType, true);
            $wcObject->update_meta_data('billing_persontype', self::normalizePersonType($personType), true);
            self::setCheckoutFieldValue($key, $personType, $group, $wcObject);
            return;
        }

        if ($key === 'petshop/document') {
            $document = self::sanitizeDocument($value);
            $wcObject->update_meta_data('petshop_document', $document, true);
            self::setCheckoutFieldValue($key, $document, $group, $wcObject);
            $personType = (string) $wcObject->get_meta('petshop_person_type');
            if (self::normalizePersonType($personType) === '2') {
                $wcObject->update_meta_data('billing_cnpj', $document, true);
                $wcObject->update_meta_data('billing_cpf', '', true);
            } else {
                $wcObject->update_meta_data('billing_cpf', $document, true);
                $wcObject->update_meta_data('billing_cnpj', '', true);
            }
        }
    }

    /**
     * @param mixed $value
     * @param mixed $wcObject
     */
    public static function defaultNumber($value, string $group, $wcObject): string
    {
        unset($value);

        $number = self::checkoutFieldDefault('petshop/number', $group, $wcObject);

        if ($number === '') {
            $number = self::metaDefault($wcObject, $group . '_number', $group . '_number');
        }

        return $number !== '' || $group !== 'shipping' ? $number : self::metaDefault($wcObject, 'billing_number', 'billing_number');
    }

    /**
     * @param mixed $value
     * @param mixed $wcObject
     */
    public static function defaultNeighborhood($value, string $group, $wcObject): string
    {
        unset($value);

        $neighborhood = self::checkoutFieldDefault('petshop/neighborhood', $group, $wcObject);

        if ($neighborhood === '') {
            $neighborhood = self::metaDefault($wcObject, $group . '_neighborhood', $group . '_neighborhood');
        }

        return $neighborhood !== '' || $group !== 'shipping' ? $neighborhood : self::metaDefault($wcObject, 'billing_neighborhood', 'billing_neighborhood');
    }

    /**
     * @param mixed $value
     * @param mixed $wcObject
     */
    public static function defaultPersonType($value, string $group, $wcObject): string
    {
        unset($value);

        $personType = self::checkoutFieldDefault('petshop/person-type', $group, $wcObject);

        if ($personType === '') {
            $personType = self::metaDefault($wcObject, 'petshop_person_type', 'petshop_person_type');
        }

        if ($personType !== '') {
            return strtoupper($personType);
        }

        return match (self::metaDefault($wcObject, 'billing_persontype', 'billing_persontype')) {
            '1' => 'PF',
            '2' => 'PJ',
            default => '',
        };
    }

    /**
     * @param mixed $value
     * @param mixed $wcObject
     */
    public static function defaultDocument($value, string $group, $wcObject): string
    {
        unset($value);

        $document = self::checkoutFieldDefault('petshop/document', $group, $wcObject);

        if ($document === '') {
            $document = self::metaDefault($wcObject, 'petshop_document', 'petshop_document');
        }

        if ($document !== '') {
            return $document;
        }

        $cpf = self::metaDefault($wcObject, 'billing_cpf', 'billing_cpf');

        return $cpf !== '' ? $cpf : self::metaDefault($wcObject, 'billing_cnpj', 'billing_cnpj');
    }

    public static function clearTaggedSessionData(): void
    {
        $session = function_exists('WC') ? (WC()->session ?? null) : null;

        if (!is_object($session) || !method_exists($session, 'get')) {
            return;
        }

        if ((int) $session->get(self::SESSION_USER_KEY, 0) <= 0) {
            return;
        }

        self::clearBridgeSessionValues($session);
    }

    /**
     * @return list<string>
     */
    private static function billingFields(): array
    {
        return [
            'first_name',
            'last_name',
            'company',
            'country',
            'address_1',
            'address_2',
            'city',
            'state',
            'postcode',
            'phone',
            'email',
        ];
    }

    /**
     * @return list<string>
     */
    private static function shippingFields(): array
    {
        return [
            'first_name',
            'last_name',
            'company',
            'country',
            'address_1',
            'address_2',
            'city',
            'state',
            'postcode',
            'phone',
        ];
    }

    private static function setCustomerFieldIfEmpty(\WC_Customer $customer, string $type, string $field, string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $getter = 'get_' . $type . '_' . $field;
        $setter = 'set_' . $type . '_' . $field;

        if (!method_exists($customer, $getter) || !method_exists($customer, $setter)) {
            return false;
        }

        if ((string) $customer->{$getter}() !== '') {
            return false;
        }

        $customer->{$setter}($value);

        return true;
    }

    public static function sanitizeShortText($value): string
    {
        return sanitize_text_field(is_scalar($value) ? (string) $value : '');
    }

    public static function sanitizeDocument($value): string
    {
        return preg_replace('/[^0-9A-Za-z]/', '', strtoupper(self::sanitizeShortText($value))) ?? '';
    }

    public static function sanitizePersonType($value): string
    {
        return match (strtoupper(self::sanitizeShortText($value))) {
            'PF', '1', 'PHYSICAL' => 'PF',
            'PJ', '2', 'LEGAL' => 'PJ',
            default => '',
        };
    }

    private static function metaDefault($wcObject, string $key, ?string $userMetaKey = null): string
    {
        if (is_object($wcObject) && method_exists($wcObject, 'get_meta')) {
            $value = (string) $wcObject->get_meta($key);

            if ($value !== '') {
                return $value;
            }
        }

        $userId = is_user_logged_in() ? (int) get_current_user_id() : 0;

        return $userId > 0 && $userMetaKey !== null ? (string) get_user_meta($userId, $userMetaKey, true) : '';
    }

    /**
     * @return list<string>
     */
    private static function addressFieldsForResponse(string $group): array
    {
        $fields = [
            'first_name',
            'last_name',
            'company',
            'country',
            'address_1',
            'address_2',
            'city',
            'state',
            'postcode',
            'phone',
        ];

        if ($group === 'billing') {
            $fields[] = 'email';
        }

        return $fields;
    }

    private static function sessionValue(?object $session, string $key): string
    {
        if (!is_object($session) || !method_exists($session, 'get')) {
            return '';
        }

        $value = $session->get($key, '');

        return is_scalar($value) ? (string) $value : '';
    }

    private static function checkoutFieldsService(): ?object
    {
        $package = '\Automattic\WooCommerce\Blocks\Package';
        $checkoutFields = '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields';

        if (!class_exists($package) || !class_exists($checkoutFields) || !method_exists($package, 'container')) {
            return null;
        }

        try {
            $container = $package::container();
        } catch (\Throwable) {
            return null;
        }

        if (!is_object($container) || !method_exists($container, 'get')) {
            return null;
        }

        try {
            $service = $container->get($checkoutFields);
        } catch (\Throwable) {
            return null;
        }

        return is_object($service) ? $service : null;
    }

    private static function checkoutFieldDefault(string $key, string $group, $wcObject): string
    {
        $checkoutFields = self::checkoutFieldsService();

        if (!is_object($checkoutFields) || !method_exists($checkoutFields, 'get_field_from_object')) {
            return '';
        }

        $value = $checkoutFields->get_field_from_object($key, $wcObject, $group);

        return is_scalar($value) ? (string) $value : '';
    }

    private static function setCheckoutFieldValue(string $key, string $value, string $group, object $wcObject): void
    {
        $checkoutFields = self::checkoutFieldsService();

        if (!is_object($checkoutFields)) {
            return;
        }

        if (method_exists($checkoutFields, 'set_field_for_object')) {
            $checkoutFields->set_field_for_object($key, sanitize_text_field($value), $wcObject, $group);
            return;
        }

        if (method_exists($checkoutFields, 'set_field_from_object')) {
            $checkoutFields->set_field_from_object($key, sanitize_text_field($value), $wcObject, $group);
        }
    }

    private static function legacyAddressMetaKey(object $wcObject, string $group, string $field): string
    {
        $key = $group . '_' . $field;

        return $wcObject instanceof \WC_Order ? '_' . $key : $key;
    }

    private static function sessionAlreadyHydrated(int $userId): bool
    {
        if ($userId <= 0 || !function_exists('WC')) {
            return false;
        }

        $session = WC()->session ?? null;

        if (!is_object($session) || !method_exists($session, 'get')) {
            return false;
        }

        return (int) $session->get(self::SESSION_USER_KEY, 0) === $userId
            && (string) $session->get(self::SESSION_HYDRATED_KEY, '') === '1';
    }

    private static function markSessionHydrated(int $userId): void
    {
        if ($userId <= 0 || !function_exists('WC')) {
            return;
        }

        $session = WC()->session ?? null;

        if (!is_object($session) || !method_exists($session, 'set')) {
            return;
        }

        $session->set(self::SESSION_USER_KEY, (string) $userId);
        $session->set(self::SESSION_HYDRATED_KEY, '1');
    }

    private static function setSessionValueIfEmpty(object $session, string $key, string $value): void
    {
        if ($value === '' || (string) $session->get($key, '') !== '') {
            return;
        }

        $session->set($key, $value);
    }

    private static function clearBridgeSessionValues(object $session): void
    {
        if (!method_exists($session, 'set')) {
            return;
        }

        foreach ([
            'billing_number',
            'shipping_number',
            'billing_neighborhood',
            'shipping_neighborhood',
            'billing_persontype',
            'billing_document',
            'billing_cpf',
            'billing_cnpj',
            self::SESSION_USER_KEY,
            self::SESSION_HYDRATED_KEY,
        ] as $key) {
            $session->set($key, '');
        }
    }

    private static function normalizePersonType(string $personType): string
    {
        return match (strtoupper($personType)) {
            'PF', '1', 'PHYSICAL' => '1',
            'PJ', '2', 'LEGAL' => '2',
            default => '',
        };
    }
}
