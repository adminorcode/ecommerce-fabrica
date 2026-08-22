<?php

declare(strict_types=1);

namespace Petshop\Core\Admin;

use Petshop\Core\Settings\DefaultSettings;

defined('ABSPATH') || exit;

final class Customizer
{
    public static function bootstrap(): void
    {
        add_action('customize_register', [self::class, 'register']);
    }

    public static function register(\WP_Customize_Manager $customizer): void
    {
        $customizer->add_section('petshop_promo_bar', [
            'title' => __('Barra promocional', 'petshop-core'),
            'priority' => 34,
            'description' => __('Faixa verde no topo de todas as páginas da loja. Deixe o texto em branco para ocultar a barra.', 'petshop-core'),
        ]);

        $customizer->add_section('petshop_store_content', [
            'title' => __('Conteúdo da loja', 'petshop-core'),
            'priority' => 35,
            'description' => __('Textos globais exibidos em mais de uma rota da loja.', 'petshop-core'),
        ]);

        $customizer->add_section('petshop_footer', [
            'title' => __('Rodapé da loja', 'petshop-core'),
            'priority' => 36,
            'description' => __('Redes, atendimento, selos de confiança e dados legais do rodapé institucional.', 'petshop-core'),
        ]);

        foreach (DefaultSettings::definitions() as $id => $config) {
            $customizer->add_setting($id, [
                'default' => $config['default'],
                'sanitize_callback' => $config['sanitize'],
                'transport' => 'refresh',
            ]);

            $control = [
                'section' => $config['section'] ?? 'petshop_store_content',
                'label' => $config['label'],
                'type' => $config['type'],
            ];

            if (!empty($config['description']) && is_string($config['description'])) {
                $control['description'] = $config['description'];
            }

            $customizer->add_control($id, $control);
        }
    }
}
