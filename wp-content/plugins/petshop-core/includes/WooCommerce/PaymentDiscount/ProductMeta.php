<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce\PaymentDiscount;

defined('ABSPATH') || exit;

final class ProductMeta
{
    public const META = '_petshop_payment_discount_percent';
    public const FIELD = '_petshop_payment_discount_percent';
    private const NONCE_ACTION = 'woocommerce_save_data';

    public static function bootstrap(): void
    {
        add_action('woocommerce_product_options_general_product_data', [self::class, 'renderProductField'], 9);
        add_action('woocommerce_process_product_meta', [self::class, 'saveProduct']);
        add_action('woocommerce_variation_options_pricing', [self::class, 'renderVariationField'], 10, 3);
        add_action('woocommerce_save_product_variation', [self::class, 'saveVariation'], 10, 2);
    }

    public static function renderProductField(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        woocommerce_wp_text_input([
            'id' => self::FIELD,
            'label' => __('Desconto por meio de pagamento (%)', 'petshop-core'),
            'description' => __('De 0 a 100, com até duas casas. No produto variável, este é o valor do pai: variação vazia ou 0 herda daqui; maior que 0 substitui.', 'petshop-core'),
            'desc_tip' => true,
            'type' => 'text',
            'wrapper_class' => 'show_if_simple show_if_variable show_if_external',
        ]);
    }

    /**
     * @param array<string, mixed> $variationData
     */
    public static function renderVariationField(int $loop, array $variationData, \WP_Post $variation): void
    {
        unset($variationData);
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        woocommerce_wp_text_input([
            'id' => 'petshop_payment_discount_percent_' . $loop,
            'name' => 'petshop_payment_discount_percent[' . $loop . ']',
            'value' => (string) get_post_meta($variation->ID, self::META, true),
            'label' => __('Desconto por meio de pagamento (%)', 'petshop-core'),
            'description' => __('Vazio ou 0 herda o produto. Maior que 0 substitui o pai.', 'petshop-core'),
            'desc_tip' => true,
            'type' => 'text',
            'wrapper_class' => 'form-row form-row-full',
        ]);
    }

    public static function saveProduct(int $productId): void
    {
        if (!self::canSave($productId) || !isset($_POST[self::FIELD])) {
            return;
        }

        $posted = $_POST[self::FIELD];
        $raw = is_scalar($posted) ? wp_unslash((string) $posted) : '';
        if (self::persist($productId, $raw) === 'rejected') {
            self::rejectNotice();
        }
    }

    public static function saveVariation(int $variationId, int $loop): void
    {
        if (!self::canSave($variationId) || !isset($_POST['petshop_payment_discount_percent'][$loop])) {
            return;
        }

        $rawValue = $_POST['petshop_payment_discount_percent'][$loop];
        $raw = is_scalar($rawValue) ? wp_unslash((string) $rawValue) : '';
        if (self::persist($variationId, $raw) === 'rejected') {
            self::rejectNotice();
        }
    }

    public static function persist(int $productId, string $raw): string
    {
        $parsed = Percent::parse($raw);
        if ($parsed['status'] === 'invalid') {
            return 'rejected';
        }

        if ($parsed['status'] === 'empty' || (float) $parsed['value'] <= 0) {
            delete_post_meta($productId, self::META);

            return 'cleared';
        }

        update_post_meta($productId, self::META, $parsed['value']);

        return 'saved';
    }

    public static function effectivePercent(\WC_Product $product): string
    {
        $own = (string) $product->get_meta(self::META, true);
        if ($product->is_type('variation')) {
            $parent = wc_get_product($product->get_parent_id());
            $parentValue = $parent instanceof \WC_Product ? (string) $parent->get_meta(self::META, true) : '';

            return Percent::effective($own, $parentValue);
        }

        return Percent::effective($own, '');
    }

    public static function rejectNotice(): void
    {
        $message = __('Informe uma porcentagem de 0 a 100 com no máximo duas casas decimais.', 'petshop-core');
        if (class_exists(\WC_Admin_Meta_Boxes::class)) {
            \WC_Admin_Meta_Boxes::add_error($message);
        }
    }

    private static function canSave(int $productId): bool
    {
        if ($productId <= 0 || !current_user_can('manage_woocommerce')) {
            return false;
        }

        $metaNonce = isset($_POST['woocommerce_meta_nonce']) && is_scalar($_POST['woocommerce_meta_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['woocommerce_meta_nonce']))
            : '';
        if ($metaNonce !== '' && wp_verify_nonce($metaNonce, self::NONCE_ACTION)) {
            return true;
        }

        // Variations are saved by admin-ajax (save-variations), which does not send woocommerce_meta_nonce.
        $ajaxNonce = isset($_POST['security']) && is_scalar($_POST['security'])
            ? sanitize_text_field(wp_unslash((string) $_POST['security']))
            : '';

        return $ajaxNonce !== '' && wp_verify_nonce($ajaxNonce, 'save-variations');
    }
}
