<?php

declare(strict_types=1);

namespace Petshop\Core\Provisioning;

defined('ABSPATH') || exit;

trait StorefrontProvisioning
{
    private static function stampNewManagedHome(int $homeId, string $shopUrl, int $heroId): void
    {
        $hero = self::heroContent($shopUrl, $heroId);
        update_post_meta($homeId, '_petshop_home_schema_version', 24);
        update_post_meta($homeId, '_petshop_managed_hero_hash', hash('sha256', $hero));
        if (
            (int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 24
            || (string) get_post_meta($homeId, '_petshop_managed_hero_hash', true) !== hash('sha256', $hero)
        ) {
            throw new \RuntimeException('Não foi possível assinar a nova Home gerenciada.');
        }
    }

    private static function addPolicyToManagedFooter(int $policiesId): void
    {
        $footer = wp_get_nav_menu_object('Navegação do rodapé');
        if ($footer instanceof \WP_Term) {
            self::addPageToMenu((int) $footer->term_id, $policiesId, 'Políticas da loja');
        }
    }

    private static function configureMenus(
        int $homeId,
        int $aboutId,
        int $supportId,
        int $shippingId,
        int $personalizeId,
        int $policiesId
    ): void {
        $primaryId = self::ensureMenu('Navegação principal');
        self::addPageToMenu($primaryId, $homeId, 'Início');
        $shopItemId = self::addPageToMenu(
            $primaryId,
            (int) get_option('woocommerce_shop_page_id'),
            'Comprar'
        );

        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => 0,
            'meta_key' => 'petshop_menu_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
        ]);
        if (!is_wp_error($categories)) {
            foreach ($categories as $term) {
                self::addTermToMenu($primaryId, $term, $shopItemId);
            }
        }
        self::addPageToMenu($primaryId, $personalizeId, 'Personalize');

        $utilityId = self::ensureMenu('Navegação de apoio');
        self::addPageToMenu($utilityId, $supportId, 'Atendimento');
        self::addWooPageToMenu($utilityId, 'woocommerce_myaccount_page_id', 'Minha conta');
        self::addWooPageToMenu($utilityId, 'woocommerce_cart_page_id', 'Carrinho');

        $footerId = self::ensureMenu('Navegação do rodapé');
        self::addPageToMenu($footerId, $aboutId, 'Sobre o Auteliê');
        self::addPageToMenu($footerId, $supportId, 'Atendimento');
        self::addPageToMenu($footerId, $shippingId, 'Envios e entregas');
        self::addPageToMenu($footerId, $policiesId, 'Políticas da loja');

