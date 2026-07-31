<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce ativo.');
}

$failures = [];
$homeId = (int) get_option('page_on_front');
$content = (string) get_post_field('post_content', $homeId);
$flat = [];
$walk = static function (array $blocks) use (&$walk, &$flat): void {
    foreach ($blocks as $block) {
        $flat[] = $block;
        if (!empty($block['innerBlocks'])) {
            $walk($block['innerBlocks']);
        }
    }
};
$walk(parse_blocks($content));

$hero = null;
$benefits = null;
foreach ($flat as $block) {
    $className = (string) ($block['attrs']['className'] ?? '');
    if (($block['blockName'] ?? '') === 'core/cover' && str_contains($className, 'petshop-hero')) {
        $hero = $block;
    }
    if (($block['blockName'] ?? '') === 'core/group' && $className === 'petshop-benefits') {
        $benefits = $block;
    }
}

if ($hero === null) {
    $failures[] = 'Hero Gutenberg ausente';
} else {
    $heroHtml = render_block($hero);
    if (($hero['attrs']['align'] ?? '') !== 'full') {
        $failures[] = 'Hero nao esta full-bleed';
    }
    if ((int) ($hero['attrs']['id'] ?? 0) <= 0 || trim((string) ($hero['attrs']['alt'] ?? '')) === '') {
        $failures[] = 'Imagem ou alt editavel ausente no hero';
    }
    if (substr_count(strtolower($heroHtml), '<h1') !== 1 || !preg_match('/<h1\b[^>]*>\s*.+?\s*<\/h1>/is', $heroHtml)) {
        $failures[] = 'H1 institucional unico e nao vazio ausente';
    }
    if (stripos($heroHtml, 'Dia dos Pais') !== false) {
        $failures[] = 'Campanha sazonal ainda esta no hero';
    }
    if (substr_count($heroHtml, 'wp-block-button__link') !== 2) {
        $failures[] = 'Hero deve ter exatamente dois CTAs editaveis';
    }
}

if ($benefits === null) {
    $failures[] = 'Faixa Gutenberg de beneficios ausente';
} else {
    $benefitParagraphs = [];
    $collectBenefitParagraphs = static function (array $blocks) use (&$collectBenefitParagraphs, &$benefitParagraphs): void {
        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/paragraph') {
                $benefitParagraphs[] = trim(wp_strip_all_tags((string) ($block['innerHTML'] ?? '')));
            }
            if (!empty($block['innerBlocks'])) $collectBenefitParagraphs($block['innerBlocks']);
        }
    };
    $collectBenefitParagraphs($benefits['innerBlocks'] ?? []);
    if (count($benefitParagraphs) !== 3 || array_filter($benefitParagraphs, static fn (string $text): bool => $text === '') !== []) {
        $failures[] = 'Faixa deve conter tres beneficios editaveis e nao vazios';
    }
}

if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 9) {
    $failures[] = 'Schema institucional da Home nao confirmado';
}
$fatherTerm = get_term_by('slug', 'dia-dos-pais', 'product_cat');
if (!$fatherTerm instanceof WP_Term || !(bool) get_term_meta($fatherTerm->term_id, 'petshop_seasonal', true)) {
    $failures[] = 'Dia dos Pais nao esta disponivel como conteudo sazonal secundario';
}

if ($failures !== []) {
    foreach ($failures as $failure) WP_CLI::warning($failure);
    WP_CLI::error('Sessao 02 invalida: ' . count($failures) . ' falha(s).');
}

WP_CLI::success('Sessao 02 valida: hero e beneficios Gutenberg editaveis, dois CTAs e campanha sazonal secundaria.');
