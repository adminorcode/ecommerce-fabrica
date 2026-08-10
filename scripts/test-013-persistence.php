<?php

defined('ABSPATH') || exit(1);
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('Execute este teste com WP-CLI.');

$cartId = (int) get_option('woocommerce_cart_page_id');
$termsId = (int) get_option('woocommerce_terms_page_id');
$productId = (int) wc_get_product_id_by_sku('PLAN013-SIMPLE');
$product = wc_get_product($productId);
if ($cartId <= 0 || $termsId <= 0 || !$product instanceof WC_Product) {
    WP_CLI::error('Fixtures do Plano 013 indisponiveis. Execute o provisionamento e o seed 013.');
}

$imageId = $product->get_image_id();
if ($imageId <= 0) WP_CLI::error('Imagem da fixture simples indisponivel.');

$original = [
    'cart' => (string) get_post_field('post_content', $cartId),
    'terms' => (string) get_post_field('post_content', $termsId),
    'lead' => $product->get_meta('_petshop_production_lead', true),
    'alt' => get_post_meta($imageId, '_wp_attachment_image_alt', true),
    'next_steps' => get_theme_mod('petshop_order_next_steps', null),
    'terms_option' => $termsId,
    'scheduled' => get_option(Petshop\Core\Lifecycle::SCHEDULED_OPTION, false),
];
$customPolicyId = 0;
$sentinel = 'Edicao administrativa persistente 013';
$passed = false;
$failed = [];

try {
    $editedCart = str_replace('petshop-cart-continue', 'petshop-cart-client-removed', $original['cart']);
    wp_update_post(['ID' => $cartId, 'post_content' => $editedCart . "\n<!-- wp:paragraph --><p>{$sentinel} carrinho</p><!-- /wp:paragraph -->"]);
    wp_update_post(['ID' => $termsId, 'post_content' => "<!-- wp:paragraph --><p>{$sentinel} politica</p><!-- /wp:paragraph -->"]);
    $product->update_meta_data('_petshop_production_lead', $sentinel . ' produto');
    $product->save();
    update_post_meta($imageId, '_wp_attachment_image_alt', $sentinel . ' imagem');
    set_theme_mod('petshop_order_next_steps', $sentinel . ' pedido');
    $customPolicyId = (int) wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_name' => 'politica-cliente-sentinela-013',
        'post_title' => 'Politica aprovada pelo cliente',
        'post_content' => "<!-- wp:paragraph --><p>{$sentinel} atribuida</p><!-- /wp:paragraph -->",
    ]);
    update_option('woocommerce_terms_page_id', $customPolicyId);

    Petshop\Core\Lifecycle::scheduleMigration();
    Petshop\Core\StorefrontExperience::maybeEnsureStorefront();

    $reloaded = wc_get_product($productId);
    $checks = [
        'carrinho Gutenberg' => str_contains((string) get_post_field('post_content', $cartId), $sentinel . ' carrinho'),
        'remocao editorial do bloco' => !str_contains((string) get_post_field('post_content', $cartId), 'petshop-cart-continue'),
        'politica Gutenberg' => str_contains((string) get_post_field('post_content', $termsId), $sentinel . ' politica'),
        'politica atribuida pelo cliente' => (int) get_option('woocommerce_terms_page_id') === $customPolicyId,
        'produto' => $reloaded instanceof WC_Product && $reloaded->get_meta('_petshop_production_lead', true) === $sentinel . ' produto',
        'imagem e alt' => get_post_meta($imageId, '_wp_attachment_image_alt', true) === $sentinel . ' imagem',
        'configuracao global' => get_theme_mod('petshop_order_next_steps') === $sentinel . ' pedido',
    ];
    $failed = array_keys(array_filter($checks, static fn (bool $check): bool => !$check));
    $passed = $failed === [];
} finally {
    wp_update_post(['ID' => $cartId, 'post_content' => $original['cart']]);
    wp_update_post(['ID' => $termsId, 'post_content' => $original['terms']]);
    update_option('woocommerce_terms_page_id', $original['terms_option']);
    if ($customPolicyId > 0) wp_delete_post($customPolicyId, true);
    $product = wc_get_product($productId);
    if ($product instanceof WC_Product) {
        $product->update_meta_data('_petshop_production_lead', $original['lead']);
        $product->save();
    }
    update_post_meta($imageId, '_wp_attachment_image_alt', $original['alt']);
    if ($original['next_steps'] === null) remove_theme_mod('petshop_order_next_steps');
    else set_theme_mod('petshop_order_next_steps', $original['next_steps']);
    if ($original['scheduled'] === false) delete_option(Petshop\Core\Lifecycle::SCHEDULED_OPTION);
    else update_option(Petshop\Core\Lifecycle::SCHEDULED_OPTION, $original['scheduled'], false);
}

if (!$passed) WP_CLI::error('Persistencia 013 falhou para: ' . implode(', ', $failed) . '.');
WP_CLI::success('Persistencia editorial do Plano 013 aprovada apos reprovisionamento.');
