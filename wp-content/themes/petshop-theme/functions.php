<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

add_filter('blocksy:builder:header:enabled', '__return_false');
add_filter('blocksy:builder:footer:enabled', '__return_false');
add_filter('blocksy:footer:theme-author', '__return_false');
add_filter('blocksy:footer:copyright:value', static fn (): string => '');
add_filter(
    'blocksy:single:has-default-hero',
    static function (bool $hasDefaultHero): bool {
        if (function_exists('is_product') && is_product()) {
            return false;
        }

        if (function_exists('is_woocommerce') && is_woocommerce()) {
            return false;
        }

        if (!is_page()) {
            return $hasDefaultHero;
        }

        $page = get_queried_object();
        if (!$page instanceof \WP_Post) {
            return $hasDefaultHero;
        }

        return (bool) get_post_meta((int) $page->ID, '_petshop_managed_commercial_page_018', true)
            ? false
            : $hasDefaultHero;
    }
);
add_filter('blocksy:archive:has-default-hero', '__return_false');
add_filter('blocksy:woocommerce:archive:has-default-hero', '__return_false');
add_filter('blocksy:woo:archive:has-default-hero', '__return_false');

add_filter(
    'body_class',
    static function (array $classes): array {
        if (is_page('lista-de-desejos')) {
            $classes[] = 'page-lista-de-desejos';
        }

        if (is_page()) {
            $page = get_queried_object();
            if ($page instanceof \WP_Post && get_post_meta((int) $page->ID, '_petshop_managed_commercial_page_018', true)) {
                $classes[] = 'petshop-commercial-managed';
            }
        }

        return $classes;
    }
);

add_action(
    'after_setup_theme',
    static function (): void {
        add_theme_support('woocommerce');
        add_theme_support('title-tag');
        add_theme_support('custom-logo', [
            'height' => 140,
            'width' => 458,
            'flex-height' => true,
            'flex-width' => true,
        ]);

        register_nav_menus([
            'petshop-primary' => __('Navegação principal', 'petshop-theme'),
            'petshop-footer' => __('Navegação do rodapé', 'petshop-theme'),
        ]);

        add_theme_support('editor-styles');
        add_editor_style([
            'style.css',
            'assets/css/editor-storefront.css',
        ]);
    }
);

add_filter(
    'get_product_search_form',
    static function (string $form): string {
        if (preg_match('/class="[^"]*search-field[^"]*"[^>]*\baria-label=/', $form)) {
            return $form;
        }

        if (preg_match('/<label\b[^>]*\bfor="[^"]*"/', $form)) {
            return $form;
        }

        $label = esc_attr__('Buscar produtos', 'petshop-theme');

        return (string) preg_replace(
            '/(<input[^>]*class="[^"]*search-field[^"]*"[^>]*)(>)/',
            '$1 aria-label="' . $label . '"$2',
            $form,
            1
        );
    }
);

/**
 * @param non-empty-string $url
 * @param non-empty-string $label
 * @param 'support'|'wishlist'|'account' $iconKey
 */
function petshop_render_header_action(string $url, string $label, string $iconKey): void
{
    $icons = [
        'support' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
        'wishlist' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
        'account' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    ];

    if (!isset($icons[$iconKey])) {
        return;
    }

    ?>
    <a href="<?php echo esc_url($url); ?>" class="petshop-header-action petshop-header-action--<?php echo esc_attr($iconKey); ?>" aria-label="<?php echo esc_attr($label); ?>">
        <span class="petshop-header-action__icon" aria-hidden="true"><?php echo $icons[$iconKey]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
        <span class="petshop-header-action__label"><?php echo esc_html($label); ?></span>
    </a>
    <?php
}

