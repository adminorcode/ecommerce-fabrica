<?php

defined('ABSPATH') || exit(1);
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('Execute este teste com WP-CLI.');

$homeId = (int) get_option('page_on_front');
$original = [
    'content' => (string) get_post_field('post_content', $homeId),
    'hash' => get_post_meta($homeId, '_petshop_managed_hero_hash', true),
    'schema' => get_post_meta($homeId, '_petshop_home_schema_version', true),
    'version' => get_option('petshop_storefront_version'),
    'error' => get_option('petshop_storefront_migration_error'),
];
$targetVersion = (string) (new ReflectionClass(Petshop\Core\StorefrontExperience::class))
    ->getReflectionConstant('VERSION')
    ->getValue();

$heroStart = strpos($original['content'], '<!-- wp:cover ');
$heroEnd = $heroStart === false ? false : strpos($original['content'], '<!-- /wp:cover -->', $heroStart);
if ($heroStart === false || $heroEnd === false) WP_CLI::error('Hero indisponivel para persistencia.');
$heroEnd += strlen('<!-- /wp:cover -->');
$hero = substr($original['content'], $heroStart, $heroEnd - $heroStart);

preg_match('/"id":(\d+)/', $hero, $idMatch);
$originalHeroId = (int) ($idMatch[1] ?? 0);
$otherImages = get_posts([
    'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image',
    'posts_per_page' => 1, 'fields' => 'ids', 'post__not_in' => [$originalHeroId],
]);
$otherId = $otherImages === [] ? 0 : (int) $otherImages[0];
if ($otherId <= 0) WP_CLI::error('Segunda imagem ausente para persistencia.');
$oldUrl = (string) wp_get_attachment_image_url($originalHeroId, 'full');
$newUrl = (string) wp_get_attachment_image_url($otherId, 'full');

$editedHero = str_replace('Acessórios que valorizam cada banho e tosa', 'Titulo institucional sentinela', $hero);
$editedHero = str_replace('"id":' . $originalHeroId, '"id":' . $otherId, $editedHero);
$editedHero = str_replace('wp-image-' . $originalHeroId, 'wp-image-' . $otherId, $editedHero);
$editedHero = str_replace($oldUrl, $newUrl, $editedHero);
$editedHero = (string) preg_replace('/"alt":"[^"]*"/', '"alt":"Alt institucional sentinela"', $editedHero, 1);
$editedHero = (string) preg_replace('/(<img[^>]+alt=")[^"]*/', '$1Alt institucional sentinela', $editedHero, 1);
preg_match_all('/href="([^"]+)"/', $editedHero, $hrefMatches);
if (count($hrefMatches[1] ?? []) !== 2) WP_CLI::error('CTAs do hero indisponiveis para persistencia.');
$editedHero = str_replace('href="' . $hrefMatches[1][0] . '"', 'href="' . esc_url(home_url('/colecoes/')) . '"', $editedHero);
$editedHero = str_replace('href="' . $hrefMatches[1][1] . '"', 'href="' . esc_url(home_url('/atendimento/')) . '"', $editedHero);
$edited = substr($original['content'], 0, $heroStart) . $editedHero . substr($original['content'], $heroEnd);
$edited = str_replace('Pronta entrega', 'Beneficio sentinela', $edited);
$passed = false;

try {
    wp_update_post(['ID' => $homeId, 'post_content' => $edited]);
    update_post_meta($homeId, '_petshop_home_schema_version', 7);
    update_option('petshop_storefront_version', 'session-02-persistence-test', false);
    delete_option('petshop_storefront_migration_error');
    Petshop\Core\StorefrontExperience::maybeEnsureStorefront();
    $after = (string) get_post_field('post_content', $homeId);
    $passed = str_contains($after, 'Titulo institucional sentinela')
        && str_contains($after, 'Alt institucional sentinela')
        && str_contains($after, $newUrl)
        && str_contains($after, home_url('/colecoes/'))
        && str_contains($after, home_url('/atendimento/'))
        && str_contains($after, 'Beneficio sentinela')
        && get_option('petshop_storefront_version') === $targetVersion
        && (int) get_post_meta($homeId, '_petshop_home_schema_version', true) === 9
        && get_option('petshop_storefront_migration_error', '') === '';
} finally {
    wp_update_post(['ID' => $homeId, 'post_content' => $original['content']]);
    update_post_meta($homeId, '_petshop_managed_hero_hash', $original['hash']);
    update_post_meta($homeId, '_petshop_home_schema_version', $original['schema']);
    update_option('petshop_storefront_version', $original['version'], false);
    if ($original['error'] === false) delete_option('petshop_storefront_migration_error');
    else update_option('petshop_storefront_migration_error', $original['error'], false);
}

if (!$passed) WP_CLI::error('Reprovisionamento sobrescreveu titulo, imagem, alt, URLs ou beneficios editados.');
WP_CLI::success('Persistencia do hero e beneficios aprovada apos reprovisionamento.');
