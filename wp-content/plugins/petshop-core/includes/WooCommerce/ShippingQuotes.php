<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class ShippingQuotes
{
    private static int $quoteTimeoutDepth = 0;
    /**
     * @return array{
     *     rates: list<array{id: string, methodId: string, instanceId: int, label: string, displayLabel: string, carrierLabel: string, badge: string, cost: float, costText: string, deliveryEstimate: string}>,
     *     productionLead: string,
     *     transportNote: string
     * }
     */
    public static function quote(\WC_Product $product, string $postcode, int $quantity = 1): array
    {
        $originalCart = WC()->cart;
        $originalCustomer = WC()->customer;
        $originalSession = WC()->session;
        $shipping = WC()->shipping();
        $originalMethods = $shipping->shipping_methods;
        $originalPackages = $shipping->packages;
        $carrierSession = class_exists('\MelhorEnvio\Helpers\SessionHelper');
        $carrierCache = null;
        $hadCarrierCache = false;
        WC()->cart = clone $originalCart;
        WC()->customer = clone $originalCustomer;
        WC()->session = new class extends \WC_Session {};
        try {
            // Price and tax helpers must see the preview destination first.
            foreach (['shipping', 'billing'] as $group) {
                foreach (self::destinationFor($postcode) as $field => $value) {
                    $setter = 'set_' . $group . '_' . ($field === 'address' ? 'address_1' : $field);
                    WC()->customer->{$setter}($value);
                }
            }
            if ($carrierSession) {
                \MelhorEnvio\Helpers\SessionHelper::initIfNotExists();
                $hadCarrierCache = array_key_exists('quotation-melhor-envio', $_SESSION ?? []);
                $carrierCache = $_SESSION['quotation-melhor-envio'] ?? null;
                $_SESSION['quotation-melhor-envio'] = $_SESSION['petshop-preview-quotation-melhor-envio'] ?? [];
            }
            return self::quoteInContext(clone $product, $postcode, $quantity);
        } finally {
            if ($carrierSession) {
                $_SESSION['petshop-preview-quotation-melhor-envio'] = $_SESSION['quotation-melhor-envio'] ?? [];
                if ($hadCarrierCache) $_SESSION['quotation-melhor-envio'] = $carrierCache;
                else unset($_SESSION['quotation-melhor-envio']);
            }
            WC()->cart = $originalCart;
            WC()->customer = $originalCustomer;
            WC()->session = $originalSession;
            $shipping->shipping_methods = $originalMethods;
            $shipping->packages = $originalPackages;
        }
    }
    private static function quoteInContext(\WC_Product $product, string $postcode, int $quantity): array
    {
        $postcode = BrazilianPostcode::normalize($postcode);
        $quantity = max(1, $quantity);

        $price = (float) wc_get_price_excluding_tax($product);
        $inclusivePrice = (float) wc_get_price_including_tax($product);
        $content = [
            'key' => 'petshop_preview',
            'product_id' => $product->is_type('variation') ? $product->get_parent_id() : $product->get_id(),
            'variation_id' => $product->is_type('variation') ? $product->get_id() : 0,
            'variation' => $product->is_type('variation') ? $product->get_variation_attributes() : [],
            'data' => $product,
            'quantity' => $quantity,
            'line_total' => $price * $quantity,
            'line_subtotal' => $price * $quantity,
        ];
        $melhorEnvioData = self::melhorEnvioFormattedData($product, $quantity);
        if ($melhorEnvioData !== null) {
            $content['formatted_data'] = $melhorEnvioData;
        }

        $package = [
            'contents' => [$content],
            'contents_cost' => $price * $quantity,
            'applied_coupons' => [],
            'user' => ['ID' => get_current_user_id()],
            'destination' => self::destinationFor($postcode),
            'cart_subtotal' => $price * $quantity,
            'product_page_calculation' => true,
        ];

        self::$quoteTimeoutDepth++;
        add_filter('http_request_args', [self::class, 'limitMelhorEnvioQuoteTimeout'], 10, 2);
        $previewCart = WC()->cart;
        $previewCart->set_cart_contents(['petshop_preview' => $content]);
        $previewCart->set_removed_cart_contents([]);
        $previewCart->set_applied_coupons([]);
        $previewCart->set_coupon_discount_totals([]);
        $previewCart->set_coupon_discount_tax_totals([]);
        $previewCart->set_totals([]);
        $previewCart->set_subtotal($price * $quantity);
        $previewCart->set_subtotal_tax(($inclusivePrice - $price) * $quantity);
        $previewCart->set_cart_contents_total($price * $quantity);
        $previewCart->set_cart_contents_tax(($inclusivePrice - $price) * $quantity);
        try {
            // A named package key prevents WooCommerce from sharing the cart's
            // `shipping_for_package_0` session cache with this PDP-only preview.
            $preview = $product->needs_shipping()
                ? WC()->shipping()->calculate_shipping_for_package($package, 'petshop_preview') : [];
        } finally {
            remove_filter('http_request_args', [self::class, 'limitMelhorEnvioQuoteTimeout'], 10);
            self::$quoteTimeoutDepth = max(0, self::$quoteTimeoutDepth - 1);
        }
        $rates = [];

        foreach (($preview['rates'] ?? []) as $rate) {
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

    /**
     * Limits only Melhor Envio's quote endpoint while an isolated PDP preview
     * is running; labels, payments and every other request retain their own
     * configured timeout.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function limitMelhorEnvioQuoteTimeout(array $args, string $url): array
    {
        if (self::$quoteTimeoutDepth < 1) {
            return $args;
        }

        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        if (in_array($host, ['api.melhorenvio.com', 'sandbox.melhorenvio.com.br'], true)
            && in_array(rtrim($path, '/'), ['/v2/me/shipment/calculate', '/api/v2/me/shipment/calculate'], true)) {
            $args['timeout'] = 10;
        }

        return $args;
    }

    private static function melhorEnvioFormattedData(\WC_Product $product, int $quantity): ?object
    {
        if (!class_exists('\MelhorEnvio\Factory\ProductServiceFactory')) {
            return null;
        }

        try {
            $service = \MelhorEnvio\Factory\ProductServiceFactory::fromId($product->get_id());
            $formatted = $service->getProduct($product->get_id(), $quantity);
        } catch (\Throwable $error) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Petshop Melhor Envio product formatting failed: ' . $error->getMessage());
            }
            return null;
        }

        return is_object($formatted) ? $formatted : null;
    }

    /**
     * Destination for an isolated CEP-only preview. It is never persisted on
     * the WooCommerce customer or copied into a checkout address.
     *
     * @return array{country: string, state: string, postcode: string, city: string, address: string, address_2: string}
     */
    public static function destinationFor(string $postcode): array
    {
        $state = BrazilianPostcode::stateFromPostcode($postcode);

        return [
            'country' => 'BR',
            'state' => $state,
            'postcode' => $postcode,
            'city' => '',
            'address' => '',
            'address_2' => '',
        ];
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
