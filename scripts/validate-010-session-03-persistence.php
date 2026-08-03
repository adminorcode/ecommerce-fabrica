<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce ativo.');
}

$failures = [];
$homeId = (int) get_option('page_on_front');
if ($homeId <= 0) {
    WP_CLI::error('Home estatica nao configurada.');
}

$original = [
    'content' => (string) get_post_field('post_content', $homeId),
    'schema' => get_post_meta($homeId, '_petshop_home_schema_version', true),
    'version' => get_option('petshop_storefront_version'),
    'error' => get_option('petshop_storefront_migration_error'),
];

$targetVersion = (string) (new ReflectionClass(Petshop\Core\StorefrontExperience::class))
    ->getReflectionConstant('VERSION')
    ->getValue();

$content = $original['content'];
if (!str_contains($content, 'petshop-section-head')) {
    $failures[] = 'Cabecalhos Gutenberg (petshop-section-head) ausentes na Home';
}
if (!str_contains($content, '[petshop_product_showcase_grid')) {
    $failures[] = 'Shortcode petshop_product_showcase_grid ausente na Home';
}
if (preg_match('/\[petshop_(featured_products|kits_section|seasonal_products|product_showcase)(?!_grid)\b/', $content)) {
    $failures[] = 'Shortcodes legados de vitrine ainda presentes na Home';
}
if (str_contains($content, 'petshop-section petshop-seasonal')) {
    $failures[] = 'Grupo Gutenberg legado da secao sazonal ainda presente';
}

$kitsSentinelTitle = 'Kits sentinela 010';
$kitsSentinelCta = 'Ver kits sentinela';
$edited = (string) preg_replace(
    '/(<h2[^>]*id="petshop-kits-heading"[^>]*>)(.*?)(<\/h2>)/s',
    '$1' . $kitsSentinelTitle . '$3',
    $content,
    1
);
$edited = (string) preg_replace(
    '/(<section[^>]*petshop-kits-section[^>]*>.*?petshop-section-head__link" href="[^"]*">)(.*?)(<\/a>)/s',
    '$1' . $kitsSentinelCta . '$3',
    $edited,
    1
);

if ($edited === $content) {
    $failures[] = 'Secao de kits editavel (cabecalho Gutenberg) nao encontrada para teste de persistencia';
}

$passedPersistence = false;

try {
    wp_update_post(['ID' => $homeId, 'post_content' => $edited]);
    update_option('petshop_storefront_version', 'session-010-persistence-test', false);
    delete_option('petshop_storefront_migration_error');
    Petshop\Core\StorefrontExperience::maybeEnsureStorefront();

    $after = (string) get_post_field('post_content', $homeId);
    $passedPersistence = str_contains($after, $kitsSentinelTitle)
        && str_contains($after, $kitsSentinelCta)
        && get_option('petshop_storefront_version') === $targetVersion
        && (int) get_post_meta($homeId, '_petshop_home_schema_version', true) >= 20
        && get_option('petshop_storefront_migration_error', '') === '';
} finally {
    wp_update_post(['ID' => $homeId, 'post_content' => $original['content']]);
    update_post_meta($homeId, '_petshop_home_schema_version', $original['schema']);
    update_option('petshop_storefront_version', $original['version'], false);
    if ($original['error'] === false) {
        delete_option('petshop_storefront_migration_error');
    } else {
        update_option('petshop_storefront_migration_error', $original['error'], false);
    }
}

if (!$passedPersistence) {
    $failures[] = 'Reprovisionamento sobrescreveu titulo ou cta editados da secao de kits';
}

$multiCategoryShowcase = do_shortcode(
    '[petshop_product_showcase limit="4" columns="4" title="Filtro multiplo teste" category="adesivos,gravatas,lacos"]'
);
if (
    $multiCategoryShowcase !== ''
    && !preg_match('/petshop_categories=(?:adesivos(?:%2C|,))+gravatas(?:%2C|,)+lacos/', $multiCategoryShowcase)
) {
    $failures[] = 'Ver todos de multiplas categorias nao usa o filtro petshop_categories';
}

$emptyKits = do_shortcode('[petshop_kits_section_grid limit="4" columns="4" category="__missing__"]');
if ($emptyKits !== '') {
    $failures[] = 'Grade de kits sem produtos deveria retornar vazio';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Validacao de persistencia do Plano 010 reprovada.');
}

WP_CLI::success('Persistencia e vitrines editaveis da Home (Plano 010) aprovados.');
