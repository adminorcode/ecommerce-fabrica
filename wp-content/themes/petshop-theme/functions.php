<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

add_filter('blocksy:builder:header:enabled', '__return_false');
add_filter('blocksy:footer:theme-author', '__return_false');

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

add_action(
    'customize_register',
    static function (\WP_Customize_Manager $customizer): void {
        $customizer->add_section('petshop_store_content', [
            'title' => __('Conteúdo da loja', 'petshop-theme'),
            'priority' => 35,
            'description' => __('Textos globais exibidos em mais de uma rota da loja.', 'petshop-theme'),
        ]);

        $settings = [
            'petshop_benefit_text' => [
                'label' => __('Mensagem da barra superior', 'petshop-theme'),
                'default' => 'Acabamento cuidadoso para tutores e profissionais',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_benefit_url' => [
                'label' => __('Link da barra superior', 'petshop-theme'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
            ],
            'petshop_support_label' => [
                'label' => __('Rótulo do atendimento no cabeçalho', 'petshop-theme'),
                'default' => 'Atendimento',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_support_page' => [
                'label' => __('Página de atendimento do cabeçalho', 'petshop-theme'),
                'default' => 0,
                'type' => 'dropdown-pages',
                'sanitize' => 'absint',
            ],
            'petshop_account_label' => [
                'label' => __('Rótulo da conta no cabeçalho', 'petshop-theme'),
                'default' => 'Minha conta',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_wishlist_label' => [
                'label' => __('Rótulo da lista de desejos no cabeçalho', 'petshop-theme'),
                'default' => 'Lista de desejos',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_wishlist_page' => [
                'label' => __('Página da lista de desejos', 'petshop-theme'),
                'default' => 0,
                'type' => 'dropdown-pages',
                'sanitize' => 'absint',
            ],
            'petshop_featured_section_title' => [
                'label' => __('Título da seção de destaques (sem vendas reais)', 'petshop-theme'),
                'default' => 'Destaques da loja',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_product_assurance_title' => [
                'label' => __('Título do aviso de produto', 'petshop-theme'),
                'default' => 'Antes de adicionar ao carrinho',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_product_assurance_text' => [
                'label' => __('Texto do aviso de produto', 'petshop-theme'),
                'default' => 'Confira o conteúdo do pacote, material, aplicação e cuidados descritos nesta página.',
                'type' => 'textarea',
                'sanitize' => 'sanitize_textarea_field',
            ],
            'petshop_shop_description' => [
                'label' => __('Descrição resumida da loja para buscadores', 'petshop-theme'),
                'default' => 'Acessórios pet com acabamento cuidadoso para tutores e profissionais.',
                'type' => 'textarea',
                'sanitize' => 'sanitize_textarea_field',
            ],
            'petshop_footer_description' => [
                'label' => __('Descrição curta no rodapé', 'petshop-theme'),
                'default' => '',
                'type' => 'textarea',
                'sanitize' => 'sanitize_textarea_field',
            ],
            'petshop_footer_whatsapp' => [
                'label' => __('URL do WhatsApp', 'petshop-theme'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
            ],
            'petshop_footer_hours' => [
                'label' => __('Horário de atendimento', 'petshop-theme'),
                'default' => '',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_footer_cnpj' => [
                'label' => __('CNPJ', 'petshop-theme'),
                'default' => '',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_footer_address' => [
                'label' => __('Endereço', 'petshop-theme'),
                'default' => '',
                'type' => 'textarea',
                'sanitize' => 'sanitize_textarea_field',
            ],
            'petshop_footer_instagram' => [
                'label' => __('URL do Instagram', 'petshop-theme'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
            ],
            'petshop_footer_facebook' => [
                'label' => __('URL do Facebook', 'petshop-theme'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
            ],
            'petshop_footer_payment_text' => [
                'label' => __('Formas de pagamento (texto)', 'petshop-theme'),
                'default' => '',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
        ];

        foreach ($settings as $id => $config) {
            $customizer->add_setting($id, [
                'default' => $config['default'],
                'sanitize_callback' => $config['sanitize'],
                'transport' => 'refresh',
            ]);
            $customizer->add_control($id, [
                'section' => 'petshop_store_content',
                'label' => $config['label'],
                'type' => $config['type'],
            ]);
        }
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

add_action(
    'wp_body_open',
    static function (): void {
        ?>
        <a class="petshop-skip-link" href="#main"><?php esc_html_e('Ir para o conteúdo', 'petshop-theme'); ?></a>
        <?php
    },
    1
);

add_action(
    'wp_body_open',
    static function (): void {
        $benefitText = (string) get_theme_mod(
            'petshop_benefit_text',
            'Acabamento cuidadoso para tutores e profissionais'
        );
        $benefitUrl = (string) get_theme_mod('petshop_benefit_url', '');
        $supportPageId = (int) get_theme_mod('petshop_support_page', 0);
        $supportPage = $supportPageId > 0 ? get_post($supportPageId) : null;
        $supportUrl = $supportPage instanceof \WP_Post && $supportPage->post_status === 'publish'
            ? (string) get_permalink($supportPage)
            : '';
        $supportLabel = trim((string) get_theme_mod('petshop_support_label', 'Atendimento'));
        $wishlistLabel = trim((string) get_theme_mod('petshop_wishlist_label', 'Lista de desejos'));
        $wishlistUrl = class_exists(\Petshop\Core\StorefrontWishlist::class)
            ? \Petshop\Core\StorefrontWishlist::getPageUrl()
            : '';
        $accountLabel = trim((string) get_theme_mod('petshop_account_label', 'Minha conta'));
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
                <?php if (class_exists('WooCommerce')) : ?>
                    <div class="petshop-commercial-header__search">
                        <?php get_product_search_form(); ?>
                    </div>
                <?php endif; ?>
                <nav class="petshop-commercial-header__actions" aria-label="<?php esc_attr_e('Conta e atendimento', 'petshop-theme'); ?>">
                    <?php if ($supportUrl !== '' && $supportLabel !== '') : ?>
                        <a href="<?php echo esc_url($supportUrl); ?>"><?php echo esc_html($supportLabel); ?></a>
                    <?php endif; ?>
                    <?php if ($wishlistUrl !== '' && $wishlistLabel !== '') : ?>
                        <a href="<?php echo esc_url($wishlistUrl); ?>"><?php echo esc_html($wishlistLabel); ?></a>
                    <?php endif; ?>
                    <?php if ($accountLabel !== '') : ?>
                        <a href="<?php echo esc_url($accountUrl); ?>"><?php echo esc_html($accountLabel); ?></a>
                    <?php endif; ?>
                    <?php if (class_exists('WooCommerce')) : ?>
                        <?php echo do_blocks('<!-- wp:woocommerce/mini-cart /-->'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </nav>
            </div>
            <?php if (has_nav_menu('petshop-primary')) : ?>
                <div class="petshop-commercial-header__navigation">
                    <div class="ct-container">
                        <?php
                        wp_nav_menu([
                            'theme_location' => 'petshop-primary',
                            'container' => 'nav',
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
            'petshop-theme',
            get_stylesheet_uri(),
            [],
            wp_get_theme()->get('Version')
        );
    }
);
