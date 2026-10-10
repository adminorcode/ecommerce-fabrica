<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

function petshop_footer_mod(string $id): string
{
    $default = class_exists(\Petshop\Core\Settings\DefaultSettings::class)
        ? (string) \Petshop\Core\Settings\DefaultSettings::get($id)
        : '';

    return trim((string) get_theme_mod($id, $default));
}

function petshop_footer_icon_svg(string $name): string
{
    $ns = 'xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"';
    $stroke = 'fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"';

    $icons = [
        'instagram' => '<svg ' . $ns . ' ' . $stroke . '><rect x="3.2" y="3.2" width="17.6" height="17.6" rx="5"/><circle cx="12" cy="12" r="4.15"/><circle cx="17.15" cy="6.85" r="1.05" fill="currentColor" stroke="none"/></svg>',
        'facebook' => '<svg ' . $ns . ' fill="currentColor"><path d="M14.12 8.15H16.4V5.4h-2.28c-2.48 0-3.87 1.48-3.87 3.82v1.9H8.1v2.72h2.15V21h2.98v-7.16h2.45l.52-2.72h-2.97v-1.62c0-.78.28-1.35 1.89-1.35z"/></svg>',
        'tiktok' => '<svg ' . $ns . ' fill="currentColor"><path d="M12.53.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
        'whatsapp' => '<svg ' . $ns . ' fill="currentColor"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.4-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.06 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.48.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zm-5.42 7.4h-.004a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26C1.16 5.34 5.6.9 11.05.9c2.64 0 5.12 1.03 6.99 2.9a9.83 9.83 0 0 1 2.89 6.99c0 5.45-4.43 9.89-9.88 9.89zm8.41-18.3A11.82 11.82 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.69 1.45h.01c6.55 0 11.89-5.34 11.89-11.89a11.82 11.82 0 0 0-3.48-8.41z"/></svg>',
        'envelope' => '<svg ' . $ns . ' ' . $stroke . '><rect x="3" y="5.25" width="18" height="13.5" rx="2"/><path d="m4.2 7.1 7.8 6.1 7.8-6.1"/></svg>',
        'headset' => '<svg ' . $ns . ' ' . $stroke . '><path d="M4.5 13.25a7.5 7.5 0 0 1 15 0"/><path d="M4.5 13.25v3.1a2 2 0 0 0 2 2h1.15v-6.4H6.5a2 2 0 0 0-2 2.3z"/><path d="M19.5 13.25v3.1a2 2 0 0 1-2 2h-1.15v-6.4H17.5a2 2 0 0 1 2 2.3z"/><path d="M16.25 19.6a4.25 4.25 0 0 1-8.5 0"/></svg>',
        'clock' => '<svg ' . $ns . ' ' . $stroke . '><circle cx="12" cy="12" r="8.25"/><path d="M12 7.75V12l3.1 1.85"/></svg>',
        'question' => '<svg ' . $ns . ' ' . $stroke . '><circle cx="12" cy="12" r="8.25"/><path d="M9.7 9.35a2.4 2.4 0 1 1 3.35 2.2c-.7.38-.95.86-.95 1.55v.35"/><circle cx="12" cy="16.55" r="1" fill="currentColor" stroke="none"/></svg>',
        'shield' => '<svg ' . $ns . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21.4s7.25-3.5 7.25-8.9V5.7L12 3.1 4.75 5.7v6.8c0 5.4 7.25 8.9 7.25 8.9z"/><path d="m8.7 11.85 2.15 2.15 4.45-4.45"/></svg>',
        'award' => '<svg ' . $ns . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8.1" r="4.35"/><path d="M8.85 12.15 7.4 20.4 12 18.1l4.6 2.3-1.45-8.25"/></svg>',
        'lock' => '<svg ' . $ns . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5.25" y="10.75" width="13.5" height="9.5" rx="2"/><path d="M8.25 10.75V8.2a3.75 3.75 0 0 1 7.5 0v2.55"/></svg>',
        'truck' => '<svg ' . $ns . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.25 7.25h10.7v8.1H3.25z"/><path d="M13.95 10.2h3.55l2.25 2.55v2.6h-5.8"/><circle cx="7.1" cy="17.85" r="1.55"/><circle cx="17.15" cy="17.85" r="1.55"/></svg>',
        'store' => '<svg ' . $ns . ' ' . $stroke . '><path d="M4.2 10.25 5.7 4.6h12.6l1.5 5.65"/><path d="M3.6 10.25h16.8l-.85 9.15H4.45z"/><path d="M10 14.4h4v5H10z"/></svg>',
    ];

    return $icons[$name] ?? '';
}

