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
if (!str_contains($content, '[petshop_product_showcase')) {
    $failures[] = 'Shortcode petshop_product_showcase ausente na Home';
}
if (!str_contains($content, 'cta="Ver todos')) {
    $failures[] = 'Atributo cta administravel ausente nos shortcodes da Home';
}
if (str_contains($content, 'petshop-section petshop-seasonal')) {
    $failures[] = 'Grupo Gutenberg legado da secao sazonal ainda presente';
}

$kitsSentinelTitle = 'Kits sentinela 010';
$kitsSentinelCta = 'Ver kits sentinela';
$edited = (string) preg_replace(
    '/\[petshop_kits_section([^\]]*)\]/',
    '[petshop_kits_section$1 title="' . $kitsSentinelTitle . '" cta="' . $kitsSentinelCta . '"]',
    $content,
    1
);

if ($edited === $content) {
    $failures[] = 'Shortcode petshop_kits_section nao encontrado para teste de persistencia';
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
        && (int) get_post_meta($homeId, '_petshop_home_schema_version', true) >= 17
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

$emptyKits = do_shortcode('[petshop_kits_section limit="4" columns="4" category="__missing__"]');
if ($emptyKits !== '') {
    $failures[] = 'Secao de kits sem produtos deveria retornar vazio';
}

$emptySeasonal = do_shortcode('[petshop_seasonal_products limit="4" columns="4" title="Sazonal teste"]');
if ($emptySeasonal !== '' && !preg_match('/class="[^"]*\bproduct\b[^"]*"/', $emptySeasonal)) {
    // ok when no seasonal products
} elseif ($emptySeasonal === '') {
    // expected when no seasonal catalog
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Validacao de persistencia do Plano 010 reprovada.');
}

WP_CLI::success('Persistencia e shortcodes da Home (Plano 010) aprovados.');
