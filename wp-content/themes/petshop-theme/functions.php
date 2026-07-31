<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

add_filter('blocksy:builder:header:enabled', '__return_false');

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
            'petshop-utility' => __('Navegação de apoio', 'petshop-theme'),
            'petshop-footer' => __('Navegação do rodapé', 'petshop-theme'),
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
        if (!has_nav_menu('petshop-footer')) {
            return;
        }
        ?>
        <div class="petshop-footer-links">
            <div class="ct-container">
                <?php
                wp_nav_menu([
                    'theme_location' => 'petshop-footer',
                    'container' => 'nav',
                    'container_aria_label' => __('Informações da loja', 'petshop-theme'),
                    'menu_class' => 'petshop-footer-nav',
                    'depth' => 1,
                    'fallback_cb' => false,
                ]);
                ?>
            </div>
        </div>
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
