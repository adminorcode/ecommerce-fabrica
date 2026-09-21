<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce.');
}

$failures = [];
$stylesheetDir = get_stylesheet_directory();
$styleCss = (string) file_get_contents($stylesheetDir . '/style.css');
$menuJs = (string) file_get_contents($stylesheetDir . '/assets/js/commercial-menu.js');
$functionsPhp = (string) file_get_contents($stylesheetDir . '/functions.php');

$uncacheMenu = static function (int $menuId): void {
    wp_cache_delete($menuId, 'nav_menu');
    clean_term_cache($menuId, 'nav_menu');
};

$record = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$record(is_readable($stylesheetDir . '/inc/commercial-menu.php'), 'Arquivo inc/commercial-menu.php ausente.');
$record(str_contains($functionsPhp, 'Petshop_Commercial_Menu::bootstrap()'), 'functions.php nao registra o markup do menu comercial.');
$record(str_contains($functionsPhp, 'petshop-commercial-menu'), 'functions.php nao enfileira commercial-menu.js.');
$record(str_contains($functionsPhp, "'depth' => 2"), 'Menu comercial do header nao permanece com depth 2.');
$record(str_contains($menuJs, 'petshop-commercial-menu__submenu-toggle'), 'JS do dropdown nao trata o chevron.');
$record(str_contains($menuJs, "event.key !== 'Escape'"), 'JS do dropdown nao trata Escape.');
$record(str_contains($styleCss, '.petshop-commercial-menu .sub-menu'), 'CSS do dropdown ausente.');
$record(str_contains($styleCss, 'box-shadow: var(--shadow-card)'), 'Dropdown nao usa --shadow-card.');
$record(str_contains($styleCss, 'background: var(--color-surface)'), 'Dropdown nao usa --color-surface.');
$record(
    str_contains($styleCss, '.petshop-commercial-header,')
        && str_contains($styleCss, 'overflow: visible;'),
    'Header desktop ainda pode cortar o dropdown.'
);

$locations = get_theme_mod('nav_menu_locations', []);
$menuId = (int) ($locations['petshop-primary'] ?? 0);
$record($menuId > 0, 'Location petshop-primary ausente.');

$parentsWithChildren = static function (array $items): array {
    $childCount = [];
    foreach ($items as $item) {
        $parentId = (int) $item->menu_item_parent;
        if ($parentId > 0) {
            $childCount[$parentId] = ($childCount[$parentId] ?? 0) + 1;
        }
    }

    $parents = [];
    foreach ($items as $item) {
        if ((int) $item->menu_item_parent === 0 && ($childCount[(int) $item->ID] ?? 0) > 0) {
            $parents[] = $item;
        }
    }

    return $parents;
};

