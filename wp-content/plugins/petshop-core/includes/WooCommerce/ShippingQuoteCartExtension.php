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
        $origin = is_object($session) && method_exists($session, 'get')
            ? (string) $session->get('petshop_shipping_destination_origin', '')
            : '';
        if ($origin !== 'quote' && self::hasCompleteShippingAddress($customer)) {
            throw new \WC_REST_Exception(
                'petshop_shipping_address_is_current',
                __('Atualize o endereço no checkout para calcular uma nova entrega.', 'petshop-core'),
                409
            );
        }

        $customer->set_shipping_country($destination['country']);
        $customer->set_shipping_state($destination['state']);
        $customer->set_shipping_postcode($destination['postcode']);
        // A CEP-only quote must never combine its UF/CEP with geographic
        // fields that belong to a previous, different address.
        $customer->set_shipping_city('');
        $customer->set_shipping_address_1('');
        $customer->set_shipping_address_2('');
        if (is_object($session) && method_exists($session, 'set')) {
            $session->set('petshop_shipping_destination_origin', 'quote');
            $session->set('petshop_shipping_destination_postcode', $destination['postcode']);
        }
    }

    private static function hasCompleteShippingAddress(\WC_Customer $customer): bool
    {
        return trim((string) $customer->get_shipping_address_1()) !== ''
            && trim((string) $customer->get_shipping_city()) !== ''
            && trim((string) $customer->get_shipping_state()) !== ''
            && BrazilianPostcode::normalize((string) $customer->get_shipping_postcode()) !== '';
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
