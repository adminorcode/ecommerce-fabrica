<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class ShippingQuotes
{
    /**
     * @return array{
     *     rates: list<array{id: string, methodId: string, instanceId: int, label: string, cost: float, costText: string, deliveryEstimate: string}>,
     *     productionLead: string,
     *     transportNote: string
     * }
     */
    public static function quote(\WC_Product $product, string $postcode): array
    {
        self::persistPostcode($postcode);

        $price = (float) wc_get_price_to_display($product);
        $package = [
            'contents' => [[
                'data' => $product,
                'quantity' => 1,
                'line_total' => $price,
                'line_subtotal' => $price,
            ]],
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
            $rates[] = [
                'id' => $rate->get_id(),
                'methodId' => $rate->get_method_id(),
                'instanceId' => $rate->get_instance_id(),
                'label' => self::plainText($rate->get_label()),
                'cost' => $cost,
                'costText' => self::formatMoney($cost),
                'deliveryEstimate' => self::deliveryEstimate($rate),
            ];
        }

        return [
            'rates' => $rates,
            'productionLead' => trim((string) $product->get_meta('_petshop_production_lead', true)),
            'transportNote' => __('O prazo de transporte é confirmado pelo método escolhido no carrinho e no checkout.', 'petshop-core'),
        ];
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

    private static function deliveryEstimate(\WC_Shipping_Rate $rate): string
    {
        foreach ($rate->get_meta_data() as $key => $value) {
            $keyText = strtolower(remove_accents((string) $key));
            if (!str_contains($keyText, 'prazo') && !str_contains($keyText, 'delivery') && !str_contains($keyText, 'estim')) continue;
            if (is_array($value) || is_object($value)) continue;
            $estimate = self::plainText((string) $value);
            if ($estimate !== '') return $estimate;
        }
        return '';
    }
}
