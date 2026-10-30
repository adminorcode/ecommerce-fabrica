<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Normalizes the small, CEP-only destination used by the Cart Block extension.
 *
 * It never fabricates a city, restores cart items, or hooks shipping packages.
 * WooCommerce owns package construction, rates and selected methods.
 */
final class ShippingQuoteDestination
{
    /**
     * @return array{country: string, state: string, postcode: string}
     */
    public static function forPostcode(string $postcode): array
    {
        $postcode = BrazilianPostcode::normalize($postcode);
        $state = BrazilianPostcode::stateFromPostcode($postcode);

        if ($postcode === '' || $state === '') {
            return ['country' => '', 'state' => '', 'postcode' => ''];
        }

        return ['country' => 'BR', 'state' => $state, 'postcode' => $postcode];
    }
}
