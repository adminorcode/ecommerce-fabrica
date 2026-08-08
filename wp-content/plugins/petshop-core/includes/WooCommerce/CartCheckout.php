<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class CartCheckout
{
    private const LOCAL_ZONE_NAME = 'Brasil (desenvolvimento)';

    public static function bootstrap(): void
    {
        add_filter('body_class', [self::class, 'bodyClasses']);
    }

    public static function migrate(): void
    {
        self::migrateBlockPages();
        self::configureAccountOptions();
        self::ensurePolicyPages();
        self::ensureLocalShippingMethod();
    }

    /** @param list<string> $classes @return list<string> */
    public static function bodyClasses(array $classes): array
    {
        if (is_checkout() && !is_order_received_page()) $classes[] = 'petshop-distraction-free-checkout';
        return $classes;
    }

    private static function migrateBlockPages(): void
    {
        $cartId = (int) get_option('woocommerce_cart_page_id');
        $cartAppend = '<!-- wp:group {"className":"petshop-cart-continue","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-cart-continue"><!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Continue explorando a loja</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Você pode voltar ao catálogo sem perder os itens do carrinho.</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="' . esc_url(wc_get_page_permalink('shop')) . '">Continuar comprando</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->';
        self::appendOnce($cartId, 'wp:woocommerce/cart', 'petshop-cart-continue', $cartAppend, '_petshop_cart_013_hash', [
            'Your cart is currently empty!' => 'Seu carrinho está vazio!',
            'New in store' => 'Novidades da loja',
            'You may be interested in&hellip;' => 'Talvez você também goste&hellip;',
        ]);

        $checkoutId = (int) get_option('woocommerce_checkout_page_id');
        $checkoutAppend = '<!-- wp:paragraph {"className":"petshop-checkout-return"} --><p class="petshop-checkout-return"><a href="' . esc_url(wc_get_page_permalink('cart')) . '">← Voltar ao carrinho</a></p><!-- /wp:paragraph -->';
        self::prependOnce($checkoutId, 'wp:woocommerce/checkout', 'petshop-checkout-return', $checkoutAppend, '_petshop_checkout_013_hash');
    }

    /** @param array<string, string> $replacements */
    private static function appendOnce(int $pageId, string $requiredMarker, string $insertedMarker, string $append, string $hashKey, array $replacements = []): void
    {
        self::insertOnce($pageId, $requiredMarker, $insertedMarker, static fn (string $content): string => strtr($content, $replacements) . "\n\n" . $append, $hashKey);
    }

    private static function prependOnce(int $pageId, string $requiredMarker, string $insertedMarker, string $prepend, string $hashKey): void
    {
        self::insertOnce($pageId, $requiredMarker, $insertedMarker, static fn (string $content): string => $prepend . "\n\n" . $content, $hashKey);
    }

    private static function insertOnce(int $pageId, string $requiredMarker, string $insertedMarker, callable $transform, string $hashKey): void
    {
        $page = get_post($pageId);
        if (!$page instanceof \WP_Post) return;
        $content = trim((string) $page->post_content);
        if (get_post_meta($pageId, $hashKey, true) !== '' || !str_contains($content, $requiredMarker) || str_contains($content, $insertedMarker)) return;
        $updated = $transform($content);
        $result = wp_update_post(['ID' => $pageId, 'post_content' => wp_slash($updated)], true);
        if (is_wp_error($result)) throw new \RuntimeException($result->get_error_message());
        update_post_meta($pageId, $hashKey, hash('sha256', $updated));
    }

    private static function configureAccountOptions(): void
    {
        if (get_option('petshop_account_options_013_configured', false) !== false) return;
        update_option('woocommerce_enable_guest_checkout', 'yes');
        update_option('woocommerce_enable_myaccount_registration', 'yes');
        update_option('woocommerce_enable_signup_and_login_from_checkout', 'yes');
        update_option('woocommerce_enable_signup_from_checkout', 'yes');
        update_option('woocommerce_registration_generate_username', 'yes');
        update_option('woocommerce_registration_generate_password', 'yes');
        update_option('petshop_account_options_013_configured', '1', false);
    }

    private static function ensurePolicyPages(): void
    {
        $privacyId = self::ensureAssignedPolicyPage('wp_page_for_privacy_policy', 'politica-de-privacidade', 'Política de privacidade', 'privacy-policy');
        $returnsId = self::ensureAssignedPolicyPage('woocommerce_refund_returns_page_id', 'trocas-e-devolucoes', 'Trocas e devoluções', 'refund_returns');
        $termsId = self::ensureAssignedPolicyPage('woocommerce_terms_page_id', 'termos-e-condicoes', 'Termos e condições');
        self::ensureDraftPage('politica-de-personalizacao', 'Política de personalização');
        if ($privacyId > 0) update_option('wp_page_for_privacy_policy', $privacyId);
        if ($returnsId > 0) update_option('woocommerce_refund_returns_page_id', $returnsId);
        if ($termsId > 0) update_option('woocommerce_terms_page_id', $termsId);
    }

    private static function ensureAssignedPolicyPage(string $option, string $slug, string $title, string $legacySlug = ''): int
    {
        $configuredId = (int) get_option($option);
        $configured = get_post($configuredId);
        if ($configured instanceof \WP_Post && $configured->post_type === 'page') {
            if ($configured->post_status === 'publish') return $configuredId;
            if (!in_array($configured->post_name, array_filter([$slug, $legacySlug]), true)) return $configuredId;
            return self::ensureDraftPage($slug, $title, $legacySlug);
        }
        return self::ensureDraftPage($slug, $title, $legacySlug);
    }

    private static function ensureDraftPage(string $slug, string $title, string $legacySlug = ''): int
    {
        $page = get_page_by_path($slug);
        if (!$page instanceof \WP_Post && $legacySlug !== '') $page = get_page_by_path($legacySlug);
        if ($page instanceof \WP_Post) {
            $updates = ['ID' => $page->ID];
            if ($legacySlug !== '' && $page->post_name === $legacySlug) $updates['post_name'] = $slug;
            if (in_array($page->post_title, ['Privacy Policy', 'Refund and Returns Policy', $title], true)) $updates['post_title'] = $title;
            if (count($updates) > 1) wp_update_post($updates);
            return (int) $page->ID;
        }
        $content = '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html($title) . '</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Conteúdo jurídico pendente de aprovação.</p><!-- /wp:paragraph -->';
        $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content, 'meta_input' => ['_petshop_managed_policy_013' => 1]], true);
        if (is_wp_error($id)) throw new \RuntimeException($id->get_error_message());
        return (int) $id;
    }

    private static function ensureLocalShippingMethod(): void
    {
        if (!in_array(wp_get_environment_type(), ['local', 'development'], true) || !class_exists('WC_Shipping_Zone') || get_option('petshop_local_shipping_013_configured', false) !== false) return;
        foreach (\WC_Shipping_Zones::get_zones() as $zoneData) {
            if (($zoneData['zone_name'] ?? '') === self::LOCAL_ZONE_NAME) {
                update_option('petshop_local_shipping_013_configured', '1', false);
                return;
            }
        }
        $zone = new \WC_Shipping_Zone();
        $zone->set_zone_name(self::LOCAL_ZONE_NAME);
        $zone->set_zone_order(1);
        $zone->add_location('BR', 'country');
        $zone->save();
        $instanceId = $zone->add_shipping_method('flat_rate');
        if ($instanceId > 0) {
            update_option('woocommerce_flat_rate_' . $instanceId . '_settings', ['title' => 'Entrega local de teste', 'enabled' => 'yes', 'tax_status' => 'none', 'cost' => '19.90']);
            update_option('petshop_local_shipping_013_configured', '1', false);
        }
    }
}
