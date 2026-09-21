<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class ShippingQuotes
{
    /**
     * @return array{
     *     rates: list<array{id: string, methodId: string, instanceId: int, label: string, displayLabel: string, carrierLabel: string, badge: string, cost: float, costText: string, deliveryEstimate: string}>,
     *     productionLead: string,
     *     transportNote: string
     * }
     */
    public static function quote(\WC_Product $product, string $postcode): array
    {
        self::persistPostcode($postcode);

        $price = (float) wc_get_price_to_display($product);
        $content = [
            'data' => $product,
            'quantity' => 1,
            'line_total' => $price,
            'line_subtotal' => $price,
        ];
        $melhorEnvioData = self::melhorEnvioFormattedData($product);
        if ($melhorEnvioData !== null) {
            $content['formatted_data'] = $melhorEnvioData;
        }

        $package = [
            'contents' => [$content],
            'contents_cost' => $price,
            'applied_coupons' => [],
            'user' => ['ID' => get_current_user_id()],
            'destination' => [
                'country' => 'BR',
                'state' => '',
                'postcode' => $postcode,
                'city' => '',
                'address' => '',
                'address_2' => '',
            ],
            'cart_subtotal' => $price,
            'product_page_calculation' => true,
        ];

        $packages = WC()->shipping()->calculate_shipping([$package]);
        $rates = [];

        foreach (($packages[0]['rates'] ?? []) as $rate) {
            if (!$rate instanceof \WC_Shipping_Rate) continue;
            $cost = self::rateCost($rate);
            $label = self::plainText($rate->get_label());
            $deliveryEstimate = self::normalizeDeliveryEstimate(self::deliveryEstimate($rate));
            if ($deliveryEstimate === '') $deliveryEstimate = self::deliveryEstimateFromLabel($label);
            $displayLabel = self::displayLabel($label);
            $rates[] = [
                'id' => $rate->get_id(),
                'methodId' => $rate->get_method_id(),
                'instanceId' => $rate->get_instance_id(),
                'label' => $label,
                'displayLabel' => $displayLabel,
                'carrierLabel' => self::carrierLabel($displayLabel),
                'badge' => '',
                'cost' => $cost,
                'costText' => self::formatMoney($cost),
                'deliveryEstimate' => $deliveryEstimate,
            ];
        }

        $rates = self::withBadges($rates);

        return [
            'rates' => $rates,
            'productionLead' => trim((string) $product->get_meta('_petshop_production_lead', true)),
            'transportNote' => __('O prazo de transporte é confirmado pelo método escolhido no carrinho e no checkout.', 'petshop-core'),
        ];
    }

    private static function melhorEnvioFormattedData(\WC_Product $product): ?object
    {
        if (!class_exists('\MelhorEnvio\Factory\ProductServiceFactory')) {
            return null;
        }

        try {
            $service = \MelhorEnvio\Factory\ProductServiceFactory::fromId($product->get_id());
            $formatted = $service->getProduct($product->get_id(), 1);
        } catch (\Throwable $error) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Petshop Melhor Envio product formatting failed: ' . $error->getMessage());
            }
            return null;
        }

        return is_object($formatted) ? $formatted : null;
    }

    private static function persistPostcode(string $postcode): void
    {
        if (!function_exists('WC') || !WC()->customer) return;

        WC()->customer->set_shipping_country('BR');
        WC()->customer->set_shipping_postcode($postcode);

        if (WC()->session) {
            WC()->session->set_customer_session_cookie(true);
            $customer = (array) WC()->session->get('customer', []);
            $customer['shipping_country'] = 'BR';
            $customer['shipping_postcode'] = $postcode;
            WC()->session->set('shipping_country', 'BR');
            WC()->session->set('shipping_postcode', $postcode);
            WC()->session->set('customer', $customer);
            WC()->session->set('petshop_shipping_postcode', $postcode);
        }
    }

    private static function rateCost(\WC_Shipping_Rate $rate): float
    {
        $taxes = array_sum(array_map('floatval', $rate->get_taxes()));
        return (float) $rate->get_cost() + (get_option('woocommerce_tax_display_cart') === 'incl' ? $taxes : 0.0);
    }

    private static function formatMoney(float $cost): string
    {
        return self::plainText(wc_price($cost));
    }

    private static function plainText(string $text): string
    {
        $decoded = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $decoded = str_replace("\xc2\xa0", ' ', $decoded);
        return trim((string) preg_replace('/\s+/', ' ', $decoded));
    }

    private static function displayLabel(string $label): string
    {
        $displayLabel = self::removeDeliveryEstimateFromLabel($label);
        $displayLabel = (string) preg_replace('/\s*\((?:Melhor\s+Envio|[0-9]+)\)\s*/iu', ' ', $displayLabel);
        $displayLabel = (string) preg_replace('/\bJadlog\s+Package\b/iu', 'Jadlog', $displayLabel);
        $displayLabel = (string) preg_replace('/\bCorreios\s+(Sedex|Pac)\b/iu', '$1', $displayLabel);
        $displayLabel = self::plainText($displayLabel);
        return $displayLabel !== '' ? $displayLabel : self::plainText($label);
    }

    private static function carrierLabel(string $displayLabel): string
    {
        $plain = self::plainText($displayLabel);
        $lower = strtolower(remove_accents($plain));
        if (str_contains($lower, 'sedex')) return 'SEDEX';
        if (str_contains($lower, 'pac')) return 'PAC';
        if (str_contains($lower, 'jadlog')) return 'Jadlog';
        if (str_contains($lower, 'correios')) return 'Correios';
        return $plain;
    }

    private static function removeDeliveryEstimateFromLabel(string $label): string
    {
        return self::plainText((string) preg_replace('/\s*\([^)]*\b(?:dia|dias|uteis|úteis)\b[^)]*\)\s*/iu', ' ', $label));
    }

    private static function deliveryEstimateFromLabel(string $label): string
    {
        if (preg_match('/\(([^)]*\b(?:dia|dias|uteis|úteis)\b[^)]*)\)/iu', $label, $matches) !== 1) return '';
        return self::normalizeDeliveryEstimate((string) $matches[1]);
    }

    private static function normalizeDeliveryEstimate(string $estimate): string
    {
        $estimate = trim($estimate);
        if ($estimate === '') return '';
        $estimate = trim($estimate, " \t\n\r\0\x0B()");
        if (ctype_digit($estimate)) {
            $days = (int) $estimate;
            return sprintf(_n('%d dia útil', '%d dias úteis', $days, 'petshop-core'), $days);
        }
        return self::plainText($estimate);
    }

    private static function deliveryEstimate(\WC_Shipping_Rate $rate): string
    {
        foreach ($rate->get_meta_data() as $key => $value) {
            $keyText = strtolower(remove_accents((string) $key));
            if (!str_contains($keyText, 'prazo') && !str_contains($keyText, 'delivery') && !str_contains($keyText, 'estim')) continue;
            if (is_array($value) || is_object($value)) continue;
            $estimate = self::normalizeDeliveryEstimate((string) $value);
            if ($estimate !== '') return $estimate;
        }
        return '';
    }

    /**
     * @param list<array{id: string, methodId: string, instanceId: int, label: string, displayLabel: string, carrierLabel: string, badge: string, cost: float, costText: string, deliveryEstimate: string}> $rates
     * @return list<array{id: string, methodId: string, instanceId: int, label: string, displayLabel: string, carrierLabel: string, badge: string, cost: float, costText: string, deliveryEstimate: string}>
     */
    private static function withBadges(array $rates): array
    {
        if ($rates === []) return $rates;

        $cheapestIndex = null;
        $cheapestCost = null;
        $fastestIndex = null;
        $fastestDays = null;

        foreach ($rates as $index => $rate) {
            $cost = (float) $rate['cost'];
            if ($cheapestCost === null || $cost < $cheapestCost) {
                $cheapestCost = $cost;
                $cheapestIndex = $index;
            }

            $days = self::minimumDays((string) $rate['deliveryEstimate']);
            if ($days !== null && ($fastestDays === null || $days < $fastestDays)) {
                $fastestDays = $days;
                $fastestIndex = $index;
            }
        }

        if ($cheapestIndex !== null) $rates[$cheapestIndex]['badge'] = __('Mais econômica', 'petshop-core');
        if ($fastestIndex !== null && $fastestIndex !== $cheapestIndex) $rates[$fastestIndex]['badge'] = __('Mais rápida', 'petshop-core');

        return $rates;
    }

    private static function minimumDays(string $estimate): ?int
    {
        if (preg_match_all('/\d+/', $estimate, $matches) < 1) return null;
        $numbers = array_map('intval', $matches[0]);
        return min($numbers);
    }
}
