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
        $customizer->add_section('petshop_store_content', [
            'title' => __('Conteúdo da loja', 'petshop-core'),
            'priority' => 35,
            'description' => __('Textos globais exibidos em mais de uma rota da loja.', 'petshop-core'),
        ]);

        foreach (DefaultSettings::definitions() as $id => $config) {
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
}
