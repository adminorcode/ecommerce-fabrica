<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('Execute esta validacao com WP-CLI.');
}

$failures = [];
$keys = [
    'petshop_footer_copyright',
    'petshop_footer_legal_name',
    'petshop_footer_cnpj',
    'petshop_footer_address',
    'petshop_footer_payment_text',
];
$original = [];
foreach ($keys as $key) {
    $original[$key] = get_theme_mod($key, null);
}

$renderFooter = static function (): string {
    ob_start();
    petshop_render_institutional_footer();

    return (string) ob_get_clean();
};

$legalCopy = static function (string $html): string {
    $start = strpos($html, 'petshop-institutional-footer__legal-copy');
    if ($start === false) {
        return '';
    }
    $start = strpos($html, '>', $start);
    if ($start === false) {
        return '';
    }
    $end = strpos($html, '</div>', $start);
    if ($end === false) {
        return '';
    }

    return substr($html, $start + 1, $end - $start - 1);
};

$paragraphs = static function (string $html): array {
    if (!preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $html, $matches)) {
        return [];
    }

    return array_map(
        static fn (string $paragraph): string => trim(wp_strip_all_tags($paragraph)),
        $matches[1]
    );
};

try {
    set_theme_mod('petshop_footer_copyright', '© 2026 Loja 033');
    set_theme_mod('petshop_footer_legal_name', 'Razao 033 ME');
    set_theme_mod('petshop_footer_cnpj', '11.111.111/0001-33');
    set_theme_mod('petshop_footer_address', 'Rua Validacao 033, 100');
    set_theme_mod('petshop_footer_payment_text', 'Pix e cartao 033');

    $fullCopy = $legalCopy($renderFooter());
    $fullParagraphs = $paragraphs($fullCopy);
    if (count($fullParagraphs) !== 2) {
        $failures[] = 'Faixa legal com endereco e pagamento deveria renderizar exatamente 2 paragrafos.';
    }
    if (($fullParagraphs[0] ?? '') !== '© 2026 Loja 033 – Razao 033 ME – CNPJ 11.111.111/0001-33') {
        $failures[] = 'Primeira linha legal nao preservou copyright, razao social e CNPJ.';
    }
    if (!str_contains($fullParagraphs[1] ?? '', 'Rua Validacao 033, 100') || !str_contains($fullParagraphs[1] ?? '', 'Pix e cartao 033')) {
        $failures[] = 'Endereco e pagamento deveriam compartilhar o segundo paragrafo legal.';
    }
    if (substr_count($fullCopy, 'Pix e cartao 033') !== 1) {
        $failures[] = 'Pagamento deveria aparecer exatamente uma vez na faixa legal.';
    }
    if (!str_contains($fullCopy, 'petshop-institutional-footer__legal-separator')) {
        $failures[] = 'Separador acessorio entre endereco e pagamento ausente.';
    }

    set_theme_mod('petshop_footer_address', '');
    set_theme_mod('petshop_footer_payment_text', 'Somente pagamento 033');
    $paymentOnlyParagraphs = $paragraphs($legalCopy($renderFooter()));
    if (count($paymentOnlyParagraphs) !== 2 || ($paymentOnlyParagraphs[1] ?? '') !== 'Somente pagamento 033') {
        $failures[] = 'Pagamento sem endereco deveria ocupar a segunda linha sem paragrafo vazio.';
    }

    set_theme_mod('petshop_footer_address', 'Somente endereco 033');
    set_theme_mod('petshop_footer_payment_text', '');
    $addressOnlyParagraphs = $paragraphs($legalCopy($renderFooter()));
    if (count($addressOnlyParagraphs) !== 2 || ($addressOnlyParagraphs[1] ?? '') !== 'Somente endereco 033') {
        $failures[] = 'Endereco sem pagamento deveria ocupar a segunda linha sem paragrafo vazio.';
    }

    set_theme_mod('petshop_footer_address', '');
    set_theme_mod('petshop_footer_payment_text', '');
    $firstLineOnlyParagraphs = $paragraphs($legalCopy($renderFooter()));
    if (count($firstLineOnlyParagraphs) !== 1) {
        $failures[] = 'Endereco e pagamento vazios nao deveriam produzir linha legal vazia.';
    }
} finally {
    foreach ($original as $key => $value) {
        if ($value === null) {
            remove_theme_mod($key);
        } else {
            set_theme_mod($key, $value);
        }
    }
}

if ($failures !== []) {
    WP_CLI::error("validate-033-footer falhou:\n- " . implode("\n- ", $failures));
}

WP_CLI::success('validate-033-footer: passed');
