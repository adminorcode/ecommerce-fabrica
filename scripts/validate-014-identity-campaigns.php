<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce ativo.');
}

$failures = [];
$root = dirname(__DIR__);
$themeCss = (string) file_get_contents($root . '/wp-content/themes/petshop-theme/style.css');
$editorCss = (string) file_get_contents($root . '/wp-content/themes/petshop-theme/assets/css/editor-storefront.css');

$requiredTokens = [
    '--brand-teal-900: #004f50',
    '--brand-teal-700: #126e70',
    '--brand-teal-500: #2b9292',
    '--brand-aqua-400: #58c2c7',
    '--brand-orange-600: #e9530d',
    '--brand-orange-500: #f47721',
    '--brand-orange-action: #c94b0b',
    '--neutral-950: #252426',
    '--neutral-700: #5e5d61',
    '--neutral-300: #d8d9db',
    '--neutral-100: #f2f3f4',
    '--cream-50: #faf7f1',
];

foreach ($requiredTokens as $token) {
    if (!str_contains(strtolower($themeCss), $token)) {
        $failures[] = 'Token de identidade ausente no tema: ' . $token;
    }
}

if (!str_contains($themeCss, 'font-family: var(--font-family-base)')) {
    $failures[] = 'Tema nao aplica Nunito Sans/fallback via --font-family-base.';
}

if (!str_contains($themeCss, 'background: #373435;')) {
    $failures[] = 'Rodape institucional nao voltou ao tom escuro anterior aprovado.';
}

if (!str_contains($themeCss, '.petshop-home-campaigns__slide--editorial')) {
    $failures[] = 'CSS da campanha editorial nao encontrado.';
}

if (!str_contains($editorCss, 'petshop-home-campaign-editor')) {
    $failures[] = 'CSS do editor nao cobre o bloco de campanha.';
}

if (!WP_Block_Type_Registry::get_instance()->is_registered('petshop/home-campaign')) {
    $failures[] = 'Bloco petshop/home-campaign nao registrado.';
}

$shopUrl = (string) wc_get_page_permalink('shop');
$attachmentId = (int) get_option('petshop_support_banner_attachment_id', 0);
if ($attachmentId <= 0) {
    $attachments = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);
    $attachmentId = (int) ($attachments[0] ?? 0);
}

if ($attachmentId <= 0) {
    $failures[] = 'Nenhuma imagem disponivel para validar campanhas.';
}

$imageUrl = $attachmentId > 0 ? (string) wp_get_attachment_url($attachmentId) : '';
$block = static function (array $attrs): string {
    return '<!-- wp:petshop/home-campaign ' . wp_json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ' /-->';
};

$legacyAttrs = [
    'desktopImageId' => $attachmentId,
    'desktopImageUrl' => $imageUrl,
    'mobileImageId' => 0,
    'mobileImageUrl' => '',
    'imageAlt' => 'Campanha legado Plano 014',
    'linkUrl' => $shopUrl,
    'editorLabel' => 'Legado',
];
$editorialAttrs = array_merge($legacyAttrs, [
    'campaignMode' => 'editorial',
    'imageAlt' => 'Pessoa ajustando acessorio em pet para campanha editorial',
    'eyebrow' => 'Colecao especial',
    'title' => 'Acessorios com acabamento profissional',
    'text' => 'Escolha pecas para banho e tosa, presentes e reposicao.',
    'benefit' => 'Produtos a pronta entrega.',
    'ctaLabel' => 'Ver produtos',
]);
$invalidEditorialAttrs = array_merge($editorialAttrs, [
    'title' => '',
]);

$legacyHtml = (string) do_blocks('<!-- wp:petshop/home-campaigns -->' . $block($legacyAttrs) . '<!-- /wp:petshop/home-campaigns -->');
if (!str_contains($legacyHtml, 'petshop-home-campaigns__link')) {
    $failures[] = 'Campanha legada de arte final deixou de renderizar como banner clicavel.';
}

$editorialHtml = (string) do_blocks('<!-- wp:petshop/home-campaigns -->' . $block($editorialAttrs) . '<!-- /wp:petshop/home-campaigns -->');
if (!str_contains($editorialHtml, 'petshop-home-campaigns__slide--editorial')) {
    $failures[] = 'Campanha editorial nao renderizou a variante visual.';
}
if (!str_contains($editorialHtml, 'Acessorios com acabamento profissional') || !str_contains($editorialHtml, 'Ver produtos')) {
    $failures[] = 'Campanha editorial nao renderizou copy e CTA administraveis.';
}

$invalidHtml = (string) do_blocks('<!-- wp:petshop/home-campaigns -->' . $block($invalidEditorialAttrs) . '<!-- /wp:petshop/home-campaigns -->');
if ($invalidHtml !== '') {
    $failures[] = 'Campanha editorial incompleta deveria ser omitida.';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Validacao do Plano 014 reprovada.');
}

WP_CLI::success('Identidade visual e campanhas editoriais do Plano 014 aprovadas.');
