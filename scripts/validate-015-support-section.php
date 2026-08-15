<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce ativo.');
}

$failures = [];
$validWhatsApp = 'https://wa.me/5511999999999';
$invalidUrl = 'https://example.com/atendimento';
$supportUrl = home_url('/atendimento/');

$originalThemeMods = [
    'petshop_footer_whatsapp' => get_theme_mod('petshop_footer_whatsapp', null),
    'petshop_support_banner_url' => get_theme_mod('petshop_support_banner_url', null),
];

try {
    set_theme_mod('petshop_footer_whatsapp', $validWhatsApp);
    set_theme_mod('petshop_support_banner_url', $invalidUrl);

    $images = Petshop\Core\Provisioning\StorefrontProvisioner::supportSectionAttachments();
    if ($images['desktop'] <= 0 || $images['mobile'] <= 0) {
        $failures[] = 'Attachments desktop/mobile da secao de atendimento nao foram provisionados.';
    }

    foreach ([
        'desktop' => [1920, 640],
        'mobile' => [1080, 1350],
    ] as $key => $expected) {
        $metadata = wp_get_attachment_metadata($images[$key]);
        if (($metadata['width'] ?? 0) !== $expected[0] || ($metadata['height'] ?? 0) !== $expected[1]) {
            $failures[] = sprintf(
                'Imagem %s deveria ter %dx%d px; recebido %sx%s.',
                $key,
                $expected[0],
                $expected[1],
                (string) ($metadata['width'] ?? '0'),
                (string) ($metadata['height'] ?? '0')
            );
        }
    }

    if (Petshop\Core\Storefront\SupportContent::resolveWhatsAppUrl($invalidUrl) !== $validWhatsApp) {
        $failures[] = 'Resolucao de WhatsApp deveria preferir URL global wa.me valida.';
    }
    if (Petshop\Core\Storefront\SupportContent::isValidWhatsAppUrl($invalidUrl)) {
        $failures[] = 'URL que nao e wa.me foi aceita como WhatsApp valido.';
    }
    $cta = Petshop\Core\Storefront\SupportContent::resolveSupportCta($supportUrl);
    if ($cta['url'] !== $validWhatsApp || $cta['label'] !== 'Falar pelo WhatsApp') {
        $failures[] = 'CTA deveria usar WhatsApp valido e rotulo de WhatsApp quando configurado.';
    }

    $markup = Petshop\Core\Storefront\SupportContent::supportSectionContent(
        $images['desktop'],
        $images['mobile'],
        $validWhatsApp,
        'Falar pelo WhatsApp'
    );
    foreach ([
        'wp:group',
        'wp:image',
        'wp:heading',
        'wp:paragraph',
        'wp:buttons',
        'petshop-support-banner__content',
        'petshop-support-banner__media',
        'petshop-support-banner__image--desktop',
        'petshop-support-banner__image--mobile',
        'Falar pelo WhatsApp',
        $validWhatsApp,
    ] as $needle) {
        if (!str_contains($markup, $needle)) {
            $failures[] = 'Markup da secao de atendimento nao contem: ' . $needle;
        }
    }
    if (str_contains($markup, '[petshop_support_banner]') || substr_count($markup, '<!-- wp:button ') !== 1) {
        $failures[] = 'Markup deveria ter exatamente um botao Gutenberg e nenhum shortcode de atendimento.';
    }

    remove_theme_mod('petshop_footer_whatsapp');
    remove_theme_mod('petshop_support_banner_url');
    $fallbackCta = Petshop\Core\Storefront\SupportContent::resolveSupportCta($supportUrl);
    if ($fallbackCta['url'] !== $supportUrl || $fallbackCta['label'] !== 'Falar com atendimento') {
        $failures[] = 'Sem WhatsApp, CTA deveria usar fallback para pagina de atendimento.';
    }
    $fallbackMarkup = Petshop\Core\Storefront\SupportContent::supportSectionContent(
        $images['desktop'],
        $images['mobile'],
        $fallbackCta['url'],
        $fallbackCta['label']
    );
    if (!str_contains($fallbackMarkup, 'wp:button') || !str_contains($fallbackMarkup, 'Falar com atendimento')) {
        $failures[] = 'Fallback para atendimento deveria provisionar botao funcional.';
    }
    set_theme_mod('petshop_footer_whatsapp', $validWhatsApp);
    set_theme_mod('petshop_support_banner_url', $invalidUrl);

    $legacyId = Petshop\Core\Provisioning\StorefrontProvisioner::supportBannerAttachment();
    $legacy = Petshop\Core\Storefront\SupportContent::supportBannerContent($legacyId, $validWhatsApp);
    $schema26 = Petshop\Core\Migration\HomeMigrator::registry()[26];
    $migrated = $schema26($legacy, '', $supportUrl);
    $second = $schema26($migrated, '', $supportUrl);
    if (!str_contains($migrated, 'petshop-support-banner__content')) {
        $failures[] = 'Schema 26 nao substituiu o banner legado por secao editorial.';
    }
    if (str_contains($migrated, 'petshop-support-banner__link') || str_contains($migrated, 'linkDestination')) {
        $failures[] = 'Schema 26 manteve comportamento de imagem unica clicavel.';
    }
    if ($migrated !== $second) {
        $failures[] = 'Schema 26 deveria ser idempotente.';
    }

    $shortcodeMigrated = $schema26('<!-- wp:shortcode -->[petshop_support_banner]<!-- /wp:shortcode -->', '', $supportUrl);
    if (!str_contains($shortcodeMigrated, 'petshop-support-banner__content')) {
        $failures[] = 'Schema 26 nao migrou shortcode legado de atendimento.';
    }

    $legacyWithoutId = '<!-- wp:group {"className":"petshop-section petshop-support-banner","layout":{"type":"constrained"}} -->'
        . '<div class="wp-block-group petshop-section petshop-support-banner">'
        . '<!-- wp:image {"className":"size-full petshop-support-banner__image"} -->'
        . '<figure class="wp-block-image size-full petshop-support-banner__image"><a href="https://example.test/atendimento/">'
        . '<img src="https://example.test/wp-content/uploads/banner-whatsapp-atendimento-1.png" alt="Atendimento"/></a></figure>'
        . '<!-- /wp:image --></div><!-- /wp:group -->';
    if (!Petshop\Core\Migration\HomeMigrator::needsSupportSectionRepair($legacyWithoutId)) {
        $failures[] = 'Reparo do schema 26 deveria detectar banner legado sem ID de attachment.';
    }
    if (!str_contains($schema26($legacyWithoutId, '', $supportUrl), 'petshop-support-banner__content')) {
        $failures[] = 'Schema 26 nao migrou banner legado sem ID de attachment.';
    }

    $managedWithoutCta = Petshop\Core\Storefront\SupportContent::supportSectionContent(
        $images['desktop'],
        $images['mobile'],
        '',
        ''
    );
    if (!Petshop\Core\Migration\HomeMigrator::needsSupportSectionRepair($managedWithoutCta)) {
        $failures[] = 'Reparo do schema 26 deveria detectar secao padrao provisionada sem CTA.';
    }
    remove_theme_mod('petshop_footer_whatsapp');
    remove_theme_mod('petshop_support_banner_url');
    if (!str_contains($schema26($managedWithoutCta, '', $supportUrl), 'Falar com atendimento')) {
        $failures[] = 'Schema 26 nao reparou secao padrao sem CTA com fallback de atendimento.';
    }
    set_theme_mod('petshop_footer_whatsapp', $validWhatsApp);
    set_theme_mod('petshop_support_banner_url', $invalidUrl);

    $custom = '<!-- wp:group {"className":"petshop-section petshop-support-banner","layout":{"type":"constrained"}} -->'
        . '<div class="wp-block-group petshop-section petshop-support-banner">'
        . '<!-- wp:image {"id":' . $images['desktop'] . ',"className":"petshop-support-banner__image"} -->'
        . '<figure class="wp-block-image petshop-support-banner__image"><img class="wp-image-' . $images['desktop'] . '" /></figure>'
        . '<!-- /wp:image --></div><!-- /wp:group -->';
    if ($schema26($custom, '', $supportUrl) !== $custom) {
        $failures[] = 'Schema 26 nao deveria alterar secao de atendimento customizada pelo cliente.';
    }

    $homeId = (int) get_option('page_on_front');
    if ($homeId <= 0) {
        $failures[] = 'Home estatica nao configurada para teste de migracao real.';
    } else {
        $original = [
            'content' => (string) get_post_field('post_content', $homeId),
            'schema' => get_post_meta($homeId, '_petshop_home_schema_version', true),
            'managed' => get_post_meta($homeId, '_petshop_managed_page', true),
            'version' => get_option('petshop_storefront_version'),
            'error' => get_option('petshop_storefront_migration_error'),
        ];

        try {
            wp_update_post(['ID' => $homeId, 'post_content' => wp_slash($legacy)]);
            update_post_meta($homeId, '_petshop_managed_page', 1);
            update_post_meta($homeId, '_petshop_home_schema_version', 25);
            update_option('petshop_storefront_version', '3.1.0', false);
            delete_option('petshop_storefront_migration_error');

            Petshop\Core\StorefrontExperience::maybeEnsureStorefront();

            $after = (string) get_post_field('post_content', $homeId);
            if (!str_contains($after, 'petshop-support-banner__content')) {
                $failures[] = 'Fluxo real de migracao nao persistiu secao editorial na Home.';
            }
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 26) {
                $failures[] = 'Fluxo real de migracao nao avancou schema da Home para 26.';
            }
            if (get_option('petshop_storefront_migration_error', '') !== '') {
                $failures[] = 'Fluxo real de migracao registrou erro de storefront.';
            }
        } finally {
            wp_update_post(['ID' => $homeId, 'post_content' => wp_slash((string) $original['content'])]);
            if ($original['managed'] === '') {
                delete_post_meta($homeId, '_petshop_managed_page');
            } else {
                update_post_meta($homeId, '_petshop_managed_page', $original['managed']);
            }
            update_post_meta($homeId, '_petshop_home_schema_version', $original['schema']);
            update_option('petshop_storefront_version', $original['version'], false);
            if ($original['error'] === false) {
                delete_option('petshop_storefront_migration_error');
            } else {
                update_option('petshop_storefront_migration_error', $original['error'], false);
            }
        }
    }
} finally {
    foreach ($originalThemeMods as $key => $value) {
        if ($value === null) {
            remove_theme_mod($key);
        } else {
            set_theme_mod($key, $value);
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Validacao PHP do Plano 015 reprovada.');
}

WP_CLI::success('Secao de atendimento, WhatsApp e migracao do Plano 015 aprovados.');