if ($menuId > 0) {
    $items = wp_get_nav_menu_items($menuId);
    $items = is_array($items) ? $items : [];
    $parents = $parentsWithChildren($items);
    if (count($parents) < 2) {
        $topLevel = array_values(array_filter(
            $items,
            static fn ($item): bool => (int) $item->menu_item_parent === 0
        ));
        $parentIds = array_map(static fn ($item): int => (int) $item->ID, $parents);
        $candidates = array_values(array_filter(
            $topLevel,
            static fn ($item): bool => !in_array((int) $item->ID, $parentIds, true)
        ));
        $needed = 2 - count($parents);
        $record(
            count($candidates) >= $needed,
            'Menu comercial nao tem itens de primeiro nivel suficientes para dois pais com filhos.'
        );
        $labels = ['A', 'B'];
        $created = 0;
        foreach ($candidates as $parent) {
            if ($created >= $needed) {
                break;
            }
            $childId = wp_update_nav_menu_item($menuId, 0, [
                'menu-item-title' => 'Subcategoria 035 ' . $labels[$created],
                'menu-item-url' => (string) $parent->url,
                'menu-item-type' => 'custom',
                'menu-item-status' => 'publish',
                'menu-item-parent-id' => (int) $parent->ID,
            ]);
            if (is_wp_error($childId) || (int) $childId <= 0) {
                $record(false, 'Nao foi possivel criar filha de teste do dropdown.');
                break;
            }
            update_post_meta((int) $childId, '_petshop_035_fixture', '1');
            $created++;
        }
        $uncacheMenu($menuId);
        $items = wp_get_nav_menu_items($menuId);
        $items = is_array($items) ? $items : [];
        $parents = $parentsWithChildren($items);
    }

    $record(count($parents) >= 2, 'Gate 035 exige pelo menos dois pais com filhos no menu comercial.');

    $childrenByParent = [];
    foreach ($items as $item) {
        $parentId = (int) $item->menu_item_parent;
        if ($parentId > 0) {
            $childrenByParent[$parentId][] = $item;
        }
    }

    ob_start();
    wp_nav_menu([
        'theme_location' => 'petshop-primary',
        'container' => 'nav',
        'menu_class' => 'petshop-commercial-menu',
        'depth' => 2,
        'fallback_cb' => false,
    ]);
    $html = (string) ob_get_clean();

    $record(str_contains($html, 'petshop-commercial-menu'), 'Markup do menu comercial ausente.');

    foreach ($parents as $parent) {
        $parentId = (int) $parent->ID;
        $submenuId = 'petshop-submenu-' . $parentId;
        $record(
            str_contains($html, 'aria-haspopup="true"'),
            'Pais com filhos devem expor aria-haspopup.'
        );
        $record(
            str_contains($html, 'aria-controls="' . $submenuId . '"'),
            "Pai {$parentId} sem aria-controls do submenu."
        );
        $record(
            str_contains($html, 'id="' . $submenuId . '"'),
            "Submenu {$submenuId} sem id."
        );
        $record(
            str_contains($html, 'petshop-commercial-menu__submenu-toggle'),
            'Chevron ausente em pai com filhos.'
        );
        foreach ($childrenByParent[$parentId] ?? [] as $child) {
            $record(
                str_contains($html, '>' . esc_html((string) $child->title) . '<')
                    || str_contains($html, (string) $child->title),
                'Filha "' . $child->title . '" ausente no markup do dropdown.'
            );
        }
    }

    $leafParents = array_filter(
        $items,
        static fn ($item): bool => (int) $item->menu_item_parent === 0 && !isset($childrenByParent[(int) $item->ID])
    );
    $record($leafParents !== [], 'Menu comercial precisa de pelo menos um item de primeiro nivel sem filhos.');

    $dom = new DOMDocument();
    $internal = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($internal);
    $xpath = new DOMXPath($dom);
    $leafNodes = $xpath->query('//ul[contains(@class,"petshop-commercial-menu")]/li[not(contains(@class,"menu-item-has-children"))]');
    if ($leafNodes instanceof DOMNodeList) {
        foreach ($leafNodes as $leaf) {
            $emptySub = $xpath->query('./*[contains(@class,"sub-menu")]', $leaf);
            $toggle = $xpath->query('./*[contains(@class,"petshop-commercial-menu__submenu-toggle")]', $leaf);
            $record(
                !($emptySub instanceof DOMNodeList) || $emptySub->length === 0,
                'Item sem filhos renderizou caixa de submenu.'
            );
            $record(
                !($toggle instanceof DOMNodeList) || $toggle->length === 0,
                'Item sem filhos recebeu chevron.'
            );
        }
    }

    $sampleChild = $childrenByParent[(int) $parents[0]->ID][0] ?? null;
    if ($sampleChild instanceof WP_Post) {
        $childUrl = (string) $sampleChild->url;
        $parsed = wp_parse_url($childUrl);
        $path = (string) ($parsed['path'] ?? '/');
        if (!empty($parsed['query'])) {
            $path .= '?' . $parsed['query'];
        }
        $host = (string) (wp_parse_url(home_url(), PHP_URL_HOST) ?: 'localhost');
        $port = (string) (wp_parse_url(home_url(), PHP_URL_PORT) ?: '');
        $hostHeader = $port !== '' ? $host . ':' . $port : $host;
        $response = wp_remote_get('http://wordpress' . $path, [
            'timeout' => 30,
            'redirection' => 0,
            'sslverify' => false,
            'headers' => ['Host' => $hostHeader],
        ]);
        $status = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
        $record(in_array($status, [200, 301, 302], true), 'Clique/URL da filha nao retornou HTTP 200 (HTTP ' . $status . ').');
    }

    ob_start();
    wp_nav_menu([
        'theme_location' => 'petshop-primary',
        'container' => false,
        'menu_class' => 'petshop-institutional-footer__menu',
        'depth' => 1,
        'fallback_cb' => false,
    ]);
    $footerHtml = (string) ob_get_clean();
    $record(
        !str_contains($footerHtml, 'petshop-commercial-menu__submenu-toggle'),
        'Chevron do dropdown vazou para o rodape.'
    );
    $record(!str_contains($footerHtml, 'class="sub-menu"'), 'Rodape renderizou submenu apesar de depth 1.');

    if ($sampleChild instanceof WP_Post) {
        $originalTitle = (string) $sampleChild->title;
        $sentinel = 'Rotulo sentinela 035';
        $menuFields = [
            'menu-item-title' => $sentinel,
            'menu-item-url' => (string) $sampleChild->url,
            'menu-item-type' => (string) $sampleChild->type,
            'menu-item-object' => (string) $sampleChild->object,
            'menu-item-object-id' => (int) $sampleChild->object_id,
            'menu-item-parent-id' => (int) $sampleChild->menu_item_parent,
            'menu-item-status' => 'publish',
        ];
        wp_update_nav_menu_item($menuId, (int) $sampleChild->ID, $menuFields);
        Petshop\Core\StorefrontExperience::maybeEnsureStorefront();
        $uncacheMenu($menuId);

        ob_start();
        wp_nav_menu([
            'theme_location' => 'petshop-primary',
            'container' => 'nav',
            'menu_class' => 'petshop-commercial-menu',
            'depth' => 2,
            'fallback_cb' => false,
        ]);
        $afterHtml = (string) ob_get_clean();
        $record(str_contains($afterHtml, $sentinel), 'Filha renomeada nao apareceu no dropdown apos reload/reprovisionamento.');

        $menuFields['menu-item-title'] = $originalTitle;
        wp_update_nav_menu_item($menuId, (int) $sampleChild->ID, $menuFields);
    }
}

if ($failures !== []) {
    WP_CLI::error('Gate 035 falhou: ' . implode(' | ', $failures));
}

WP_CLI::success('Gate 035: markup, dois pais com filhos, item sem caixa vazia, HTTP 200 da filha, rodape depth 1 e persistencia aprovados.');