        $locations = get_theme_mod('nav_menu_locations', []);
        $defaults = [
            'petshop-primary' => $primaryId,
            'petshop-utility' => $utilityId,
            'petshop-footer' => $footerId,
        ];
        if (get_stylesheet() === 'petshop-theme') {
            $defaults['menu_1'] = $primaryId;
            $defaults['menu_mobile'] = $primaryId;
        }
        foreach ($defaults as $location => $menuId) {
            if (empty($locations[$location])) {
                $locations[$location] = $menuId;
            }
        }
        set_theme_mod('nav_menu_locations', $locations);
    }

    private static function configureTheme(int $homeId): void
    {
        if (get_stylesheet() !== 'petshop-theme') {
            return;
        }

        set_theme_mod('colorPalette', [
            'color1' => ['color' => '#17676a'],
            'color2' => ['color' => '#9f3e0a'],
            'color3' => ['color' => '#625f60'],
            'color4' => ['color' => '#373435'],
            'color5' => ['color' => '#e6e7e9'],
            'color6' => ['color' => '#f7f8f8'],
            'color7' => ['color' => '#fbfbfb'],
            'color8' => ['color' => '#ffffff'],
        ]);
        set_theme_mod('buttonRadius', ['top' => '999px', 'bottom' => '999px', 'left' => '999px', 'right' => '999px', 'linked' => true]);
        set_theme_mod('buttonMinHeight', '44');
        set_theme_mod('shop_cards_alignment', 'left');
        set_theme_mod('has_product_categories', 'yes');
        if ((int) get_theme_mod('custom_logo', 0) <= 0) {
            set_theme_mod('custom_logo', self::ensureLogoAttachment());
        }
        if (in_array(get_option('blogname'), ['Petshop', 'Petshop Local', 'Autelie Moda Pet'], true)) {
            update_option('blogname', 'Auteliê Moda Pet');
        }
        if (in_array(get_option('blogdescription'), ['', 'Tudo para o seu pet'], true)) {
            update_option('blogdescription', 'Acessórios pet com personalidade');
        }
        update_post_meta($homeId, 'blocksy_post_meta_options', ['has_hero_section' => 'disabled']);
    }

    private static function ensureHeaderDefaults(): void
    {
        if (get_stylesheet() !== 'petshop-theme') {
            return;
        }
        if (get_theme_mod('petshop_benefit_text', null) === null) {
            set_theme_mod('petshop_benefit_text', \Petshop\Core\Settings\DefaultSettings::get('petshop_benefit_text'));
        }
        if (get_theme_mod('petshop_support_label', null) === null) {
            set_theme_mod('petshop_support_label', \Petshop\Core\Settings\DefaultSettings::get('petshop_support_label'));
        }
        if (get_theme_mod('petshop_checkout_assurance_text', null) === null) {
            set_theme_mod('petshop_checkout_assurance_text', \Petshop\Core\Settings\DefaultSettings::get('petshop_checkout_assurance_text'));
        }
        if (get_theme_mod('petshop_account_label', null) === null) {
            set_theme_mod('petshop_account_label', \Petshop\Core\Settings\DefaultSettings::get('petshop_account_label'));
        }
        if (get_theme_mod('petshop_wishlist_label', null) === null) {
            set_theme_mod('petshop_wishlist_label', \Petshop\Core\Settings\DefaultSettings::get('petshop_wishlist_label'));
        }
        if (get_theme_mod('petshop_support_page', null) === null) {
            $supportPage = get_page_by_path('atendimento');
            if ($supportPage instanceof \WP_Post) {
                set_theme_mod('petshop_support_page', (int) $supportPage->ID);
            }
        }
    }

    private static function ensureCommercialMenu(int $collectionsId, int $personalizeId): void
    {
        if (get_option(self::COMMERCIAL_MENU_OPTION) === '1') {
            return;
        }

        $menuId = self::ensureMenu('Navegação comercial');
        $targets = [
            ['type' => 'taxonomy', 'slug' => 'lacos', 'label' => 'Laços'],
            ['type' => 'taxonomy', 'slug' => 'bandanas', 'label' => 'Bandanas'],
            ['type' => 'taxonomy', 'slug' => 'adesivos', 'label' => 'Adesivos'],
            ['type' => 'taxonomy', 'slug' => 'gravatas', 'label' => 'Gravatas'],
            ['type' => 'taxonomy', 'slug' => 'conjuntos', 'label' => 'Kits econômicos'],
            ['type' => 'page', 'id' => $collectionsId, 'label' => 'Coleções'],
            ['type' => 'page', 'id' => $personalizeId, 'label' => 'Personalizados'],
        ];

        foreach ($targets as $position => $target) {
            if ($target['type'] === 'taxonomy') {
                $term = get_term_by('slug', $target['slug'], 'product_cat');
                if (!$term instanceof \WP_Term) {
                    throw new \Petshop\Core\Migration\MigrationException(
                        'PETSHOP_MENU_CATEGORIES_MISSING',
                        'Categoria ausente para o menu comercial: ' . $target['slug']
                    );
                }
                $object = 'product_cat';
                $objectId = (int) $term->term_id;
                $itemType = 'taxonomy';
            } else {
                $object = 'page';
                $objectId = (int) $target['id'];
                $itemType = 'post_type';
            }

            $itemId = self::findMenuObjectItem($menuId, $itemType, $object, $objectId);
            if ($itemId <= 0) {
                $itemId = wp_update_nav_menu_item($menuId, 0, [
                    'menu-item-title' => $target['label'],
                    'menu-item-object' => $object,
                    'menu-item-object-id' => $objectId,
                    'menu-item-type' => $itemType,
                    'menu-item-position' => $position + 1,
                    'menu-item-status' => 'publish',
                ]);
                if (is_wp_error($itemId)) {
                    throw new \RuntimeException($itemId->get_error_message());
                }
                update_post_meta((int) $itemId, '_petshop_managed_menu_item_005', '1');
            }
        }

        $items = wp_get_nav_menu_items($menuId);
        if (!is_array($items) || count($items) < count($targets)) {
            throw new \RuntimeException('O menu comercial não possui as sete entradas esperadas.');
        }

        $locations = get_theme_mod('nav_menu_locations', []);
        $locations['petshop-primary'] = $menuId;
        set_theme_mod('nav_menu_locations', $locations);
        update_option(self::COMMERCIAL_MENU_OPTION, '1', false);

        $confirmedLocations = get_theme_mod('nav_menu_locations', []);
        if (
            (int) ($confirmedLocations['petshop-primary'] ?? 0) !== $menuId
            || get_option(self::COMMERCIAL_MENU_OPTION) !== '1'
        ) {
            throw new \RuntimeException('Não foi possível confirmar o menu comercial.');
        }
    }

    private static function ensureLogoAttachment(): int
    {
        $existingId = (int) get_option('petshop_logo_attachment_id');
        if ($existingId > 0 && get_post($existingId) instanceof \WP_Post) {
            return $existingId;
        }

        $source = get_stylesheet_directory() . '/assets/images/autelie-logo.png';
        if (!is_readable($source)) {
            return 0;
        }

        $upload = wp_upload_bits('autelie-logo.png', null, (string) file_get_contents($source));
        if (!empty($upload['error'])) {
            return 0;
        }

        $attachmentId = wp_insert_attachment([
            'post_mime_type' => 'image/png',
            'post_title' => 'Auteliê Moda Pet',
            'post_status' => 'inherit',
        ], $upload['file']);

        if (is_wp_error($attachmentId)) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachmentId, wp_generate_attachment_metadata($attachmentId, $upload['file']));
        update_post_meta($attachmentId, '_wp_attachment_image_alt', 'Auteliê Moda Pet');
        update_option('petshop_logo_attachment_id', (int) $attachmentId, false);

        return (int) $attachmentId;
    }

    public static function supportBannerAttachment(): int
    {
        return self::ensureSupportBannerAttachment();
    }

    private static function ensureSupportBannerAttachment(): int
    {
        $existingId = (int) get_option('petshop_support_banner_attachment_id');
        if ($existingId > 0 && get_post($existingId) instanceof \WP_Post) {
            return $existingId;
        }

        $placeholder = self::placeholderAttachment('support-banner-whatsapp');
        if ($placeholder > 0) {
            return $placeholder;
        }

        $source = get_stylesheet_directory() . '/assets/images/banner-whatsapp-atendimento.png';
        if (!is_readable($source)) {
            return 0;
        }

        $upload = wp_upload_bits('banner-whatsapp-atendimento.png', null, (string) file_get_contents($source));
        if (!empty($upload['error'])) {
            return 0;
        }

        $attachmentId = wp_insert_attachment([
            'post_mime_type' => 'image/png',
            'post_title' => __('Banner de atendimento WhatsApp', 'petshop-core'),
            'post_status' => 'inherit',
        ], $upload['file']);

        if (is_wp_error($attachmentId)) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachmentId, wp_generate_attachment_metadata($attachmentId, $upload['file']));
        update_post_meta($attachmentId, '_petshop_placeholder_key', 'support-banner-whatsapp');
        update_post_meta(
            $attachmentId,
            '_wp_attachment_image_alt',
            __('Precisa de ajuda para escolher? Fale com nossa equipe no WhatsApp.', 'petshop-core')
        );
        update_option('petshop_support_banner_attachment_id', (int) $attachmentId, false);

        return (int) $attachmentId;
    }

    private static function ensureMenu(string $name): int
    {
        $menu = wp_get_nav_menu_object($name);
        if ($menu instanceof \WP_Term) {
            return (int) $menu->term_id;
        }

        $menuId = wp_create_nav_menu($name);
        if (is_wp_error($menuId)) {
            throw new \RuntimeException($menuId->get_error_message());
        }

        return (int) $menuId;
    }

    private static function addPageToMenu(int $menuId, int $pageId, string $label): int
    {
        if ($pageId <= 0) {
            return 0;
        }

        $existingItemId = self::findMenuObjectItem($menuId, 'post_type', 'page', $pageId);
        if ($existingItemId > 0) {
            return $existingItemId;
        }

        $itemId = wp_update_nav_menu_item($menuId, 0, [
            'menu-item-title' => $label,
            'menu-item-object' => 'page',
            'menu-item-object-id' => $pageId,
            'menu-item-type' => 'post_type',
            'menu-item-status' => 'publish',
        ]);

        return is_wp_error($itemId) ? 0 : (int) $itemId;
    }

    private static function addTermToMenu(int $menuId, \WP_Term $term, int $parentItemId): void
    {
        $existingItemId = self::findMenuObjectItem($menuId, 'taxonomy', 'product_cat', (int) $term->term_id);
        if ($existingItemId > 0) {
            update_post_meta($existingItemId, '_menu_item_menu_item_parent', $parentItemId);
            return;
        }

        wp_update_nav_menu_item($menuId, 0, [
            'menu-item-title' => $term->name,
            'menu-item-object' => 'product_cat',
            'menu-item-object-id' => $term->term_id,
            'menu-item-type' => 'taxonomy',
            'menu-item-parent-id' => $parentItemId,
            'menu-item-status' => 'publish',
        ]);
    }

    private static function addWooPageToMenu(int $menuId, string $option, string $label): void
    {
        self::addPageToMenu($menuId, (int) get_option($option), $label);
    }

    private static function findMenuObjectItem(int $menuId, string $type, string $object, int $objectId): int
    {
        $items = wp_get_nav_menu_items($menuId);
        if (!is_array($items)) {
            return 0;
        }

        foreach ($items as $item) {
            if ($item->type === $type && $item->object === $object && (int) $item->object_id === $objectId) {
                return (int) $item->ID;
            }
        }

        return 0;
    }

    private static function placeholderAttachment(string $key): int
    {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_petshop_placeholder_key',
            'meta_value' => $key,
        ]);

        return $attachments === [] ? 0 : (int) $attachments[0];
    }
}