add_action(
    'wp_body_open',
    static function (): void {
        $petshopDefault = static fn (string $id): mixed => class_exists(\Petshop\Core\Settings\DefaultSettings::class)
            ? \Petshop\Core\Settings\DefaultSettings::get($id)
            : null;
        $benefitText = (string) get_theme_mod(
            'petshop_benefit_text',
            $petshopDefault('petshop_benefit_text')
        );
        $benefitUrl = (string) get_theme_mod('petshop_benefit_url', '');
        $supportPageId = (int) get_theme_mod('petshop_support_page', 0);
        $supportPage = $supportPageId > 0 ? get_post($supportPageId) : null;
        $supportUrl = $supportPage instanceof \WP_Post && $supportPage->post_status === 'publish'
            ? (string) get_permalink($supportPage)
            : '';
        $supportLabel = trim((string) get_theme_mod('petshop_support_label', $petshopDefault('petshop_support_label')));
        $checkoutAssuranceText = trim((string) get_theme_mod(
            'petshop_checkout_assurance_text',
            $petshopDefault('petshop_checkout_assurance_text')
        ));
        $wishlistLabel = trim((string) get_theme_mod('petshop_wishlist_label', $petshopDefault('petshop_wishlist_label')));
        $wishlistUrl = class_exists(\Petshop\Core\StorefrontWishlist::class)
            ? \Petshop\Core\StorefrontWishlist::getPageUrl()
            : '';
        $accountLabel = trim((string) get_theme_mod('petshop_account_label', $petshopDefault('petshop_account_label')));
        $accountUrl = class_exists('WooCommerce')
            ? (string) wc_get_page_permalink('myaccount')
            : wp_login_url();
        ?>
        <?php if (trim($benefitText) !== '') : ?>
            <aside class="petshop-promo-bar" aria-label="<?php esc_attr_e('Mensagem promocional', 'petshop-theme'); ?>">
                <div class="ct-container">
                    <?php if ($benefitUrl !== '') : ?>
                        <a href="<?php echo esc_url($benefitUrl); ?>"><?php echo esc_html($benefitText); ?></a>
                    <?php else : ?>
                        <span><?php echo esc_html($benefitText); ?></span>
                    <?php endif; ?>
                </div>
            </aside>
        <?php endif; ?>
        <header class="petshop-commercial-header" itemscope itemtype="https://schema.org/WPHeader">
            <div class="petshop-commercial-header__main ct-container">
                <div class="petshop-commercial-header__brand" itemscope itemtype="https://schema.org/Organization">
                    <?php if (has_custom_logo()) : ?>
                        <?php the_custom_logo(); ?>
                    <?php else : ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>" rel="home"><?php echo esc_html(get_bloginfo('name')); ?></a>
                    <?php endif; ?>
                </div>
                <?php if ($checkoutAssuranceText !== '') : ?>
                    <div class="petshop-checkout-header__assurance" data-petshop-checkout-assurance aria-label="<?php esc_attr_e('Status do checkout', 'petshop-theme'); ?>">
                        <span class="petshop-checkout-header__assurance-mark" aria-hidden="true"></span>
                        <span><?php echo esc_html($checkoutAssuranceText); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (class_exists('WooCommerce')) : ?>
                    <div class="petshop-commercial-header__search">
                        <?php get_product_search_form(); ?>
                    </div>
                <?php endif; ?>
                <nav class="petshop-commercial-header__actions" aria-label="<?php esc_attr_e('Conta e atendimento', 'petshop-theme'); ?>">
                    <?php if ($supportUrl !== '' && $supportLabel !== '') : ?>
                        <?php petshop_render_header_action($supportUrl, $supportLabel, 'support'); ?>
                    <?php endif; ?>
                    <?php if ($wishlistUrl !== '' && $wishlistLabel !== '') : ?>
                        <?php petshop_render_header_action($wishlistUrl, $wishlistLabel, 'wishlist'); ?>
                    <?php endif; ?>
                    <?php if ($accountLabel !== '') : ?>
                        <?php petshop_render_header_action($accountUrl, $accountLabel, 'account'); ?>
                    <?php endif; ?>
                    <?php if (class_exists('WooCommerce')) : ?>
                        <?php echo do_blocks('<!-- wp:woocommerce/mini-cart /-->'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </nav>
            </div>
            <?php if (has_nav_menu('petshop-primary')) : ?>
                <div class="petshop-commercial-header__navigation">
                    <div class="ct-container">
                        <button class="petshop-commercial-header__menu-toggle" type="button" aria-expanded="false" aria-controls="petshop-commercial-menu-panel">
                            <span class="petshop-commercial-header__menu-icon" aria-hidden="true"></span>
                            <span><?php esc_html_e('Categorias', 'petshop-theme'); ?></span>
                        </button>
                        <?php
                        wp_nav_menu([
                            'theme_location' => 'petshop-primary',
                            'container' => 'nav',
                            'container_id' => 'petshop-commercial-menu-panel',
                            'container_class' => 'petshop-commercial-header__menu-panel',
                            'container_aria_label' => __('Categorias e coleções', 'petshop-theme'),
                            'menu_class' => 'petshop-commercial-menu',
                            'depth' => 2,
                            'fallback_cb' => false,
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </header>
        <?php
    },
    5
);

add_action(
    'wp_footer',
    static function (): void {
        ?>
        <script>
            (() => {
                const header = document.querySelector('.petshop-commercial-header');
                const toggle = header?.querySelector('.petshop-commercial-header__menu-toggle');
                const panel = header?.querySelector('#petshop-commercial-menu-panel');

                if (!header || !toggle || !panel) {
                    return;
                }

                const close = () => {
                    toggle.setAttribute('aria-expanded', 'false');
                    header.classList.remove('is-menu-open');
                };

                toggle.addEventListener('click', () => {
                    const expanded = toggle.getAttribute('aria-expanded') === 'true';
                    toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    header.classList.toggle('is-menu-open', !expanded);
                });

                panel.addEventListener('click', (event) => {
                    if (event.target instanceof HTMLAnchorElement) {
                        close();
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        close();
                    }
                });

                window.matchMedia('(min-width: 768px)').addEventListener('change', close);
            })();
        </script>
        <?php
    },
    20
);

add_action(
    'wp_footer',
    static function (): void {
        $description = trim((string) get_theme_mod('petshop_footer_description', ''));
        $whatsapp = trim((string) get_theme_mod('petshop_footer_whatsapp', ''));
        $hours = trim((string) get_theme_mod('petshop_footer_hours', ''));
        $cnpj = trim((string) get_theme_mod('petshop_footer_cnpj', ''));
        $address = trim((string) get_theme_mod('petshop_footer_address', ''));
        $instagram = trim((string) get_theme_mod('petshop_footer_instagram', ''));
        $facebook = trim((string) get_theme_mod('petshop_footer_facebook', ''));
        $paymentText = trim((string) get_theme_mod('petshop_footer_payment_text', ''));
        $accountUrl = class_exists('WooCommerce') ? (string) wc_get_page_permalink('myaccount') : '';
        $ordersUrl = $accountUrl !== '' ? wc_get_endpoint_url('orders', '', $accountUrl) : '';
        $hasSocial = $instagram !== '' || $facebook !== '';
        $hasContact = $whatsapp !== '' || $hours !== '';
        $hasLegal = $cnpj !== '' || $address !== '';
        $hasFooterMenu = has_nav_menu('petshop-footer');
        $hasPrimaryMenu = has_nav_menu('petshop-primary');

        if (
            !$hasFooterMenu
            && !$hasPrimaryMenu
            && $description === ''
            && !$hasContact
            && !$hasSocial
            && $paymentText === ''
            && !$hasLegal
        ) {
            return;
        }
        ?>
        <footer class="petshop-institutional-footer" aria-label="<?php esc_attr_e('Rodapé da loja', 'petshop-theme'); ?>">
            <div class="ct-container petshop-institutional-footer__grid">
                <?php if ($description !== '' || has_custom_logo()) : ?>
                    <div class="petshop-institutional-footer__brand">
                        <?php if (has_custom_logo()) : ?>
                            <div class="petshop-institutional-footer__logo"><?php the_custom_logo(); ?></div>
                        <?php endif; ?>
                        <?php if ($description !== '') : ?>
                            <p><?php echo esc_html($description); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($hasContact) : ?>
                    <div class="petshop-institutional-footer__contact">
                        <h2><?php esc_html_e('Atendimento', 'petshop-theme'); ?></h2>
                        <?php if ($whatsapp !== '') : ?>
                            <p><a href="<?php echo esc_url($whatsapp); ?>"><?php esc_html_e('WhatsApp', 'petshop-theme'); ?></a></p>
                        <?php endif; ?>
                        <?php if ($hours !== '') : ?>
                            <p><?php echo esc_html($hours); ?></p>
                        <?php endif; ?>
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

                <?php if ($hasFooterMenu || ($accountUrl !== '' && $ordersUrl !== '')) : ?>
                    <nav class="petshop-institutional-footer__policies" aria-label="<?php esc_attr_e('Informações da loja', 'petshop-theme'); ?>">
                        <h2><?php esc_html_e('Institucional', 'petshop-theme'); ?></h2>
                        <ul class="petshop-institutional-footer__menu">
                            <?php if ($accountUrl !== '') : ?>
                                <li><a href="<?php echo esc_url($accountUrl); ?>"><?php esc_html_e('Minha conta', 'petshop-theme'); ?></a></li>
                            <?php endif; ?>
                            <?php if ($ordersUrl !== '') : ?>
                                <li><a href="<?php echo esc_url($ordersUrl); ?>"><?php esc_html_e('Meus pedidos', 'petshop-theme'); ?></a></li>
                            <?php endif; ?>
                        </ul>
                        <?php if ($hasFooterMenu) : ?>
                            <?php
                            wp_nav_menu([
                                'theme_location' => 'petshop-footer',
                                'container' => false,
                                'menu_class' => 'petshop-institutional-footer__menu',
                                'depth' => 1,
                                'fallback_cb' => false,
                            ]);
                            ?>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

                <?php if ($hasSocial || $paymentText !== '') : ?>
                    <div class="petshop-institutional-footer__extras">
                        <?php if ($hasSocial) : ?>
                            <div class="petshop-institutional-footer__social">
                                <h2><?php esc_html_e('Redes sociais', 'petshop-theme'); ?></h2>
                                <ul class="petshop-institutional-footer__menu">
                                    <?php if ($instagram !== '') : ?>
                                        <li><a href="<?php echo esc_url($instagram); ?>">Instagram</a></li>
                                    <?php endif; ?>
                                    <?php if ($facebook !== '') : ?>
                                        <li><a href="<?php echo esc_url($facebook); ?>">Facebook</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if ($paymentText !== '') : ?>
                            <div class="petshop-institutional-footer__payment">
                                <h2><?php esc_html_e('Pagamento', 'petshop-theme'); ?></h2>
                                <p><?php echo esc_html($paymentText); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($hasLegal) : ?>
                <div class="petshop-institutional-footer__legal ct-container">
                    <?php if ($cnpj !== '') : ?>
                        <p><?php echo esc_html(sprintf(/* translators: %s: CNPJ */ __('CNPJ: %s', 'petshop-theme'), $cnpj)); ?></p>
                    <?php endif; ?>
                    <?php if ($address !== '') : ?>
                        <p><?php echo esc_html($address); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </footer>
        <?php
    },
    5
);

add_action(
    'wp_enqueue_scripts',
    static function (): void {
        wp_enqueue_style(
            'petshop-theme-fonts',
            'https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'petshop-theme',
            get_stylesheet_uri(),
            ['petshop-theme-fonts'],
            wp_get_theme()->get('Version')
        );
    }
);

add_action(
    'enqueue_block_editor_assets',
    static function (): void {
        wp_enqueue_style(
            'petshop-theme-fonts',
            'https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap',
            [],
            null
        );
    }
);
