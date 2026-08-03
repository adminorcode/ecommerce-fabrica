<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce ativo.');
}

if (!class_exists(\Petshop\Core\StorefrontWishlist::class)) {
    WP_CLI::error('Classe StorefrontWishlist ausente.');
}

$failures = [];
$endpoint = \Petshop\Core\StorefrontWishlist::ENDPOINT;
$pageSlug = \Petshop\Core\StorefrontWishlist::PAGE_SLUG;

Petshop\Core\StorefrontExperience::maybeEnsureStorefront();

$page = get_page_by_path($pageSlug);
if (!$page instanceof \WP_Post || $page->post_status !== 'publish') {
    $failures[] = 'Pagina lista-de-desejos nao publicada';
} elseif (!str_contains((string) $page->post_content, '[petshop_wishlist]')) {
    $failures[] = 'Shortcode petshop_wishlist ausente na pagina';
}

$wishlistPageId = (int) get_theme_mod('petshop_wishlist_page', 0);
if ($wishlistPageId <= 0) {
    $failures[] = 'Theme mod petshop_wishlist_page nao provisionado';
}

$wishlistLabel = trim((string) get_theme_mod('petshop_wishlist_label', ''));
if ($wishlistLabel === '') {
    $failures[] = 'Theme mod petshop_wishlist_label vazio';
}

$queryVars = apply_filters('woocommerce_get_query_vars', []);
if (!isset($queryVars[$endpoint])) {
    $failures[] = 'Endpoint WooCommerce lista-de-desejos nao registrado';
}

$menuItems = apply_filters('woocommerce_account_menu_items', [
    'dashboard' => 'Painel',
    'customer-logout' => 'Sair',
]);
if (!isset($menuItems[$endpoint])) {
    $failures[] = 'Item Lista de desejos ausente no menu Minha conta';
}

$emptyHtml = do_shortcode('[petshop_wishlist]');
if (!str_contains($emptyHtml, 'petshop-wishlist-page')) {
    $failures[] = 'Shortcode nao renderiza container petshop-wishlist-page';
}
if (!str_contains($emptyHtml, 'petshop-wishlist-page__empty')) {
    $failures[] = 'Shortcode nao renderiza estado vazio';
}

if ($page instanceof \WP_Post && $page->post_status !== 'publish') {
    $failures[] = 'Pagina lista-de-desejos nao publicada';
}

$products = wc_get_products(['limit' => 1, 'status' => 'publish', 'return' => 'ids']);
if ($products !== []) {
    $productId = (int) $products[0];
    $grid = (new ReflectionClass(\Petshop\Core\StorefrontWishlist::class))
        ->getMethod('renderProductGrid');
    $grid->setAccessible(true);
    $html = $grid->invoke(null, [$productId]);
    if ($html === '' || !preg_match('/class="[^"]*\bproduct\b[^"]*"/', $html)) {
        $failures[] = 'Grade de wishlist nao renderiza produto valido';
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Validacao do Plano 010b reprovada.');
}

WP_CLI::success('Wishlist (Plano 010b): pagina, endpoint, shortcode e theme mods aprovados.');
