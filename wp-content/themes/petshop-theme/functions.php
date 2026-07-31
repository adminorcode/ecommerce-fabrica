<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

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
        ?>
        <aside class="petshop-benefit-bar" aria-label="<?php esc_attr_e('Benefícios da loja', 'petshop-theme'); ?>">
            <div class="ct-container">
                <span><?php echo esc_html((string) get_theme_mod('petshop_benefit_text', 'Acabamento cuidadoso para tutores e profissionais')); ?></span>
                <?php if (has_nav_menu('petshop-utility')) : ?>
                    <?php
                    wp_nav_menu([
                        'theme_location' => 'petshop-utility',
                        'container' => 'nav',
                        'container_aria_label' => __('Acessos rápidos', 'petshop-theme'),
                        'menu_class' => 'petshop-utility-nav',
                        'depth' => 1,
                        'fallback_cb' => false,
                    ]);
                    ?>
                <?php endif; ?>
                <?php if (class_exists('WooCommerce')) : ?>
                    <div class="petshop-commerce-tools">
                        <?php get_product_search_form(); ?>
                        <?php echo do_blocks('<!-- wp:woocommerce/mini-cart /-->'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
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
