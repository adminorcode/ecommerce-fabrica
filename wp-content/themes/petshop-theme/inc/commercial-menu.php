<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

final class Petshop_Commercial_Menu
{
    private static int $submenuForItemId = 0;

    public static function bootstrap(): void
    {
        add_filter('nav_menu_link_attributes', [self::class, 'filterLinkAttributes'], 10, 4);
        add_filter('walker_nav_menu_start_el', [self::class, 'appendSubmenuToggle'], 10, 4);
        add_filter('nav_menu_submenu_attributes', [self::class, 'filterSubmenuAttributes'], 10, 3);
    }

    /**
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    public static function filterLinkAttributes(array $atts, mixed $item, mixed $args, int $depth): array
    {
        if (!self::isCommercialParentWithChildren($item, $args, $depth)) {
            return $atts;
        }

        $itemId = (int) ($item->ID ?? 0);
        $atts['aria-haspopup'] = 'true';
        $atts['aria-expanded'] = 'false';
        $atts['aria-controls'] = self::submenuId($itemId);

        return $atts;
    }

    public static function appendSubmenuToggle(string $itemOutput, mixed $item, int $depth, mixed $args): string
    {
        if (!self::isCommercialParentWithChildren($item, $args, $depth)) {
            return $itemOutput;
        }

        $itemId = (int) ($item->ID ?? 0);
        self::$submenuForItemId = $itemId;
        $title = trim(wp_strip_all_tags((string) ($item->title ?? '')));
        $openLabel = sprintf(
            /* translators: %s: menu item title */
            __('Abrir subcategorias de %s', 'petshop-theme'),
            $title
        );
        $closeLabel = sprintf(
            /* translators: %s: menu item title */
            __('Fechar subcategorias de %s', 'petshop-theme'),
            $title
        );

        $button = sprintf(
            '<button type="button" class="petshop-commercial-menu__submenu-toggle" aria-expanded="false" aria-controls="%1$s" aria-label="%2$s" data-open-label="%2$s" data-close-label="%3$s"><span class="petshop-commercial-menu__chevron" aria-hidden="true"></span></button>',
            esc_attr(self::submenuId($itemId)),
            esc_attr($openLabel),
            esc_attr($closeLabel)
        );

        return $itemOutput . $button;
    }

    /**
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    public static function filterSubmenuAttributes(array $atts, mixed $args, int $depth): array
    {
        if (!self::isCommercialMenu($args) || $depth !== 0 || self::$submenuForItemId <= 0) {
            return $atts;
        }

        $atts['id'] = self::submenuId(self::$submenuForItemId);
        self::$submenuForItemId = 0;

        return $atts;
    }

    private static function submenuId(int $itemId): string
    {
        return 'petshop-submenu-' . $itemId;
    }

    private static function isCommercialMenu(mixed $args): bool
    {
        if (is_array($args)) {
            $args = (object) $args;
        }

        if (!is_object($args)) {
            return false;
        }

        return ($args->menu_class ?? '') === 'petshop-commercial-menu';
    }

    private static function isCommercialParentWithChildren(mixed $item, mixed $args, int $depth): bool
    {
        if ($depth !== 0 || !is_object($item) || !self::isCommercialMenu($args)) {
            return false;
        }

        $classes = array_map('strval', (array) ($item->classes ?? []));

        return in_array('menu-item-has-children', $classes, true);
    }
}
