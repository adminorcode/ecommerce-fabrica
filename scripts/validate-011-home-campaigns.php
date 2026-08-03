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
    $failures[] = 'Nenhuma imagem disponivel para testar banners de campanha';
}

$desktopUrl = $heroAttachment > 0 ? (string) wp_get_attachment_url($heroAttachment) : '';
$campaignAttrs = [
    'desktopImageId' => $heroAttachment,
    'desktopImageUrl' => $desktopUrl,
    'mobileImageId' => $heroAttachment,
    'mobileImageUrl' => $desktopUrl,
    'imageAlt' => 'Campanha de teste 011',
    'linkUrl' => $shopUrl,
    'editorLabel' => 'Campanha sentinela',
];
$campaignTwoAttrs = array_merge($campaignAttrs, [
    'imageAlt' => 'Segunda campanha de teste 011',
    'editorLabel' => 'Campanha sentinela 2',
]);

$buildCampaign = static function (array $attrs): string {
    $json = wp_json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return '<!-- wp:petshop/home-campaign ' . $json . ' /-->';
};

$singleBlock = '<!-- wp:petshop/home-campaigns -->'
    . $buildCampaign($campaignAttrs)
    . '<!-- /wp:petshop/home-campaigns -->';
$carouselBlock = '<!-- wp:petshop/home-campaigns -->'
    . $buildCampaign($campaignAttrs)
    . $buildCampaign($campaignTwoAttrs)
    . '<!-- /wp:petshop/home-campaigns -->';
$incompleteBlock = '<!-- wp:petshop/home-campaigns -->'
    . '<!-- wp:petshop/home-campaign {"desktopImageId":0,"imageAlt":"","linkUrl":""} /-->'
    . $buildCampaign($campaignAttrs)
    . '<!-- /wp:petshop/home-campaigns -->';

$singleHtml = (string) do_blocks($singleBlock);
if ($singleHtml === '') {
    $failures[] = 'Banner unico valido nao renderizou';
}
if (str_contains($singleHtml, 'petshop-home-campaigns__controls')) {
    $failures[] = 'Banner unico nao deveria exibir controles de carrossel';
}
if (!str_contains($singleHtml, '<picture')) {
    $failures[] = 'Banner com imagem mobile deveria usar picture';
}

$carouselHtml = (string) do_blocks($carouselBlock);
if (!str_contains($carouselHtml, 'petshop-home-campaigns__controls')) {
    $failures[] = 'Carrossel com duas campanhas deveria exibir controles';
}
if (!str_contains($carouselHtml, 'petshop-home-campaigns__prev')) {
    $failures[] = 'Carrossel deveria incluir botao anterior';
}

$incompleteHtml = (string) do_blocks($incompleteBlock);
$incompleteSlides = substr_count($incompleteHtml, 'petshop-home-campaigns__slide');
if ($incompleteHtml === '' || $incompleteSlides !== 1) {
    $failures[] = 'Campanha incompleta deveria ser ignorada mantendo apenas banners validos';
}

$emptyHtml = (string) do_blocks('<!-- wp:petshop/home-campaigns /-->');
if ($emptyHtml !== '') {
    $failures[] = 'Conteiner sem campanhas validas deveria renderizar vazio';
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

$anchor = '<!-- wp:group {"className":"petshop-section","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section">';
$anchorPos = strpos($original['content'], '<h2 class="wp-block-heading">Compre por categoria</h2>');
if ($anchorPos === false) {
    $failures[] = 'Secao Compre por categoria nao encontrada para teste de persistencia';
    $passedPersistence = false;
} else {
    $beforeCategories = strrpos(substr($original['content'], 0, $anchorPos), '<!-- /wp:group -->');
    $insertPos = $beforeCategories === false ? $anchorPos : $beforeCategories + strlen('<!-- /wp:group -->');
    $sentinelAlt = 'Campanha persistencia 011 sentinela';
    $sentinelCampaign = array_merge($campaignAttrs, ['imageAlt' => $sentinelAlt]);
    $sentinelBlock = '<!-- wp:petshop/home-campaigns -->'
        . $buildCampaign($sentinelCampaign)
        . '<!-- /wp:petshop/home-campaigns -->';
    $edited = substr($original['content'], 0, $insertPos)
        . "\n" . $sentinelBlock . "\n"
        . substr($original['content'], $insertPos);

    $passedPersistence = false;

    try {
        wp_update_post(['ID' => $homeId, 'post_content' => $edited]);
        update_option('petshop_storefront_version', 'session-011-persistence-test', false);
        delete_option('petshop_storefront_migration_error');
        Petshop\Core\StorefrontExperience::maybeEnsureStorefront();

        $after = (string) get_post_field('post_content', $homeId);
        $passedPersistence = str_contains($after, 'petshop/home-campaigns')
            && str_contains($after, $sentinelAlt)
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
        $failures[] = 'Reprovisionamento removeu ou alterou banners de campanha salvos na Home';
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Validacao PHP do Plano 011 reprovada.');
}

WP_CLI::success('Blocos, renderizacao e persistencia de banners da Home (Plano 011) aprovados.');
