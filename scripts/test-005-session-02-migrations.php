<?php

defined('ABSPATH') || exit(1);
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('Execute este teste com WP-CLI.');

$class = new ReflectionClass(Petshop\Core\StorefrontExperience::class);
$targetVersion = (string) $class->getReflectionConstant('VERSION')->getValue();
$invoke = static function (string $method, mixed ...$args) use ($class): mixed {
    return $class->getMethod($method)->invoke(null, ...$args);
};
$homeId = (int) get_option('page_on_front');
$shopUrl = (string) wc_get_page_permalink('shop');
$supportUrl = (string) get_permalink((int) get_theme_mod('petshop_support_page', 0));
$heroAttachments = get_posts([
    'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1,
    'fields' => 'ids', 'meta_key' => '_petshop_placeholder_key', 'meta_value' => 'hero-wide',
]);
$heroId = $heroAttachments === [] ? 0 : (int) $heroAttachments[0];
if ($heroId <= 0) WP_CLI::error('Imagem hero-wide ausente para matriz de migracao.');

$original = [
    'content' => (string) get_post_field('post_content', $homeId),
    'managed' => get_post_meta($homeId, '_petshop_managed_page', true),
    'hash' => get_post_meta($homeId, '_petshop_managed_hero_hash', true),
    'schema' => get_post_meta($homeId, '_petshop_home_schema_version', true),
    'version' => get_option('petshop_storefront_version'),
    'error' => get_option('petshop_storefront_migration_error'),
];

$newHero = (string) $invoke('heroContent', $shopUrl, $heroId);
$campaignHero = (string) $invoke('campaignHeroContent', $shopUrl, $heroId);
$legacyHero = (string) $invoke('legacyHeroContent', $shopUrl, $heroId);
$benefits = (string) $invoke('benefitsContent');
$heroEnd = strpos($original['content'], '<!-- /wp:cover -->');
$tailStart = $heroEnd === false ? false : strpos($original['content'], '<!-- wp:group {"className":"petshop-section"', $heroEnd);
if ($tailStart === false) WP_CLI::error('Cauda da Home ausente para matriz de migracao.');
$tail = substr($original['content'], $tailStart);
$customHero = str_replace('Acessórios que valorizam cada banho e tosa', 'Hero customizado sentinela', $newHero);
$customBenefits = str_replace('Pronta entrega', 'Beneficio customizado sentinela', $benefits);
$failures = [];

$run = static function (
    string $name,
    string $content,
    int $schema,
    string $hash,
    callable $assert
) use ($homeId, $targetVersion, &$failures): void {
    wp_update_post(['ID' => $homeId, 'post_content' => $content]);
    update_post_meta($homeId, '_petshop_managed_page', 1);
    update_post_meta($homeId, '_petshop_home_schema_version', $schema);
    if ($hash === '') delete_post_meta($homeId, '_petshop_managed_hero_hash');
    else update_post_meta($homeId, '_petshop_managed_hero_hash', $hash);
    update_option('petshop_storefront_version', 'migration-matrix-' . $name, false);
    delete_option('petshop_storefront_migration_error');
    Petshop\Core\StorefrontExperience::maybeEnsureStorefront();
    $after = (string) get_post_field('post_content', $homeId);
    $common = get_option('petshop_storefront_version') === $targetVersion
        && (int) get_post_meta($homeId, '_petshop_home_schema_version', true) === 9
        && get_option('petshop_storefront_migration_error', '') === '';
    if (!$common || !$assert($after, (string) get_post_meta($homeId, '_petshop_managed_hero_hash', true))) {
        $failures[] = $name;
    }
};

try {
    wp_update_post(['ID' => $homeId, 'post_content' => $newHero . "\n" . $benefits . $tail]);
    delete_post_meta($homeId, '_petshop_home_schema_version');
    delete_post_meta($homeId, '_petshop_managed_hero_hash');
    $invoke('stampNewManagedHome', $homeId, $shopUrl, $heroId);
    if (
        (int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 9
        || (string) get_post_meta($homeId, '_petshop_managed_hero_hash', true) !== hash('sha256', $newHero)
    ) {
        $failures[] = 'fresh';
    }

    $managedAssert = static fn (string $after, string $hash): bool =>
        str_contains($after, 'Acessórios que valorizam cada banho e tosa')
        && str_contains($after, 'Pronta entrega')
        && str_contains($after, 'Condições para volume')
        && str_contains($after, 'Envio para todo o Brasil')
        && substr_count($after, '"className":"petshop-benefits"') === 1
        && $hash === hash('sha256', $newHero);
    $run('legacy', $legacyHero . $tail, 6, '', $managedAssert);
    $run('campaign', $campaignHero . $tail, 7, '', $managedAssert);
    $run(
        'custom-no-hash',
        $customHero . "\n" . $customBenefits . $tail,
        7,
        '',
        static fn (string $after, string $hash): bool => str_contains($after, 'Hero customizado sentinela')
            && str_contains($after, 'Beneficio customizado sentinela') && $hash === ''
    );
    $run(
        'custom-stale-hash',
        $customHero . "\n" . $benefits . $tail,
        7,
        hash('sha256', $newHero),
        static fn (string $after, string $hash): bool => str_contains($after, 'Hero customizado sentinela') && $hash === ''
    );
    $run(
        'hero-removed',
        $customBenefits . $tail,
        7,
        hash('sha256', $newHero),
        static fn (string $after, string $hash): bool => !str_contains($after, '"className":"petshop-hero"')
            && str_contains($after, 'Beneficio customizado sentinela') && $hash === ''
    );
    $run(
        'benefits-missing',
        $newHero . $tail,
        7,
        hash('sha256', $newHero),
        static fn (string $after, string $hash): bool => substr_count($after, '"className":"petshop-benefits"') === 1
            && $hash === hash('sha256', $newHero)
    );
    $run(
        'benefits-custom',
        $campaignHero . "\n" . $customBenefits . $tail,
        7,
        '',
        static fn (string $after, string $hash): bool => str_contains($after, 'Beneficio customizado sentinela')
            && $hash === hash('sha256', $newHero)
    );
} finally {
    wp_update_post(['ID' => $homeId, 'post_content' => $original['content']]);
    update_post_meta($homeId, '_petshop_managed_page', $original['managed']);
    update_post_meta($homeId, '_petshop_managed_hero_hash', $original['hash']);
    update_post_meta($homeId, '_petshop_home_schema_version', $original['schema']);
    update_option('petshop_storefront_version', $original['version'], false);
    if ($original['error'] === false) delete_option('petshop_storefront_migration_error');
    else update_option('petshop_storefront_migration_error', $original['error'], false);
}

if ($failures !== []) WP_CLI::error('Matriz de migracao falhou: ' . implode(', ', $failures));
WP_CLI::success('Matriz fresh, legacy, campanha, customizacoes, remocao e beneficios aprovada.');
