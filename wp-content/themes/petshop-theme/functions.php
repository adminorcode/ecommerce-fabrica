<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

add_action(
    'after_setup_theme',
    static function (): void {
        add_theme_support('woocommerce');
    }
);
