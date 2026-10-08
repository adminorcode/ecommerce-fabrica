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
 * Correios range. The city exists only on the shipping package, never as a
 * stored checkout address. A freight quote does not change cart quantities.
 */
final class ShippingQuoteDestination
{
    private static int $calculating = 0;

    private static int $quoting = 0;

    /**
     * Quantities loaded before a freight quote. Restored if that quote changes them.
     *
     * @var array<string, int>|null
     */
    private static ?array $quantitySnapshot = null;

    public static function bootstrap(): void
    {
        add_action('woocommerce_store_api_cart_update_customer_from_request', [self::class, 'keepCartQuantities'], 0, 2);
        add_action('woocommerce_store_api_cart_update_customer_from_request', [self::class, 'applyStateFromRequest'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [self::class, 'beginCalculation'], 0);
        add_action('woocommerce_after_calculate_totals', [self::class, 'endCalculation'], PHP_INT_MAX);
        add_action('woocommerce_after_calculate_totals', [self::class, 'restoreCartQuantities'], PHP_INT_MAX);
        add_filter('woocommerce_customer_get_shipping_city', [self::class, 'cityDuringQuote'], 10, 2);
        add_filter('woocommerce_cart_shipping_packages', [self::class, 'fillPackageCity'], 9998);
        add_filter('woocommerce_shipping_packages', [self::class, 'beginPackageRead']);
        add_filter('woocommerce_shipping_packages', [self::class, 'endPackageRead'], PHP_INT_MAX);
    }

    /**
     * The Store API reads rates again after totals close, then hides them when
     * the address is incomplete. The transient city stays available only for
     * that read.
     *
     * @param array<int, mixed> $packages
     * @return array<int, mixed>
     */
    public static function beginPackageRead(array $packages): array
    {
        self::$quoting++;

        return $packages;
    }

    /**
     * @param array<int, mixed> $packages
     * @return array<int, mixed>
     */
    public static function endPackageRead(array $packages): array
    {
        self::$quoting = max(0, self::$quoting - 1);

        return $packages;
    }

    /**
     * The freight request may only change the destination. Cart lines stay as they were.
     */
    public static function keepCartQuantities(\WC_Customer $customer, \WP_REST_Request $request): void
    {
        unset($customer, $request);
        $cart = WC()->cart;
        if (!$cart instanceof \WC_Cart) {
            self::$quantitySnapshot = null;

            return;
        }

        $snapshot = [];
        foreach ($cart->get_cart() as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            $snapshot[(string) $key] = (int) ($item['quantity'] ?? 0);
        }
        self::$quantitySnapshot = $snapshot;
    }

    /**
     * Puts the transient city on the package after totals close.
     *
     * The Store API calculates rates again while building the response, after
     * the customer city has returned to empty. Without this, that second pass
     * drops every rate even though the cart total already includes freight.
     *
     * @param array<int, array<string, mixed>> $packages
     * @return array<int, array<string, mixed>>
     */
    public static function fillPackageCity(array $packages): array
    {
        foreach ($packages as $index => $package) {
            if (!is_array($package) || !is_array($package['destination'] ?? null)) {
                continue;
            }
            $destination = $package['destination'];
            $destination['city'] = self::transientCity(
                $destination['city'] ?? '',
                (string) ($destination['country'] ?? ''),
                (string) ($destination['state'] ?? ''),
                (string) ($destination['postcode'] ?? '')
            );
            $packages[$index]['destination'] = $destination;
        }

        return $packages;
    }

    public static function restoreCartQuantities(mixed $cart = null): void
    {
        $snapshot = self::$quantitySnapshot;
        self::$quantitySnapshot = null;
        if ($snapshot === null) {
            return;
        }

        $current = $cart instanceof \WC_Cart ? $cart : WC()->cart;
        if (!$current instanceof \WC_Cart) {
            return;
        }

        foreach ($snapshot as $key => $quantity) {
            $item = $current->get_cart_item($key);
            if (!is_array($item) || (int) ($item['quantity'] ?? 0) === $quantity) {
                continue;
            }
            $current->set_quantity($key, $quantity, false);
        }
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
        if (self::$calculating < 1 && self::$quoting < 1) {
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
