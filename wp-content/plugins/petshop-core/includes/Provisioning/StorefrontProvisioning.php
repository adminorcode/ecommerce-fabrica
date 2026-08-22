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
        self::ensureFooterDefaults();
    }

    private static function ensureFooterDefaults(): void
    {
        $keys = [
            'petshop_footer_description',
            'petshop_footer_copyright',
            'petshop_footer_whatsapp_label',
            'petshop_footer_support_text',
            'petshop_footer_hours',
            'petshop_footer_faq_text',
            'petshop_footer_trust_1_title',
            'petshop_footer_trust_1_text',
            'petshop_footer_trust_2_title',
            'petshop_footer_trust_2_text',
            'petshop_footer_trust_3_title',
            'petshop_footer_trust_3_text',
            'petshop_footer_trust_4_title',
            'petshop_footer_trust_4_text',
        ];

        foreach ($keys as $key) {
            if (get_theme_mod($key, null) !== null) {
                continue;
            }
            set_theme_mod($key, \Petshop\Core\Settings\DefaultSettings::get($key));
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

    private static function ensureP1CommercialPages(): void
    {
        $animalImageId = self::ensureCommercialPageAttachment(
            'commercial-animal-republik-placeholder',
            'commercial-animal-republik-placeholder.png',
            __('Placeholder Animal Republik', 'petshop-core'),
            __('Cachorro em mesa de banho e tosa com frascos neutros de cuidados pet.', 'petshop-core')
        );
        $premiumImageId = self::ensureCommercialPageAttachment(
            'commercial-premium-placeholder',
            'commercial-premium-placeholder.png',
            __('Placeholder Produtos Premium', 'petshop-core'),
            __('Acessórios pet com tecidos, embalagem e acabamento em composição de produto.', 'petshop-core')
        );

        $animalProductIds = self::commercialProductIds(
            ['penteados', 'gargantilhas', 'lacos'],
            ['Ponto de Luz', 'Penteado', 'Gargantilha', 'Elastico'],
            4
        );
        $premiumProductIds = self::commercialProductIds(
            ['lacos', 'conjuntos', 'gargantilhas'],
            ['Premium', 'Luxo', 'Pedraria', 'Strass'],
            20
        );
        self::ensureCommercialCategory('Produtos premium', 'premium', $premiumProductIds, 95);

        $animalId = self::ensureCommercialPage(
            'animal-republik',
            'Animal Republik',
            self::animalRepublikPageContent($animalImageId, $animalProductIds),
            $animalProductIds !== []
        );
        $premiumId = self::ensureCommercialPage(
            'premium',
            'Produtos premium',
            self::premiumPageContent($premiumImageId, $premiumProductIds),
            $premiumProductIds !== []
        );

        $locations = get_theme_mod('nav_menu_locations', []);
        $menuId = (int) ($locations['petshop-primary'] ?? 0);
        if ($menuId <= 0) {
            $menu = wp_get_nav_menu_object('Navegação comercial');
            $menuId = $menu instanceof \WP_Term ? (int) $menu->term_id : 0;
        }

        if ($menuId <= 0) {
            return;
        }

        self::syncPublishedCommercialMenuItem($menuId, $animalId, 'Animal Republik');
        self::syncPublishedCommercialMenuItem($menuId, $premiumId, 'Premium');
    }

    private static function ensureCommercialPage(string $slug, string $title, string $content, bool $publish): int
    {
        $existing = get_page_by_path($slug);
        $status = $publish ? 'publish' : 'draft';
        if ($existing instanceof \WP_Post) {
            self::configureCommercialPageMeta((int) $existing->ID);
            if (
                (bool) get_post_meta((int) $existing->ID, '_petshop_managed_commercial_page_018', true)
                && !str_contains((string) $existing->post_content, 'wp-block-cover__image-background')
                && str_contains($content, 'wp-block-cover__image-background')
            ) {
                $updated = wp_update_post([
                    'ID' => (int) $existing->ID,
                    'post_content' => $content,
                ], true);
                if (is_wp_error($updated)) {
                    throw new \RuntimeException($updated->get_error_message());
                }
            }
            if (
                (bool) get_post_meta((int) $existing->ID, '_petshop_managed_commercial_page_018', true)
                && $existing->post_status !== $status
            ) {
                $updated = wp_update_post([
                    'ID' => (int) $existing->ID,
                    'post_status' => $status,
                ], true);
                if (is_wp_error($updated)) {
                    throw new \RuntimeException($updated->get_error_message());
                }
            }

            return (int) $existing->ID;
        }

        $pageId = wp_insert_post([
            'post_type' => 'page',
            'post_status' => $status,
            'post_name' => $slug,
            'post_title' => $title,
            'post_content' => $content,
            'meta_input' => [
                '_petshop_managed_page' => 1,
                '_petshop_managed_commercial_page_018' => 1,
            ],
        ], true);

        if (is_wp_error($pageId)) {
            throw new \RuntimeException($pageId->get_error_message());
        }

        self::configureCommercialPageMeta((int) $pageId);

        return (int) $pageId;
    }

    private static function configureCommercialPageMeta(int $pageId): void
    {
        if ($pageId <= 0) {
            return;
        }

        update_post_meta($pageId, 'blocksy_post_meta_options', [
            'has_hero_section' => 'disabled',
            'page_title' => 'disabled',
        ]);
    }

    /**
     * @param list<int> $productIds
     */
    private static function ensureCommercialCategory(string $name, string $slug, array $productIds, int $order): int
    {
        $term = get_term_by('slug', sanitize_title($slug), 'product_cat');
        if (!$term instanceof \WP_Term) {
            $created = wp_insert_term($name, 'product_cat', ['slug' => sanitize_title($slug)]);
            if (is_wp_error($created)) {
                throw new \RuntimeException($created->get_error_message());
            }
            $termId = (int) $created['term_id'];
        } else {
            $termId = (int) $term->term_id;
        }

        update_term_meta($termId, 'petshop_visible_in_menu', 0);
        update_term_meta($termId, 'petshop_seasonal', 0);
        update_term_meta($termId, 'petshop_menu_order', $order);

        foreach ($productIds as $productId) {
            $product = wc_get_product($productId);
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $categoryIds = array_values(array_unique(array_merge($product->get_category_ids(), [$termId])));
            if ($categoryIds !== $product->get_category_ids()) {
                $product->set_category_ids($categoryIds);
                $product->save();
            }
        }

        return $termId;
    }

    private static function syncPublishedCommercialMenuItem(int $menuId, int $pageId, string $label): void
    {
        if ($pageId <= 0) {
            return;
        }

        $page = get_post($pageId);
        $itemId = self::findMenuObjectItem($menuId, 'post_type', 'page', $pageId);
        if (!$page instanceof \WP_Post || $page->post_status !== 'publish') {
            if ($itemId > 0) {
                wp_delete_post($itemId, true);
            }
            return;
        }

        self::addPageToMenu($menuId, $pageId, $label);
    }

    private static function ensureCommercialPageAttachment(string $key, string $filename, string $title, string $alt): int
    {
        $option = 'petshop_' . str_replace('-', '_', $key) . '_attachment_id';
        $existingId = (int) get_option($option);
        if ($existingId > 0 && get_post($existingId) instanceof \WP_Post) {
            return $existingId;
        }

        $placeholder = self::placeholderAttachment($key);
        if ($placeholder > 0) {
            update_option($option, $placeholder, false);

            return $placeholder;
        }

        $source = get_stylesheet_directory() . '/assets/images/' . $filename;
        if (!is_readable($source)) {
            return 0;
        }

        $upload = wp_upload_bits($filename, null, (string) file_get_contents($source));
        if (!empty($upload['error'])) {
            return 0;
        }

        $attachmentId = wp_insert_attachment([
            'post_mime_type' => 'image/png',
            'post_title' => $title,
            'post_status' => 'inherit',
        ], $upload['file']);

        if (is_wp_error($attachmentId)) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachmentId, wp_generate_attachment_metadata($attachmentId, $upload['file']));
        update_post_meta($attachmentId, '_petshop_placeholder_key', $key);
        update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
        update_post_meta($attachmentId, '_petshop_placeholder_source', 'AI-generated placeholder for editable Gutenberg seed.');
        update_post_meta($attachmentId, '_petshop_placeholder_license', 'Generated project placeholder; replace with approved brand/customer media before final campaign use.');
        update_option($option, (int) $attachmentId, false);

        return (int) $attachmentId;
    }

    /**
     * @param list<string> $categorySlugs
     * @param list<string> $searchTerms
     * @return list<int>
     */
    private static function commercialProductIds(array $categorySlugs, array $searchTerms, int $limit): array
    {
        $ids = [];
        foreach ($searchTerms as $term) {
            $query = new \WP_Query([
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'fields' => 'ids',
                's' => $term,
                'orderby' => 'title',
                'order' => 'ASC',
            ]);
            foreach ($query->posts as $productId) {
                self::appendVisibleProductId($ids, (int) $productId, $limit);
            }
            if (count($ids) >= $limit) {
                return $ids;
            }
        }

        $termIds = [];
        foreach ($categorySlugs as $slug) {
            $term = get_term_by('slug', sanitize_title($slug), 'product_cat');
            if ($term instanceof \WP_Term) {
                $termIds[] = (int) $term->term_id;
            }
        }

        if ($termIds !== []) {
            $query = new \WP_Query([
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'fields' => 'ids',
                'orderby' => 'date',
                'order' => 'DESC',
                'tax_query' => [
                    [
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $termIds,
                    ],
                ],
            ]);
            foreach ($query->posts as $productId) {
                self::appendVisibleProductId($ids, (int) $productId, $limit);
            }
        }

        return $ids;
    }

    /**
     * @param list<int> $ids
     */
    private static function appendVisibleProductId(array &$ids, int $productId, int $limit): void
    {
        if (count($ids) >= $limit || in_array($productId, $ids, true)) {
            return;
        }

        $product = wc_get_product($productId);
        if (
            !$product instanceof \WC_Product
            || $product->get_status() !== 'publish'
            || !in_array($product->get_catalog_visibility(), ['visible', 'catalog'], true)
        ) {
            return;
        }

        $ids[] = $productId;
    }

    /**
     * @param list<int> $productIds
     */
    private static function animalRepublikPageContent(int $imageId, array $productIds): string
    {
        $imageUrl = $imageId > 0 ? (string) wp_get_attachment_image_url($imageId, 'full') : '';
        $animalRepublikTerm = get_term_by('slug', 'animal-republik', 'product_cat');
        $productGrid = $animalRepublikTerm instanceof \WP_Term
            ? \Petshop\Core\ProductGridBlock::blockMarkup([
                'selectionMode' => 'category',
                'categoryIds' => [(int) $animalRepublikTerm->term_id],
                'limit' => 20,
                'columns' => 4,
                'orderby' => 'menu_order',
                'order' => 'ASC',
            ])
            : \Petshop\Core\ProductGridBlock::blockMarkup([
                'selectionMode' => 'manual',
                'productIds' => $productIds,
                'limit' => max(1, min(4, count($productIds))),
                'columns' => 4,
                'orderby' => 'menu_order',
            ]);

        return self::commercialPageContent(
            'petshop-commercial-page--animal-republik',
            $imageUrl,
            $imageId,
            'Fornecedor oficial',
            'Animal Republik',
            'Cosméticos pet para rotinas de cuidado profissional e diário. Esta página usa imagem temporária editável até a chegada de materiais aprovados pelo fornecedor.',
            'Ver produtos selecionados',
            '#animal-republik-produtos',
            'animal-republik-produtos',
            '',
            '',
            'Lançamentos Animal Republik',
            'Edite os produtos no WooCommerce ou ajuste a categoria do bloco no Gutenberg para controlar esta vitrine.',
            self::commercialCategoryUrl('animal-republik'),
            $productGrid
        );
    }

    /**
     * @param list<int> $productIds
     */
    private static function premiumPageContent(int $imageId, array $productIds): string
    {
        $imageUrl = $imageId > 0 ? (string) wp_get_attachment_image_url($imageId, 'full') : '';
        $premiumTerm = get_term_by('slug', 'premium', 'product_cat');
        $productGrid = $premiumTerm instanceof \WP_Term
            ? \Petshop\Core\ProductGridBlock::blockMarkup([
                'selectionMode' => 'category',
                'categoryIds' => [(int) $premiumTerm->term_id],
                'limit' => 20,
                'columns' => 4,
                'orderby' => 'menu_order',
                'order' => 'ASC',
            ])
            : \Petshop\Core\ProductGridBlock::blockMarkup([
                'selectionMode' => 'manual',
                'productIds' => $productIds,
                'limit' => max(1, min(20, count($productIds))),
                'columns' => 4,
                'orderby' => 'menu_order',
            ]);

        return self::commercialPageContent(
            'petshop-commercial-page--premium',
            $imageUrl,
            $imageId,
            'Curadoria especial',
            'Produtos premium',
            'Uma seleção editável de produtos com acabamento, materiais e apresentação mais elaborados, sem promessas comerciais que dependam de validação externa.',
            'Conhecer seleção',
            '#premium-produtos',
            'premium-produtos',
            'Critério de curadoria',
            'Use esta página para destacar itens com acabamento diferenciado, composição visual cuidadosa ou apresentação de presente. Ajuste o texto e os produtos no Gutenberg conforme a curadoria comercial aprovada.',
            'Seleção premium',
            'Produtos reais do WooCommerce, com preço, variações, estoque e compra preservados nas páginas de produto comuns.',
            self::commercialCategoryUrl('premium'),
            $productGrid
        );
    }

    private static function commercialCategoryUrl(string $categorySlug): string
    {
        $shopPath = (string) wp_parse_url((string) wc_get_page_permalink('shop'), PHP_URL_PATH);
        if ($shopPath === '') {
            $shopPath = '/loja/';
        }

        return add_query_arg(
            ['product_cat' => [sanitize_title($categorySlug)]],
            $shopPath
        );
    }

    private static function commercialPageContent(
        string $pageClass,
        string $imageUrl,
        int $imageId,
        string $eyebrow,
        string $title,
        string $intro,
        string $ctaLabel,
        string $ctaUrl,
        string $productsAnchor,
        string $contextTitle,
        string $contextText,
        string $productsTitle,
        string $productsIntro,
        string $viewAllUrl,
        string $productGrid
    ): string {
        $coverAttributes = [
            'url' => $imageUrl,
            'id' => $imageId,
            'dimRatio' => 0,
            'focalPoint' => ['x' => 0.72, 'y' => 0.5],
            'minHeight' => 420,
            'minHeightUnit' => 'px',
            'contentPosition' => 'center left',
            'isDark' => false,
            'className' => 'petshop-hero petshop-commercial-hero',
        ];
        $coverJson = wp_json_encode($coverAttributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $imageTag = $imageUrl !== ''
            ? '<img class="wp-block-cover__image-background wp-image-' . (int) $imageId . '" alt="" src="' . esc_url($imageUrl) . '" style="object-position:72% 50%" data-object-fit="cover" data-object-position="72% 50%"/>'
            : '';
        $contextSection = $contextTitle !== '' || $contextText !== ''
            ? '<!-- wp:group {"tagName":"section","className":"petshop-section petshop-commercial-context","layout":{"type":"constrained"}} -->'
                . '<section class="wp-block-group petshop-section petshop-commercial-context">'
                . '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">' . esc_html($contextTitle) . '</h2><!-- /wp:heading -->'
                . '<!-- wp:paragraph --><p>' . esc_html($contextText) . '</p><!-- /wp:paragraph -->'
                . '</section><!-- /wp:group -->'
            : '';

        return '<!-- wp:group {"className":"petshop-commercial-page ' . esc_attr($pageClass) . '","layout":{"type":"default"}} -->'
            . '<div class="wp-block-group petshop-commercial-page ' . esc_attr($pageClass) . '">'
            . '<!-- wp:cover ' . $coverJson . ' -->'
            . '<div class="wp-block-cover is-light has-custom-content-position is-position-center-left petshop-hero petshop-commercial-hero" style="min-height:420px">'
            . '<span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span>'
            . $imageTag
            . '<div class="wp-block-cover__inner-container">'
            . '<!-- wp:group {"className":"petshop-hero__copy","layout":{"type":"constrained"}} -->'
            . '<div class="wp-block-group petshop-hero__copy">'
            . '<!-- wp:paragraph {"className":"petshop-eyebrow"} --><p class="petshop-eyebrow">' . esc_html($eyebrow) . '</p><!-- /wp:paragraph -->'
            . '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">' . esc_html($title) . '</h1><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>' . esc_html($intro) . '</p><!-- /wp:paragraph -->'
            . '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url($ctaUrl) . '">' . esc_html($ctaLabel) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
            . '</div><!-- /wp:group -->'
            . '</div></div><!-- /wp:cover -->'
            . $contextSection
            . '<!-- wp:group {"tagName":"section","className":"petshop-section petshop-product-showcase petshop-commercial-products","layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group petshop-section petshop-product-showcase petshop-commercial-products" id="' . esc_attr(sanitize_title($productsAnchor)) . '">'
            . '<!-- wp:group {"className":"petshop-section-head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->'
            . '<div class="wp-block-group petshop-section-head"><!-- wp:heading {"level":2} --><h2 class="wp-block-heading">' . esc_html($productsTitle) . '</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph {"className":"petshop-section-head__cta"} --><p class="petshop-section-head__cta"><a class="petshop-section-head__link" href="' . esc_url($viewAllUrl) . '">' . esc_html__('Ver tudo', 'petshop-core') . '</a></p><!-- /wp:paragraph -->'
            . '</div><!-- /wp:group -->'
            . '<!-- wp:paragraph {"className":"petshop-product-showcase__intro"} --><p class="petshop-product-showcase__intro">' . esc_html($productsIntro) . '</p><!-- /wp:paragraph -->'
            . $productGrid
            . '</section><!-- /wp:group -->'
            . '</div><!-- /wp:group -->';
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

    /**
     * @return array{desktop: int, mobile: int}
     */
    public static function supportSectionAttachments(): array
    {
        return [
            'desktop' => self::ensureSupportSectionAttachment(
                'support-section-desktop-v2',
                'atendimento-home-desktop.png',
                __('Atendimento da Home - imagem desktop', 'petshop-core'),
                __('Atendimento por mensagem junto a acessórios pet e embalagem de pedido.', 'petshop-core')
            ),
            'mobile' => self::ensureSupportSectionAttachment(
                'support-section-mobile-v2',
                'atendimento-home-mobile.png',
                __('Atendimento da Home - imagem mobile', 'petshop-core'),
                __('Celular com conversa de atendimento ao lado de acessórios pet e cachorro.', 'petshop-core')
            ),
        ];
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

    private static function ensureSupportSectionAttachment(string $key, string $filename, string $title, string $alt): int
    {
        $option = 'petshop_' . str_replace('-', '_', $key) . '_attachment_id';
        $existingId = (int) get_option($option);
        if ($existingId > 0 && get_post($existingId) instanceof \WP_Post) {
            return $existingId;
        }

        $placeholder = self::placeholderAttachment($key);
        if ($placeholder > 0) {
            update_option($option, $placeholder, false);

            return $placeholder;
        }

        $source = get_stylesheet_directory() . '/assets/images/' . $filename;
        if (!is_readable($source)) {
            return 0;
        }

        $upload = wp_upload_bits($filename, null, (string) file_get_contents($source));
        if (!empty($upload['error'])) {
            return 0;
        }

        $attachmentId = wp_insert_attachment([
            'post_mime_type' => 'image/png',
            'post_title' => $title,
            'post_status' => 'inherit',
        ], $upload['file']);

        if (is_wp_error($attachmentId)) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachmentId, wp_generate_attachment_metadata($attachmentId, $upload['file']));
        update_post_meta($attachmentId, '_petshop_placeholder_key', $key);
        update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
        update_option($option, (int) $attachmentId, false);

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
