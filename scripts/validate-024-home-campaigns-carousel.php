<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce ativo.');
}

$failures = [];

if (!WP_Block_Type_Registry::get_instance()->is_registered('petshop/home-campaigns')) {
    $failures[] = 'Bloco petshop/home-campaigns nao registrado';
}

if (!WP_Block_Type_Registry::get_instance()->is_registered('petshop/home-campaign')) {
    $failures[] = 'Bloco petshop/home-campaign nao registrado';
}

$shopUrl = (string) wc_get_page_permalink('shop');
$heroAttachment = (int) get_option('petshop_support_banner_attachment_id', 0);
if ($heroAttachment <= 0) {
    $attachments = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);
    $heroAttachment = (int) ($attachments[0] ?? 0);
}

if ($heroAttachment <= 0) {
    $failures[] = 'Nenhuma imagem disponivel para testar o carrossel promocional';
}

$desktopUrl = $heroAttachment > 0 ? (string) wp_get_attachment_url($heroAttachment) : '';
$baseAttrs = [
    'desktopImageId' => $heroAttachment,
    'desktopImageUrl' => $desktopUrl,
    'mobileImageId' => $heroAttachment,
    'mobileImageUrl' => $desktopUrl,
    'imageAlt' => 'Campanha de teste 024',
    'linkUrl' => $shopUrl,
    'editorLabel' => 'Campanha sentinela 024',
];

$buildCampaign = static function (array $attrs) use ($baseAttrs): string {
    $json = wp_json_encode(array_merge($baseAttrs, $attrs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return '<!-- wp:petshop/home-campaign ' . $json . ' /-->';
};

$wrap = static function (string $inner): string {
    return '<!-- wp:petshop/home-campaigns -->' . $inner . '<!-- /wp:petshop/home-campaigns -->';
};

if (Petshop\Core\HomeCampaignBlocks::sanitizeDurationSeconds(null) !== 10) {
    $failures[] = 'Duracao padrao deveria ser 10 segundos';
}

if (Petshop\Core\HomeCampaignBlocks::sanitizeDurationSeconds(7) !== 7) {
    $failures[] = 'Duracao 7 deveria ser preservada';
}

if (Petshop\Core\HomeCampaignBlocks::sanitizeDurationSeconds(0) !== 10) {
    $failures[] = 'Duracao 0 deveria cair no padrao 10';
}

if (Petshop\Core\HomeCampaignBlocks::sanitizeDurationSeconds(2) !== 3) {
    $failures[] = 'Duracao 2 deveria ser limitada ao minimo 3';
}

if (Petshop\Core\HomeCampaignBlocks::sanitizeDurationSeconds(999) !== 60) {
    $failures[] = 'Duracao 999 deveria ser limitada ao maximo 60';
}

$singleHtml = (string) do_blocks($wrap($buildCampaign(['imageAlt' => 'Unico 024'])));
if ($singleHtml === '' || str_contains($singleHtml, 'petshop-home-campaigns__controls')) {
    $failures[] = 'Banner unico deveria renderizar sem controles de carrossel';
}

$carouselHtml = (string) do_blocks(
    $wrap(
        $buildCampaign(['imageAlt' => 'Primeiro 024', 'durationSeconds' => 12])
        . $buildCampaign(['imageAlt' => 'Segundo 024', 'durationSeconds' => 8])
    )
);
if (!str_contains($carouselHtml, 'is-carousel') || !str_contains($carouselHtml, 'petshop-home-campaigns__controls')) {
    $failures[] = 'Dois banners validos deveriam gerar carrossel com controles';
}
if (!str_contains($carouselHtml, 'data-duration-seconds="12"') || !str_contains($carouselHtml, 'data-duration-seconds="8"')) {
    $failures[] = 'Carrossel deveria serializar o tempo de visualizacao por imagem';
}
if (!str_contains($carouselHtml, '<svg') || !str_contains($carouselHtml, 'petshop-home-campaigns__dot')) {
    $failures[] = 'Controles deveriam incluir setas SVG e indicadores';
}

$fourHtml = (string) do_blocks(
    $wrap(
        $buildCampaign(['imageAlt' => 'Um 024'])
        . $buildCampaign(['imageAlt' => 'Dois 024'])
        . $buildCampaign(['imageAlt' => 'Tres 024'])
        . $buildCampaign(['imageAlt' => 'Quatro 024'])
    )
);
$slideCount = substr_count($fourHtml, 'petshop-home-campaigns__slide');
$dotCount = preg_match_all('/class="petshop-home-campaigns__dot[\s"]/', $fourHtml);
if ($slideCount !== 3 || $dotCount !== 3) {
    $failures[] = 'O quarto banner nao deveria ser publicado (esperado 3 slides e 3 indicadores; slides=' . $slideCount . ' dots=' . $dotCount . ')';
}

$legacyHtml = (string) do_blocks($wrap($buildCampaign(['imageAlt' => 'Legado sem duracao'])));
if ($legacyHtml === '' || !str_contains($legacyHtml, 'data-duration-seconds="10"')) {
    $failures[] = 'Banner legado sem duracao deveria usar 10 segundos';
}

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

$anchorPos = strpos($original['content'], '<h2 class="wp-block-heading">Compre por categoria</h2>');
if ($anchorPos === false) {
    $failures[] = 'Secao Compre por categoria nao encontrada para teste de persistencia';
} else {
    $beforeCategories = strrpos(substr($original['content'], 0, $anchorPos), '<!-- /wp:group -->');
    $insertPos = $beforeCategories === false ? $anchorPos : $beforeCategories + strlen('<!-- /wp:group -->');
    $sentinelAlt = 'Campanha persistencia 024 sentinela';
    $sentinelBlock = $wrap($buildCampaign([
        'imageAlt' => $sentinelAlt,
        'durationSeconds' => 15,
        'editorLabel' => 'Sentinela 024',
    ]));
    $edited = substr($original['content'], 0, $insertPos)
        . "\n" . $sentinelBlock . "\n"
        . substr($original['content'], $insertPos);

    $passedPersistence = false;

    try {
        wp_update_post(['ID' => $homeId, 'post_content' => $edited]);
        update_option('petshop_storefront_version', 'session-024-persistence-test', false);
        delete_option('petshop_storefront_migration_error');
        Petshop\Core\StorefrontExperience::maybeEnsureStorefront();

        $after = (string) get_post_field('post_content', $homeId);
        $passedPersistence = str_contains($after, 'petshop/home-campaigns')
            && str_contains($after, $sentinelAlt)
            && str_contains($after, '"durationSeconds":15')
            && get_option('petshop_storefront_version') === $targetVersion
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
        $failures[] = 'Reprovisionamento removeu ou alterou o carrossel promocional salvo na Home';
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Validacao PHP do Plano 024 reprovada.');
}

WP_CLI::success('Carrossel promocional da Home (Plano 024) aprovado.');
