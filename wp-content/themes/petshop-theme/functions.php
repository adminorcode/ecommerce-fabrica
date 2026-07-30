<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

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
