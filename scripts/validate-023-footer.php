<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('Execute esta validacao com WP-CLI.');
}

use Petshop\Core\Settings\DefaultSettings;

$failures = [];

$requiredKeys = [
    'petshop_footer_description',
    'petshop_footer_instagram',
    'petshop_footer_facebook',
    'petshop_footer_tiktok',
    'petshop_footer_social_whatsapp',
    'petshop_footer_whatsapp',
    'petshop_footer_whatsapp_label',
    'petshop_footer_email',
    'petshop_footer_support_text',
    'petshop_footer_hours',
    'petshop_footer_faq_url',
    'petshop_footer_faq_text',
    'petshop_footer_payment_text',
    'petshop_footer_trust_1_title',
    'petshop_footer_trust_1_text',
    'petshop_footer_trust_2_title',
    'petshop_footer_trust_2_text',
    'petshop_footer_trust_3_title',
    'petshop_footer_trust_3_text',
    'petshop_footer_trust_4_title',
    'petshop_footer_trust_4_text',
    'petshop_footer_copyright',
    'petshop_footer_legal_name',
    'petshop_footer_cnpj',
    'petshop_footer_address',
];

$definitions = DefaultSettings::definitions();
foreach ($requiredKeys as $key) {
    if (!isset($definitions[$key])) {
        $failures[] = "Setting ausente em DefaultSettings: {$key}";
        continue;
    }
    if (($definitions[$key]['section'] ?? '') !== 'petshop_footer') {
        $failures[] = "Setting {$key} deveria estar na secao petshop_footer.";
    }
}

$faqProbe = 'https://example.com/faq-023-unique/';
$fixture = [
    'petshop_footer_instagram' => 'https://instagram.com/petshop023unique',
    'petshop_footer_facebook' => 'https://facebook.com/petshop023unique',
    'petshop_footer_tiktok' => 'https://tiktok.com/@petshop023unique',
    'petshop_footer_social_whatsapp' => 'https://wa.me/5511900000023',
    'petshop_footer_whatsapp' => 'https://wa.me/5511900000123',
    'petshop_footer_whatsapp_label' => 'Fale conosco 023unique',
    'petshop_footer_email' => 'atendimento023unique@example.com',
    'petshop_footer_support_text' => 'Atendimento ao cliente 023unique',
    'petshop_footer_hours' => 'Seg a Sex 9h-18h 023unique',
    'petshop_footer_faq_url' => $faqProbe,
    'petshop_footer_faq_text' => 'Tire suas duvidas 023unique',
    'petshop_footer_trust_1_title' => 'Selo um 023unique',
    'petshop_footer_trust_1_text' => 'Descricao selo um 023unique',
    'petshop_footer_trust_2_title' => 'Selo dois 023unique',
    'petshop_footer_trust_2_text' => 'Descricao selo dois 023unique',
    'petshop_footer_copyright' => '© 2026 Loja 023unique',
    'petshop_footer_legal_name' => 'Razao Social 023unique MEI',
    'petshop_footer_cnpj' => '00.000.000/0001-23',
    'petshop_footer_address' => 'Rua Validacao 023unique, 100',
];

$original = [];
foreach (array_keys($fixture) as $key) {
    $original[$key] = get_theme_mod($key, null);
}
$originalSupportPage = get_theme_mod('petshop_support_page', null);
$createdSupportPageId = null;

$supportPage = get_page_by_path('atendimento');
if (!$supportPage instanceof WP_Post) {
    $supportPageId = wp_insert_post([
        'post_title' => 'Atendimento',
        'post_name' => 'atendimento',
        'post_status' => 'publish',
        'post_type' => 'page',
    ]);
    if (is_wp_error($supportPageId) || $supportPageId <= 0) {
        WP_CLI::error('Nao foi possivel garantir pagina de atendimento para o gate 023.');
    }
    $createdSupportPageId = (int) $supportPageId;
    $supportPage = get_post($createdSupportPageId);
}

