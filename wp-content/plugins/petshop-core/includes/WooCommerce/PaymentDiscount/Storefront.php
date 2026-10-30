<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce\PaymentDiscount;

defined('ABSPATH') || exit;

final class Storefront
{
    public static function bootstrap(): void
    {
        add_filter('woocommerce_get_price_html', [self::class, 'appendPriceHtml'], 30, 2);
        add_filter('woocommerce_available_variation', [self::class, 'extendVariation'], 20, 3);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueProductScript']);
    }

    public static function appendPriceHtml(string $html, \WC_Product $product): string
    {
        if (!Settings::hasGateways()) {
            return $html;
        }

        if ($product->is_type('variation')) {
            return (float) ProductMeta::effectivePercent($product) > 0
                ? self::withoutProductDiscount($html)
                : $html;
        }

        if (self::isPrimarySimpleSummary($product)) {
            return $html;
        }

        // The card already shows the selected variation price, including that variation's discount.
        if (str_contains($html, 'data-petshop-card-price')) {
            return $html;
        }

        $extra = self::markup($product, true);
        if ($extra === '') {
            return $html;
        }

        return self::withoutProductDiscount($html) . $extra;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function extendVariation(array $data, \WC_Product_Variable $product, \WC_Product_Variation $variation): array
    {
        unset($product);
        // WooCommerce leaves price_html empty when every variation shares the parent price.
        // The selected variation still needs a visible price (and a .price node for the Pix line).
        if (!isset($data['price_html']) || !is_string($data['price_html']) || $data['price_html'] === '') {
            $data['price_html'] = '<span class="price">' . $variation->get_price_html() . '</span>';
        }

        $data['petshop_payment_discount_unit'] = '';
        $data['petshop_payment_discount_label'] = '';
        $data['petshop_payment_discount_html'] = '';
        if (Settings::hasGateways()) {
            $percent = ProductMeta::effectivePercent($variation);
            $display = function_exists('wc_get_price_to_display') ? wc_get_price_to_display($variation) : $variation->get_price();
            $html = self::markup($variation, false);
            if ((float) $percent > 0 && is_numeric($display) && (float) $display > 0.0) {
                $data['petshop_payment_discount_unit'] = Money::discounted(
                    wc_format_decimal((string) $display, wc_get_price_decimals()),
                    $percent
                );
                $data['petshop_payment_discount_label'] = Settings::label();
            }
            if ($html !== '') {
                $data['petshop_payment_discount_html'] = $html;
            }
        }

        return $data;
    }

    public static function enqueueProductScript(): void
    {
        if (is_admin() || !function_exists('is_product') || !is_product()) {
            return;
        }

        $relative = 'assets/js/payment-discount-product.js';
        $path = plugin_dir_path(PETSHOP_CORE_FILE) . $relative;
        $dependencies = ['jquery'];
        if (wp_script_is('wc-add-to-cart-variation', 'registered')) {
            $dependencies[] = 'wc-add-to-cart-variation';
        }

        wp_enqueue_script(
            'petshop-payment-discount-product',
            plugins_url($relative, PETSHOP_CORE_FILE),
            $dependencies,
            is_file($path) ? (string) filemtime($path) : '1.0.0',
            true
        );
    }

    public static function markup(\WC_Product $product, bool $range): string
    {
        $inner = $range && $product->is_type('variable')
            ? self::variableInner($product)
            : self::singleInner($product);
        if ($inner === '') {
            return '';
        }

        $from = $range && $product->is_type('variable');

        return '<span class="petshop-payment-discount" data-petshop-payment-discount'
            . ($from ? ' data-petshop-payment-discount-from="1"' : '')
            . '>' . $inner . '</span>';
    }

    private static function singleInner(\WC_Product $product): string
    {
        $percent = ProductMeta::effectivePercent($product);
        if ((float) $percent <= 0) {
            return '';
        }

        $price = (string) $product->get_price();
        if ($price === '' || (float) $price <= 0.0) {
            return '';
        }

        return self::amountHtml(Money::discounted($price, $percent), false);
    }

    private static function variableInner(\WC_Product $product): string
    {
        if (!$product instanceof \WC_Product_Variable) {
            return '';
        }

        // Without a parent percentage, another variation's discount must stay hidden until one is selected.
        if ((float) ProductMeta::effectivePercent($product) <= 0) {
            return '';
        }

        $minors = [];
        foreach ($product->get_children() as $variationId) {
            $variation = wc_get_product($variationId);
            if (!$variation instanceof \WC_Product_Variation || $variation->get_status() !== 'publish') {
                continue;
            }
            if (!$variation->is_purchasable() || !$variation->is_in_stock()) {
                continue;
            }
            $percent = ProductMeta::effectivePercent($variation);
            if ((float) $percent <= 0) {
                continue;
            }
            $price = (string) $variation->get_price();
            if ($price === '' || (float) $price <= 0.0) {
                continue;
            }
            $minors[] = Money::toMinor(Money::discounted($price, $percent));
        }

        if ($minors === []) {
            return '';
        }

        $unique = count(array_unique($minors)) === 1;

        return self::amountHtml(Money::fromMinor(min($minors)), !$unique);
    }

    private static function amountHtml(string $amount, bool $from): string
    {
        $prefix = '';
        if ($from && function_exists('wc_get_price_html_from_text')) {
            $prefix = wp_kses_post(wc_get_price_html_from_text());
        }

        $price = function_exists('wc_price') ? wp_kses_post(wc_price($amount)) : esc_html($amount);

        return '<span class="petshop-payment-discount__amount">' . $prefix . $price . '</span> '
            . '<span class="petshop-payment-discount__label">' . esc_html(Settings::label()) . '</span>';
    }

    private static function isPrimarySimpleSummary(\WC_Product $product): bool
    {
        if ($product->is_type(['variable', 'variation', 'grouped'])) {
            return false;
        }
        if (!function_exists('is_product') || !is_product() || (int) get_queried_object_id() !== $product->get_id()) {
            return false;
        }
        if (function_exists('wc_get_loop_prop')) {
            $loopName = wc_get_loop_prop('name');
            if (is_string($loopName) && $loopName !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * The crossed-out promotional price stays out when the payment price is shown.
     */
    private static function withoutProductDiscount(string $html): string
    {
        $stripped = preg_replace(
            '/<del\b[^>]*>.*?<\/del>\s*(?:<span class="screen-reader-text">.*?<\/span>)?/is',
            '',
            $html
        );

        return is_string($stripped) ? $stripped : $html;
    }
}
