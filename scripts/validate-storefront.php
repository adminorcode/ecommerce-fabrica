<?php

defined('ABSPATH') || exit(1);

/**
 * @param mixed $condition
 */
function petshop_assert($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$expectedCategories = [
    'promocoes',
    'adesivos',
    'babador',
    'bandanas',
    'colarinhos',
    'conjuntos',
    'copa',
    'festa-junina',
    'gargantilhas',
    'gravatas',
    'inverno',
    'lacos',
    'lacos-adesivos',
    'penteados',
    'dia-dos-pais',
];
$seasonalCategories = ['copa', 'festa-junina', 'inverno', 'dia-dos-pais'];
$validateInstallationDefaults = getenv('PETSHOP_VALIDATE_DEFAULTS') === '1';

foreach ($expectedCategories as $order => $slug) {
    $term = get_term_by('slug', $slug, 'product_cat');
    petshop_assert($term instanceof WP_Term, "Categoria ausente: {$slug}");
    petshop_assert(metadata_exists('term', $term->term_id, 'petshop_menu_order'), "Categoria sem ordem comercial: {$slug}");
    petshop_assert(metadata_exists('term', $term->term_id, 'petshop_seasonal'), "Categoria sem sazonalidade: {$slug}");
    petshop_assert(metadata_exists('term', $term->term_id, 'petshop_visible_in_menu'), "Categoria sem visibilidade: {$slug}");

    if ($validateInstallationDefaults) {
        petshop_assert(
            (int) get_term_meta($term->term_id, 'petshop_menu_order', true) === $order,
            "Ordem comercial inicial inválida: {$slug}"
        );
        $seasonal = in_array($slug, $seasonalCategories, true);
        petshop_assert(
            (bool) get_term_meta($term->term_id, 'petshop_seasonal', true) === $seasonal,
            "Sazonalidade inicial inválida: {$slug}"
        );
        petshop_assert(
            (bool) get_term_meta($term->term_id, 'petshop_visible_in_menu', true) === (
                $slug === 'dia-dos-pais' ? true : !$seasonal
            ),
            "Visibilidade inicial inválida: {$slug}"
        );
    }
}

$laces = get_term_by('slug', 'lacos', 'product_cat');
$adhesiveLaces = get_term_by('slug', 'lacos-adesivos', 'product_cat');
petshop_assert(
    $laces instanceof WP_Term
    && $adhesiveLaces instanceof WP_Term
    && (int) $adhesiveLaces->parent === (int) $laces->term_id,
    'Laços Adesivos não está abaixo de Laços.'
);
echo "taxonomia: passed\n";

$home = get_page_by_path('inicio');
petshop_assert($home instanceof WP_Post, 'Página inicial ausente.');
petshop_assert((int) get_option('page_on_front') === (int) $home->ID, 'Página inicial não configurada.');
foreach (['petshop-hero', '[petshop_categories', '[petshop_featured_products_grid', '[petshop_seasonal_products_grid', '[petshop_reviews'] as $fragment) {
    petshop_assert(str_contains($home->post_content, $fragment), "Home sem bloco obrigatório: {$fragment}");
}
echo "home: passed\n";

$locations = get_theme_mod('nav_menu_locations', []);
foreach (['menu_1', 'menu_mobile', 'petshop-primary', 'petshop-utility', 'petshop-footer'] as $location) {
    petshop_assert(!empty($locations[$location]), "Menu não atribuído: {$location}");
}

$primaryItems = wp_get_nav_menu_items((int) $locations['petshop-primary']);
petshop_assert(is_array($primaryItems) && $primaryItems !== [], 'Menu principal vazio.');
$shopItem = null;
foreach ($primaryItems as $item) {
    if ((int) $item->object_id === (int) get_option('woocommerce_shop_page_id')) {
        $shopItem = $item;
        break;
    }
}
if ($validateInstallationDefaults) {
    petshop_assert(count($primaryItems) >= 16, 'Menu principal inicial incompleto.');
    petshop_assert($shopItem instanceof WP_Post, 'Entrada Comprar ausente no menu inicial.');
    foreach ($primaryItems as $item) {
        if ($item->object === 'product_cat') {
            petshop_assert((int) $item->menu_item_parent === (int) $shopItem->ID, 'Categoria fora do submenu Comprar.');
        }
    }
}
echo "navegacao: passed\n";

$cartContent = (string) get_post_field('post_content', (int) get_option('woocommerce_cart_page_id'));
$checkoutContent = (string) get_post_field('post_content', (int) get_option('woocommerce_checkout_page_id'));
petshop_assert(str_contains($cartContent, 'wp:woocommerce/cart'), 'Carrinho não usa o bloco oficial.');
petshop_assert(str_contains($checkoutContent, 'wp:woocommerce/checkout'), 'Checkout não usa o bloco oficial.');
echo "woocommerce-blocks: passed\n";

petshop_assert((int) get_theme_mod('custom_logo') > 0, 'Logo da marca não configurado.');
$expectedBlogName = getenv('PETSHOP_EXPECTED_BLOGNAME');
if ($expectedBlogName !== false && $expectedBlogName !== '') {
    petshop_assert(get_option('blogname') === $expectedBlogName, 'Nome da loja diverge do esperado.');
} else {
    petshop_assert(trim((string) get_option('blogname')) !== '', 'Nome da loja não configurado.');
}
petshop_assert(get_option('woocommerce_coming_soon') === 'no', 'Loja ainda está em modo de lançamento.');
petshop_assert(get_option('woocommerce_hide_out_of_stock_items') === 'yes', 'Produtos sem estoque ainda aparecem nas vitrines.');
echo "identidade: passed\n";
