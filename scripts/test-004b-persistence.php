<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('Execute este teste com WP-CLI.');
}

$homeId = (int) get_option('page_on_front');
$original = (string) get_post_field('post_content', $homeId);
$originalHash = (string) get_post_meta($homeId, '_petshop_managed_hero_hash', true);
$originalSchema = (int) get_post_meta($homeId, '_petshop_home_schema_version', true);
$originalVersion = (string) get_option('petshop_storefront_version');
$supportPage = get_page_by_path('atendimento');
$supportUrl = $supportPage instanceof WP_Post ? (string) get_permalink($supportPage) : home_url('/atendimento/');

$edited = str_replace('Acessórios para banho e tosa', 'Conteúdo editorial sentinela', $original);
$edited = (string) preg_replace(
    '/href="[^"]+">Ver destaques da loja/',
    'href="' . esc_url($supportUrl) . '">CTA editorial sentinela',
    $edited,
    1
);
$edited = (string) preg_replace(
    '/"alt":"[^"]*"/',
    '"alt":"Alt editorial sentinela"',
    $edited,
    1
);
$edited = (string) preg_replace(
    '/(wp-block-cover__image-background[^>]+alt=")[^"]*/',
    '$1Alt editorial sentinela',
    $edited,
    1
);

$passed = false;
try {
    $saved = wp_update_post(['ID' => $homeId, 'post_content' => $edited], true);
    if (is_wp_error($saved)) {
        throw new RuntimeException($saved->get_error_message());
    }
    update_post_meta($homeId, '_petshop_home_schema_version', 6);
    update_option('petshop_storefront_version', 'persistence-test', false);
    Petshop\Core\StorefrontExperience::maybeEnsureStorefront();

    $after = (string) get_post_field('post_content', $homeId);
    $passed = str_contains($after, 'Conteúdo editorial sentinela')
        && str_contains($after, 'CTA editorial sentinela')
        && str_contains($after, 'Alt editorial sentinela');
} finally {
    wp_update_post(['ID' => $homeId, 'post_content' => $original]);
    update_post_meta($homeId, '_petshop_home_schema_version', $originalSchema);
    update_post_meta($homeId, '_petshop_managed_hero_hash', $originalHash);
    update_option('petshop_storefront_version', $originalVersion, false);
}

if (!$passed) {
    WP_CLI::error('A migração sobrescreveu conteúdo editorial do hero.');
}

$heroAttachments = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => 1,
    'fields' => 'ids',
    'meta_key' => '_petshop_placeholder_key',
    'meta_value' => 'hero-wide',
]);
$otherAttachments = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => 1,
    'fields' => 'ids',
    'meta_key' => '_petshop_placeholder_key',
    'meta_value' => 'grooming-salon',
]);
$heroId = $heroAttachments === [] ? 0 : (int) $heroAttachments[0];
$otherId = $otherAttachments === [] ? 0 : (int) $otherAttachments[0];
if ($heroId <= 0 || $otherId <= 0) {
    WP_CLI::error('Anexos necessários ao teste de migração legacy não foram encontrados.');
}

$legacyMethod = new ReflectionMethod(Petshop\Core\StorefrontExperience::class, 'legacyHeroContent');
$legacy = (string) $legacyMethod->invoke(null, (string) wc_get_page_permalink('shop'), $heroId);
$legacy = str_replace(
    [
        '"id":' . $heroId,
        'wp-image-' . $heroId,
        (string) wp_get_attachment_image_url($heroId, 'full'),
        (string) wc_get_page_permalink('shop'),
    ],
    [
        '"id":' . $otherId,
        'wp-image-' . $otherId,
        (string) wp_get_attachment_image_url($otherId, 'full'),
        $supportUrl,
    ],
    $legacy
);
$coverEnd = strpos($original, '<!-- /wp:cover -->');
$legacyPage = $legacy . ($coverEnd === false ? '' : substr($original, $coverEnd + strlen('<!-- /wp:cover -->')));
$legacyPassed = false;
try {
    wp_update_post(['ID' => $homeId, 'post_content' => $legacyPage]);
    update_post_meta($homeId, '_petshop_home_schema_version', 6);
    delete_post_meta($homeId, '_petshop_managed_hero_hash');
    update_option('petshop_storefront_version', 'legacy-persistence-test', false);
    Petshop\Core\StorefrontExperience::maybeEnsureStorefront();
    $afterLegacy = (string) get_post_field('post_content', $homeId);
    $legacyPassed = str_contains($afterLegacy, 'O acabamento que faz seu cliente lembrar')
        && str_contains($afterLegacy, $supportUrl)
        && str_contains($afterLegacy, 'wp-image-' . $otherId);
} finally {
    wp_update_post(['ID' => $homeId, 'post_content' => $original]);
    update_post_meta($homeId, '_petshop_home_schema_version', $originalSchema);
    update_post_meta($homeId, '_petshop_managed_hero_hash', $originalHash);
    update_option('petshop_storefront_version', $originalVersion, false);
}
if (!$legacyPassed) {
    WP_CLI::error('A migração substituiu imagem ou CTA customizado no hero legacy.');
}

$originalError = (string) get_option('petshop_storefront_migration_error', '');
$preconditionPassed = false;
try {
    delete_post_meta($heroId, '_petshop_placeholder_key');
    update_option('petshop_storefront_version', 'precondition-test', false);
    Petshop\Core\StorefrontExperience::maybeEnsureStorefront();
    $preconditionPassed = get_option('petshop_storefront_version') === 'precondition-test'
        && get_option('petshop_storefront_migration_error', '') === 'PETSHOP_HERO_ATTACHMENT_MISSING';
} finally {
    update_post_meta($heroId, '_petshop_placeholder_key', 'hero-wide');
    update_option('petshop_storefront_version', $originalVersion, false);
    if ($originalError === '') {
        delete_option('petshop_storefront_migration_error');
    } else {
        update_option('petshop_storefront_migration_error', $originalError, false);
    }
}
if (!$preconditionPassed) {
    WP_CLI::error('Ausência do seed foi marcada incorretamente como migração concluída.');
}

WP_CLI::success('Persistência atual/legacy e pré-condição do seed aprovadas.');