/**
 * @param 'instagram'|'facebook'|'tiktok'|'whatsapp' $network
 */
function petshop_render_footer_social_link(string $url, string $label, string $network): void
{
    $svg = petshop_footer_icon_svg($network);
    if ($svg === '') {
        return;
    }
    ?>
    <li>
        <a
            class="petshop-institutional-footer__social-link"
            href="<?php echo esc_url($url); ?>"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="<?php echo esc_attr($label); ?>"
            data-icon="<?php echo esc_attr($network); ?>"
        >
            <span class="petshop-institutional-footer__social-icon" aria-hidden="true"><?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
            <span class="screen-reader-text"><?php echo esc_html($label); ?></span>
        </a>
    </li>
    <?php
}

function petshop_render_footer_contact_row(
    string $icon,
    string $title,
    string $text = '',
    string $url = '',
    bool $multiline = false,
    bool $external = false
): void {
    if ($title === '' && $text === '') {
        return;
    }

    $svg = petshop_footer_icon_svg($icon);
    $rel = $external ? 'noopener noreferrer' : '';
    $target = $external ? '_blank' : '';
    ?>
    <div class="petshop-institutional-footer__contact-row">
        <span class="petshop-institutional-footer__contact-icon" aria-hidden="true" data-icon="<?php echo esc_attr($icon); ?>">
            <?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
        </span>
        <div class="petshop-institutional-footer__contact-copy">
            <?php if ($url !== '') : ?>
                <a
                    href="<?php echo esc_url($url); ?>"
                    <?php echo $target !== '' ? 'target="' . esc_attr($target) . '"' : ''; ?>
                    <?php echo $rel !== '' ? 'rel="' . esc_attr($rel) . '"' : ''; ?>
                >
                    <?php if ($title !== '') : ?>
                        <span class="petshop-institutional-footer__contact-title"><?php echo esc_html($title); ?></span>
                    <?php endif; ?>
                    <?php if ($text !== '') : ?>
                        <span class="petshop-institutional-footer__contact-text"><?php echo esc_html($text); ?></span>
                    <?php endif; ?>
                </a>
            <?php else : ?>
                <?php if ($title !== '') : ?>
                    <span class="petshop-institutional-footer__contact-title"><?php echo esc_html($title); ?></span>
                <?php endif; ?>
                <?php if ($text !== '') : ?>
                    <span class="petshop-institutional-footer__contact-text<?php echo $multiline ? ' petshop-institutional-footer__contact-text--multiline' : ''; ?>">
                        <?php echo esc_html($text); ?>
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function petshop_render_institutional_footer(): void
{
    $description = petshop_footer_mod('petshop_footer_description');
    $whatsapp = petshop_footer_mod('petshop_footer_whatsapp');
    $whatsappLabel = petshop_footer_mod('petshop_footer_whatsapp_label');
    $email = petshop_footer_mod('petshop_footer_email');
    $hours = petshop_footer_mod('petshop_footer_hours');
    $supportText = petshop_footer_mod('petshop_footer_support_text');
    $supportPageId = (int) get_theme_mod('petshop_support_page', 0);
    $supportPage = $supportPageId > 0 ? get_post($supportPageId) : null;
    $supportUrl = $supportPage instanceof \WP_Post && $supportPage->post_status === 'publish'
        ? (string) get_permalink($supportPage)
        : '';
    $faqUrl = petshop_footer_mod('petshop_footer_faq_url');
    $faqText = petshop_footer_mod('petshop_footer_faq_text');
    $cnpj = petshop_footer_mod('petshop_footer_cnpj');
    $address = petshop_footer_mod('petshop_footer_address');
    $copyright = petshop_footer_mod('petshop_footer_copyright');
    $legalName = petshop_footer_mod('petshop_footer_legal_name');
    $instagram = petshop_footer_mod('petshop_footer_instagram');
    $facebook = petshop_footer_mod('petshop_footer_facebook');
    $tiktok = petshop_footer_mod('petshop_footer_tiktok');
    $socialWhatsapp = petshop_footer_mod('petshop_footer_social_whatsapp');
    $paymentText = petshop_footer_mod('petshop_footer_payment_text');

    $trustIcons = [1 => 'shield', 2 => 'award', 3 => 'lock', 4 => 'truck'];
    $trustSlots = [];
    for ($i = 1; $i <= 4; $i++) {
        $title = petshop_footer_mod("petshop_footer_trust_{$i}_title");
        $text = petshop_footer_mod("petshop_footer_trust_{$i}_text");
        if ($title === '' && $text === '') {
            continue;
        }
        $trustSlots[] = [
            'title' => $title,
            'text' => $text,
            'icon' => $trustIcons[$i],
        ];
    }

    $hasSocial = $instagram !== '' || $facebook !== '' || $tiktok !== '' || $socialWhatsapp !== '';
    $hasEmail = $email !== '' && is_email($email);
    $hasFaq = $faqUrl !== '';
    $hasContact = $whatsapp !== '' || $hasEmail || $hours !== '' || $supportUrl !== '' || $hasFaq;
    $hasLegal = $copyright !== '' || $legalName !== '' || $cnpj !== '' || $address !== '' || $paymentText !== '';
    $hasTrust = $trustSlots !== [];
    $hasFooterMenu = has_nav_menu('petshop-footer');
    $hasPrimaryMenu = has_nav_menu('petshop-primary');
    $hasBrand = $description !== '' || has_custom_logo() || $hasSocial;

    $institutionalItems = '';
    if ($hasFooterMenu) {
        $menuItems = wp_nav_menu([
            'theme_location' => 'petshop-footer',
            'container' => false,
            'items_wrap' => '%3$s',
            'echo' => false,
            'depth' => 1,
            'fallback_cb' => false,
        ]);
        if (is_string($menuItems) && $menuItems !== '') {
            $institutionalItems .= $menuItems;
        }
    }
    $hasInstitutional = $institutionalItems !== '';

    if (
        !$hasInstitutional
        && !$hasPrimaryMenu
        && !$hasBrand
        && !$hasContact
        && !$hasLegal
        && !$hasTrust
    ) {
        return;
    }
    ?>
    <footer class="petshop-institutional-footer" aria-label="<?php esc_attr_e('Rodapé da loja', 'petshop-theme'); ?>">
        <div class="ct-container petshop-institutional-footer__grid">
            <?php if ($hasBrand) : ?>
                <div class="petshop-institutional-footer__brand">
                    <?php if (has_custom_logo()) : ?>
                        <div class="petshop-institutional-footer__logo"><?php the_custom_logo(); ?></div>
                    <?php endif; ?>
                    <?php if ($description !== '') : ?>
                        <p><?php echo esc_html($description); ?></p>
                    <?php endif; ?>
                    <?php if ($hasSocial) : ?>
                        <div class="petshop-institutional-footer__social">
                            <h2><?php esc_html_e('Siga-nos', 'petshop-theme'); ?></h2>
                            <ul class="petshop-institutional-footer__social-list">
                                <?php if ($instagram !== '') : ?>
                                    <?php petshop_render_footer_social_link($instagram, __('Instagram', 'petshop-theme'), 'instagram'); ?>
                                <?php endif; ?>
                                <?php if ($facebook !== '') : ?>
                                    <?php petshop_render_footer_social_link($facebook, __('Facebook', 'petshop-theme'), 'facebook'); ?>
                                <?php endif; ?>
                                <?php if ($tiktok !== '') : ?>
                                    <?php petshop_render_footer_social_link($tiktok, __('TikTok', 'petshop-theme'), 'tiktok'); ?>
                                <?php endif; ?>
                                <?php if ($socialWhatsapp !== '') : ?>
                                    <?php petshop_render_footer_social_link($socialWhatsapp, __('WhatsApp', 'petshop-theme'), 'whatsapp'); ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($hasContact) : ?>
                <div class="petshop-institutional-footer__contact">
                    <h2><?php esc_html_e('Atendimento', 'petshop-theme'); ?></h2>
                    <?php
                    if ($whatsapp !== '') {
                        petshop_render_footer_contact_row(
                            'whatsapp',
                            __('WhatsApp', 'petshop-theme'),
                            $whatsappLabel,
                            $whatsapp,
                            false,
                            true
                        );
                    }
                    if ($hasEmail) {
                        petshop_render_footer_contact_row(
                            'envelope',
                            __('E-mail', 'petshop-theme'),
                            $email,
                            'mailto:' . $email
                        );
                    }
                    if ($supportUrl !== '') {
                        petshop_render_footer_contact_row(
                            'headset',
                            __('Atendimento ao cliente', 'petshop-theme'),
                            $supportText,
                            $supportUrl
                        );
                    }
                    if ($hours !== '') {
                        petshop_render_footer_contact_row(
                            'clock',
                            __('Horário de atendimento', 'petshop-theme'),
                            $hours,
                            '',
                            true
                        );
                    }
                    if ($hasFaq) {
                        petshop_render_footer_contact_row(
                            'question',
                            __('Perguntas frequentes', 'petshop-theme'),
                            $faqText,
                            $faqUrl
                        );
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($hasPrimaryMenu) : ?>
                <nav class="petshop-institutional-footer__categories" aria-label="<?php esc_attr_e('Categorias', 'petshop-theme'); ?>">
                    <h2><?php esc_html_e('Categorias', 'petshop-theme'); ?></h2>
                    <?php
                    wp_nav_menu([
                        'theme_location' => 'petshop-primary',
                        'container' => false,
                        'menu_class' => 'petshop-institutional-footer__menu',
                        'depth' => 1,
                        'fallback_cb' => false,
                    ]);
                    ?>
                </nav>
            <?php endif; ?>

            <?php if ($hasInstitutional) : ?>
                <nav class="petshop-institutional-footer__policies" aria-label="<?php esc_attr_e('Informações da loja', 'petshop-theme'); ?>">
                    <h2><?php esc_html_e('Institucional', 'petshop-theme'); ?></h2>
                    <ul class="petshop-institutional-footer__menu">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with wp_nav_menu.
                        echo $institutionalItems;
                        ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>

        <?php if ($hasTrust) : ?>
            <div class="petshop-institutional-footer__trust" aria-label="<?php esc_attr_e('Selos de confiança', 'petshop-theme'); ?>">
                <div class="ct-container petshop-institutional-footer__trust-grid">
                    <?php foreach ($trustSlots as $slot) : ?>
                        <div class="petshop-institutional-footer__trust-item">
                            <span class="petshop-institutional-footer__trust-icon" aria-hidden="true" data-icon="<?php echo esc_attr($slot['icon']); ?>">
                                <?php echo petshop_footer_icon_svg($slot['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
                            </span>
                            <div class="petshop-institutional-footer__trust-copy">
                                <?php if ($slot['title'] !== '') : ?>
                                    <h3><?php echo esc_html($slot['title']); ?></h3>
                                <?php endif; ?>
                                <?php if ($slot['text'] !== '') : ?>
                                    <p><?php echo esc_html($slot['text']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasLegal) : ?>
            <div class="petshop-institutional-footer__legal ct-container">
                <span class="petshop-institutional-footer__legal-icon" aria-hidden="true">
                    <?php echo petshop_footer_icon_svg('store'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
                </span>
                <div class="petshop-institutional-footer__legal-copy">
                    <?php
                    $legalLine = array_values(array_filter([
                        $copyright,
                        $legalName,
                        $cnpj !== '' ? sprintf(/* translators: %s: CNPJ */ __('CNPJ %s', 'petshop-theme'), $cnpj) : '',
                    ], static fn (string $part): bool => $part !== ''));
                    ?>
                    <?php if ($legalLine !== []) : ?>
                        <p><?php echo esc_html(implode(' – ', $legalLine)); ?></p>
                    <?php endif; ?>
                    <?php if ($address !== '') : ?>
                        <p><?php echo esc_html($address); ?></p>
                    <?php endif; ?>
                    <?php if ($paymentText !== '') : ?>
                        <p><?php echo esc_html($paymentText); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </footer>
    <?php
}

add_action('wp_footer', 'petshop_render_institutional_footer', 5);
