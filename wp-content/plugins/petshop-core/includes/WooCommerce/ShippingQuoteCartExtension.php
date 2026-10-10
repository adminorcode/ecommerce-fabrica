<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

/** Store API endpoint for the CEP-only quote requested by the Cart Block. */
final class ShippingQuoteCartExtension
{
    public const NAMESPACE = 'petshop-shipping-quote';
    private const MIGRATION_OPTION = 'petshop_shipping_quote_destination_v1';

    public static function bootstrap(): void
    {
        add_action('woocommerce_blocks_loaded', [self::class, 'registerCallback']);
        add_action('woocommerce_init', [self::class, 'migrateEarlyShippingSetting'], 20);
        add_action('woocommerce_store_api_cart_update_customer_from_request', [self::class, 'markAddressIntent'], 10, 2);
        add_action('woocommerce_load_cart_from_session', [self::class, 'restoreQuoteDestination'], 1);
    }

    public static function registerCallback(): void
    {
        if (!function_exists('woocommerce_store_api_register_update_callback')) {
            return;
        }

        woocommerce_store_api_register_update_callback([
            'namespace' => self::NAMESPACE,
            'callback' => [self::class, 'updateDestination'],
        ]);
        woocommerce_store_api_register_endpoint_data([
            'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
            'namespace' => self::NAMESPACE,
            'data_callback' => static fn (): array => [
                'origin' => (string) WC()->session?->get('petshop_shipping_destination_origin', ''),
                'requires_address' => get_option('woocommerce_shipping_cost_requires_address', 'yes') === 'yes',
            ],
            'schema_callback' => static fn (): array => [
                'origin' => ['type' => 'string', 'readonly' => true],
                'requires_address' => ['type' => 'boolean', 'readonly' => true],
            ],
            'schema_type' => ARRAY_A,
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function updateDestination(array $data): void
    {
        $action = sanitize_key(is_scalar($data['action'] ?? null) ? (string) $data['action'] : '');
        $postcode = BrazilianPostcode::normalize(is_scalar($data['postcode'] ?? null) ? (string) $data['postcode'] : '');

        if ($action !== 'set_quote_destination' || $postcode === '') {
            throw new \WC_REST_Exception('petshop_invalid_shipping_quote', __('Informe um CEP válido para calcular a entrega.', 'petshop-core'), 400);
        }

        $destination = ShippingQuoteDestination::forPostcode($postcode);
        if ($destination['state'] === '') {
            throw new \WC_REST_Exception('petshop_invalid_shipping_quote', __('Não foi possível identificar a UF deste CEP.', 'petshop-core'), 400);
        }

        $customer = WC()->customer;
        if (!$customer instanceof \WC_Customer) {
            throw new \WC_REST_Exception('petshop_missing_customer', __('Não foi possível atualizar o destino da entrega.', 'petshop-core'), 409);
        }

        $session = WC()->session;
        if (BrazilianPostcode::normalize((string) $customer->get_shipping_postcode()) === $postcode
            && trim((string) $customer->get_shipping_address_1()) !== ''
            && trim((string) $customer->get_shipping_city()) !== '') {
            return;
        }
        $customer->set_shipping_country($destination['country']);
        $customer->set_shipping_state($destination['state']);
        $customer->set_shipping_postcode($destination['postcode']);
        // A CEP-only quote must never combine its UF/CEP with geographic
        // fields that belong to a previous, different address.
        $customer->set_shipping_city('');
        $customer->set_shipping_address_1('');
        $customer->set_shipping_address_2('');
        $customer->update_meta_data('shipping_neighborhood', '');
        $customer->update_meta_data('_wc_shipping/petshop/neighborhood', '');
        if (wc_ship_to_billing_address_only()) {
            foreach (['country', 'state', 'postcode'] as $field) {
                $setter = 'set_billing_' . $field;
                $customer->{$setter}($destination[$field]);
            }
            $customer->set_billing_city('');
            $customer->set_billing_address_1('');
            $customer->set_billing_address_2('');
        }
        if (is_object($session) && method_exists($session, 'set')) {
            $session->set('petshop_shipping_destination_origin', 'quote');
            $session->set('petshop_shipping_destination_postcode', $destination['postcode']);
            $session->set('shipping_neighborhood', '');
        }
    }

    public static function markAddressIntent(\WC_Customer $customer, \WP_REST_Request $request): void
    {
        $group = wc_ship_to_billing_address_only() ? 'billing' : 'shipping';
        $address = $request->get_param($group . '_address');
        if (!is_array($address) || !array_intersect(array_keys($address), ['country', 'state', 'postcode', 'city', 'address_1', 'address_2'])) return;
        $snapshot = [];
        foreach (['country', 'state', 'postcode', 'city', 'address_1', 'address_2'] as $field) {
            $getter = 'get_' . $group . '_' . $field;
            $snapshot[$field] = (string) $customer->{$getter}();
        }
        // Empty geographic fields are also a confirmed address edit. Contact-only
        // requests were excluded above and cannot replace this snapshot.
        WC()->session?->set('petshop_shipping_destination_origin', 'address');
        WC()->session?->set('petshop_shipping_destination_address', $snapshot);
        $getter = 'get_' . $group . '_postcode';
        WC()->session?->set('petshop_shipping_destination_postcode', BrazilianPostcode::normalize((string) $customer->{$getter}()));
    }

    /** Reapply quote invalidation when the customer datastore reloads saved, nonempty account fields. */
    public static function restoreQuoteDestination(): void
    {
        $origin = WC()->session?->get('petshop_shipping_destination_origin', '');
        if (!in_array($origin, ['quote', 'address'], true)) return;
        $postcode = (string) WC()->session->get('petshop_shipping_destination_postcode', '');
        $destination = ShippingQuoteDestination::forPostcode($postcode);
        $customer = WC()->customer;
        if (!$customer instanceof \WC_Customer || ($origin === 'quote' && $destination['postcode'] === '')) return;
        foreach (wc_ship_to_billing_address_only() ? ['shipping', 'billing'] : ['shipping'] as $group) {
            if ($origin === 'address') {
                $snapshot = WC()->session->get('petshop_shipping_destination_address', []);
                foreach (['country', 'state', 'postcode', 'city', 'address_1', 'address_2'] as $field) {
                    if (!is_array($snapshot) || !array_key_exists($field, $snapshot)) continue;
                    $setter = 'set_' . $group . '_' . $field;
                    $customer->{$setter}((string) $snapshot[$field]);
                }
                continue;
            }
            foreach ($destination as $field => $value) {
                $setter = 'set_' . $group . '_' . $field;
                $customer->{$setter}($value);
            }
            foreach (['city', 'address_1', 'address_2'] as $field) {
                $setter = 'set_' . $group . '_' . $field;
                $customer->{$setter}('');
            }
            $customer->update_meta_data($group . '_neighborhood', '');
            $customer->update_meta_data('_wc_' . $group . '/petshop/neighborhood', '');
            WC()->session->set($group . '_neighborhood', '');
        }
    }

    /** Enables CEP-only estimates once, preserving later admin decisions. */
    public static function migrateEarlyShippingSetting(): void
    {
        if (get_option(self::MIGRATION_OPTION, '') !== '') {
            return;
        }

        $previous = (string) get_option('woocommerce_shipping_cost_requires_address', 'yes');
        update_option('woocommerce_shipping_cost_requires_address', 'no');
        update_option(self::MIGRATION_OPTION, [
            'version' => 1,
            'previous_value' => $previous,
            'applied_at' => time(),
        ], false);
    }
}
