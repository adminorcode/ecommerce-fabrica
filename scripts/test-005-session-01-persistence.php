<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('Execute este teste com WP-CLI.');
}

$locations = get_theme_mod('nav_menu_locations', []);
$menuId = (int) ($locations['petshop-primary'] ?? 0);
$items = $menuId > 0 ? wp_get_nav_menu_items($menuId) : false;
if (!is_array($items) || $items === []) {
    WP_CLI::error('Menu comercial indisponivel para o teste de persistencia.');
}

$item = $items[0];
$original = [
    'text' => get_theme_mod('petshop_benefit_text', null),
    'url' => get_theme_mod('petshop_benefit_url', null),
    'logo' => get_theme_mod('custom_logo', null),
    'support_label' => get_theme_mod('petshop_support_label', null),
    'support_page' => get_theme_mod('petshop_support_page', null),
    'account_label' => get_theme_mod('petshop_account_label', null),
    'locations' => $locations,
    'item_title' => (string) $item->title,
    'storefront_version' => get_option('petshop_storefront_version'),
    'menu_version' => get_option('petshop_commercial_menu_version'),
    'migration_error' => get_option('petshop_storefront_migration_error'),
];

$attachments = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => 2,
    'fields' => 'ids',
    'post_mime_type' => 'image',
    'post__not_in' => [(int) $original['logo']],
]);
$sentinelLogo = $attachments === [] ? 0 : (int) $attachments[0];
if ($sentinelLogo <= 0) {
    WP_CLI::error('Nao ha uma segunda imagem para testar a persistencia do logo.');
}

$sentinelText = 'Promocao sentinela da Sessao 01';
$sentinelUrl = home_url('/atendimento/');
$sentinelMenuTitle = 'Lacos sentinela';
$sentinelSupportLabel = 'Ajuda sentinela';
$sentinelAccountLabel = 'Conta sentinela';
$sentinelSupportPage = 0;
$targetVersion = (string) (new ReflectionClass(Petshop\Core\StorefrontExperience::class))
    ->getReflectionConstant('VERSION')
    ->getValue();
$passed = false;
$emptyPromoPassed = false;

try {
    set_theme_mod('petshop_benefit_text', '');
    ob_start();
    do_action('wp_body_open');
    $emptyPromoPassed = !str_contains((string) ob_get_clean(), 'petshop-promo-bar');

    set_theme_mod('petshop_benefit_text', $sentinelText);
    set_theme_mod('petshop_benefit_url', $sentinelUrl);
    set_theme_mod('custom_logo', $sentinelLogo);
    set_theme_mod('petshop_support_label', $sentinelSupportLabel);
    set_theme_mod('petshop_support_page', $sentinelSupportPage);
    set_theme_mod('petshop_account_label', $sentinelAccountLabel);
    wp_update_post(['ID' => (int) $item->ID, 'post_title' => $sentinelMenuTitle]);
    update_option('petshop_storefront_version', 'session-01-persistence-test', false);
    delete_option('petshop_commercial_menu_version');
    delete_option('petshop_storefront_migration_error');

    Petshop\Core\StorefrontExperience::maybeEnsureStorefront();

    $afterLocations = get_theme_mod('nav_menu_locations', []);
    $passed = get_theme_mod('petshop_benefit_text') === $sentinelText
        && get_theme_mod('petshop_benefit_url') === $sentinelUrl
        && (int) get_theme_mod('custom_logo') === $sentinelLogo
        && get_theme_mod('petshop_support_label') === $sentinelSupportLabel
        && (int) get_theme_mod('petshop_support_page') === $sentinelSupportPage
        && get_theme_mod('petshop_account_label') === $sentinelAccountLabel
        && (string) get_post_field('post_title', (int) $item->ID) === $sentinelMenuTitle
        && (int) ($afterLocations['petshop-primary'] ?? 0) === $menuId
        && get_option('petshop_storefront_version') === $targetVersion
        && get_option('petshop_commercial_menu_version') === '1'
        && get_option('petshop_storefront_migration_error', '') === ''
        && count((array) wp_get_nav_menu_items($menuId)) === 7;
} finally {
    if ($original['text'] === null) {
        remove_theme_mod('petshop_benefit_text');
    } else {
        set_theme_mod('petshop_benefit_text', $original['text']);
    }
    if ($original['url'] === null) {
        remove_theme_mod('petshop_benefit_url');
    } else {
        set_theme_mod('petshop_benefit_url', $original['url']);
    }
    if ($original['logo'] === null) {
        remove_theme_mod('custom_logo');
    } else {
        set_theme_mod('custom_logo', $original['logo']);
    }
    foreach (['support_label', 'support_page', 'account_label'] as $key) {
        $themeMod = 'petshop_' . $key;
        if ($original[$key] === null) {
            remove_theme_mod($themeMod);
        } else {
            set_theme_mod($themeMod, $original[$key]);
        }
    }
    set_theme_mod('nav_menu_locations', $original['locations']);
    wp_update_post(['ID' => (int) $item->ID, 'post_title' => $original['item_title']]);
    update_option('petshop_storefront_version', $original['storefront_version'], false);
    if ($original['menu_version'] === false) {
        delete_option('petshop_commercial_menu_version');
    } else {
        update_option('petshop_commercial_menu_version', $original['menu_version'], false);
    }
    if ($original['migration_error'] === false) {
        delete_option('petshop_storefront_migration_error');
    } else {
        update_option('petshop_storefront_migration_error', $original['migration_error'], false);
    }
}

if (!$passed || !$emptyPromoPassed) {
    WP_CLI::error('Persistencia ou estado vazio falhou para o conteudo administravel do cabecalho.');
}

WP_CLI::success('Persistencia aprovada para promocao, link, logo, rotulo e localizacao do menu.');
