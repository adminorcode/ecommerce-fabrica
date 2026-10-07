<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Lets a CEP-only freight quote satisfy WooCommerce's shipping destination
 * without writing a street or city into the checkout address.
 *
 * The Store API refuses to calculate until the locale-required fields are
 * non-empty. For Brazil that includes state and city. The UF comes from the
 * Correios range. The city is visible only while totals are calculating.
 */
final class ShippingQuoteDestination
{
    private static int $calculating = 0;

    public static function bootstrap(): void
    {
        add_action('woocommerce_store_api_cart_update_customer_from_request', [self::class, 'applyStateFromRequest'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [self::class, 'beginCalculation'], 0);
        add_action('woocommerce_after_calculate_totals', [self::class, 'endCalculation'], PHP_INT_MAX);
        add_filter('woocommerce_customer_get_shipping_city', [self::class, 'cityDuringQuote'], 10, 2);
    }

    public static function applyStateFromRequest(\WC_Customer $customer, \WP_REST_Request $request): void
    {
        $shipping = $request->get_param('shipping_address');
        if (!is_array($shipping) || !self::shouldDeriveState($shipping)) {
            return;
        }

        $country = strtoupper(trim((string) ($shipping['country'] ?? '')));
        if ($country === '') {
            $country = strtoupper(trim((string) $customer->get_shipping_country()));
        }
        if ($country !== 'BR') {
            return;
        }

        $state = BrazilianPostcode::stateFromPostcode((string) ($shipping['postcode'] ?? ''));
        if ($state === '' || strtoupper(trim((string) $customer->get_shipping_state())) === $state) {
            return;
        }

        $customer->set_shipping_state($state);
    }

    /**
     * A CEP quote sends no UF, or still carries the UF of the previous CEP
     * with an empty street. A checkout address that already has street or
     * city keeps the state the customer sent.
     *
     * @param array<string, mixed> $shipping
     */
    public static function shouldDeriveState(array $shipping): bool
    {
        $postedState = strtoupper(trim((string) ($shipping['state'] ?? '')));
        $city = trim((string) ($shipping['city'] ?? ''));
        $address = trim((string) ($shipping['address_1'] ?? ''));

        return $postedState === '' || ($city === '' && $address === '');
    }

    public static function beginCalculation(mixed $cart = null): void
    {
        self::$calculating++;
    }

    public static function endCalculation(mixed $cart = null): void
    {
        self::$calculating = max(0, self::$calculating - 1);
    }

    public static function cityDuringQuote(mixed $city, \WC_Customer $customer): mixed
    {
        if (self::$calculating < 1) {
            return $city;
        }

        return self::transientCity(
            $city,
            (string) $customer->get_shipping_country(),
            (string) $customer->get_shipping_state(),
            (string) $customer->get_shipping_postcode()
        );
    }

    /**
     * City value used only while shipping totals are calculating.
     * An empty stored city stays empty in the checkout address.
     */
    public static function transientCity(mixed $city, string $country, string $state, string $postcode): mixed
    {
        if (is_string($city) && trim($city) !== '') {
            return $city;
        }

        if (strtoupper(trim($country)) !== 'BR') {
            return $city;
        }

        $derived = BrazilianPostcode::stateFromPostcode($postcode);
        if ($derived === '' || $derived !== strtoupper(trim($state))) {
            return $city;
        }

        return ' ';
    }
}