try {
    foreach ($fixture as $key => $value) {
        set_theme_mod($key, $value);
    }
    set_theme_mod('petshop_support_page', (int) $supportPage->ID);

    $requestUrl = 'http://wordpress/';
    $filled = wp_remote_get($requestUrl, [
        'timeout' => 30,
        'redirection' => 0,
        'headers' => [
            'Host' => 'localhost:8888',
        ],
    ]);
    if (is_wp_error($filled)) {
        $failures[] = 'Falha ao carregar home com rodape preenchido: ' . $filled->get_error_message();
    } else {
        $body = (string) wp_remote_retrieve_body($filled);
        if (!str_contains($body, 'petshop-institutional-footer')) {
            $failures[] = 'Rodape institucional ausente na home.';
        }
        foreach ([
            'https://instagram.com/petshop023unique',
            'https://facebook.com/petshop023unique',
            'https://tiktok.com/@petshop023unique',
            'https://wa.me/5511900000023',
            'Fale conosco 023unique',
            'atendimento023unique@example.com',
            'Atendimento ao cliente 023unique',
            'Seg a Sex 9h-18h 023unique',
            'Tire suas duvidas 023unique',
            'Selo um 023unique',
            'Selo dois 023unique',
            '© 2026 Loja 023unique',
            'Razao Social 023unique MEI',
            '00.000.000/0001-23',
            'Rua Validacao 023unique, 100',
            'aria-label="Instagram"',
            'Siga-nos',
            'petshop-institutional-footer__brand',
            'petshop-institutional-footer__social',
            'petshop-institutional-footer__contact-row',
            'petshop-institutional-footer__contact-icon',
            'petshop-institutional-footer__trust',
            'petshop-institutional-footer__social-icon',
            'petshop-institutional-footer__trust-icon',
            'data-icon="instagram"',
            'data-icon="shield"',
            'data-icon="award"',
            'WhatsApp',
            'E-mail',
            'Horário de atendimento',
            'Perguntas frequentes',
            'rel="noopener noreferrer"',
        ] as $needle) {
            if (!str_contains($body, $needle)) {
                $failures[] = 'Conteudo esperado ausente no rodape: ' . $needle;
            }
        }
        if (str_contains($body, 'petshop-institutional-footer__extras')) {
            $failures[] = 'Coluna extras nao deveria existir; redes ficam na coluna da marca.';
        }
        $brandPos = strpos($body, 'petshop-institutional-footer__brand');
        $socialPos = strpos($body, 'petshop-institutional-footer__social');
        $contactPos = strpos($body, 'petshop-institutional-footer__contact');
        if ($brandPos === false || $socialPos === false || $socialPos < $brandPos || ($contactPos !== false && $socialPos > $contactPos)) {
            $failures[] = 'Redes sociais deveriam estar na coluna da marca, antes do atendimento.';
        }
    }

    foreach (array_keys($fixture) as $key) {
        remove_theme_mod($key);
    }
    remove_theme_mod('petshop_support_page');

    $empty = wp_remote_get($requestUrl, [
        'timeout' => 30,
        'redirection' => 0,
        'headers' => [
            'Host' => 'localhost:8888',
        ],
    ]);
    if (is_wp_error($empty)) {
        $failures[] = 'Falha ao carregar home com rodape esvaziado: ' . $empty->get_error_message();
    } else {
        $emptyBody = (string) wp_remote_retrieve_body($empty);
        foreach ($fixture as $key => $value) {
            if ($value === '' || !str_contains($emptyBody, (string) $value)) {
                continue;
            }
            $failures[] = "Campo esvaziado ainda aparece no rodape ({$key}): {$value}";
        }
    }

    set_theme_mod('petshop_footer_instagram', 'https://instagram.com/petshop023-persist');
    $reloaded = get_theme_mod('petshop_footer_instagram', '');
    if ($reloaded !== 'https://instagram.com/petshop023-persist') {
        $failures[] = 'Persistencia de theme_mod Instagram falhou.';
    }
    remove_theme_mod('petshop_footer_instagram');
} finally {
    foreach ($original as $key => $value) {
        if ($value === null) {
            remove_theme_mod($key);
        } else {
            set_theme_mod($key, $value);
        }
    }
    if ($originalSupportPage === null) {
        remove_theme_mod('petshop_support_page');
    } else {
        set_theme_mod('petshop_support_page', $originalSupportPage);
    }
    if ($createdSupportPageId !== null) {
        wp_delete_post($createdSupportPageId, true);
    }
}

if ($failures !== []) {
    WP_CLI::error("validate-023-footer falhou:\n- " . implode("\n- ", $failures));
}

WP_CLI::success('validate-023-footer: passed');
