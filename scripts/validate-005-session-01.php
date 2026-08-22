<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce ativo.');
}

$failures = [];
$expected = [
    'Lacos' => 'lacos',
    'Bandanas' => 'bandanas',
    'Adesivos' => 'adesivos',
    'Gravatas' => 'gravatas',
    'Kits Economicos' => 'conjuntos',
    'Colecoes' => 'colecoes',
    'Personalizados' => 'personalize',
];
$normalize = static function (string $value): string {
    $value = remove_accents(wp_strip_all_tags($value));
    return trim((string) preg_replace('/\s+/u', ' ', $value));
};

if (trim((string) get_theme_mod('petshop_benefit_text', '')) === '') {
    $failures[] = 'Mensagem promocional global ausente';
}
if ((int) get_theme_mod('custom_logo', 0) <= 0) {
    $failures[] = 'Logo customizavel ausente';
}
$supportPage = get_post((int) get_theme_mod('petshop_support_page', 0));
if (!$supportPage instanceof WP_Post || $supportPage->post_status !== 'publish') {
    $failures[] = 'Pagina de atendimento configuravel ausente ou nao publicada';
}
if (trim((string) get_theme_mod('petshop_support_label', '')) === '') {
    $failures[] = 'Rotulo configuravel de atendimento ausente';
}
if (trim((string) get_theme_mod('petshop_account_label', '')) === '') {
    $failures[] = 'Rotulo configuravel da conta ausente';
}

$locations = get_theme_mod('nav_menu_locations', []);
$menuId = (int) ($locations['petshop-primary'] ?? 0);
$items = $menuId > 0 ? wp_get_nav_menu_items($menuId) : false;
if (!is_array($items) || count($items) < count($expected)) {
    $failures[] = 'Menu comercial deve conter ao menos os sete destinos base';
    $items = [];
}

$actual = [];
foreach ($items as $item) {
    $actual[$normalize((string) $item->title)] = (string) $item->url;
}
foreach ($expected as $label => $slug) {
    $url = $actual[$label] ?? '';
    if ($url === '') {
        $failures[] = "Entrada ausente no menu: {$label}";
        continue;
    }
    if (!str_contains(untrailingslashit($url), '/' . $slug)) {
        $failures[] = "Destino incorreto para {$label}: {$url}";
    }
}

$collections = get_page_by_path('colecoes');
$personalize = get_page_by_path('personalize');
if (!$collections instanceof WP_Post || $collections->post_status !== 'publish') {
    $failures[] = 'Pagina Gutenberg Colecoes ausente ou nao publicada';
}
if (!$personalize instanceof WP_Post || $personalize->post_status !== 'publish') {
    $failures[] = 'Pagina Personalizados ausente ou nao publicada';
}
if (has_filter('posts_search', [Petshop\Core\StorefrontExperience::class, 'filterExactSkuSearch']) === false) {
    $failures[] = 'Filtro de busca por SKU nao registrado';
}
if (get_option('petshop_commercial_menu_version') !== '1') {
    $failures[] = 'Versao do menu comercial nao confirmada';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Sessao 01 invalida: ' . count($failures) . ' falha(s).');
}

WP_CLI::success('Sessao 01 valida: conteudo global editavel, logo, destinos base cadastrados e busca por SKU registrada.');
