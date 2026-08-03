<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

final class StorefrontExperience
{
    private const VERSION = '2.4.1';
    private const OPTION = 'petshop_storefront_version';
    private const LOCK_OPTION = 'petshop_storefront_migration_lock';
    private const ERROR_OPTION = 'petshop_storefront_migration_error';
    private const COMMERCIAL_MENU_OPTION = 'petshop_commercial_menu_version';
    private static bool $catalogLayoutOpen = false;

    public static function bootstrap(): void
    {
        add_action('admin_init', [self::class, 'maybeEnsureStorefront'], 40);
        add_action('admin_notices', [self::class, 'renderMigrationNotice']);
        add_action('admin_notices', [self::class, 'renderPlaceholderProductNotice']);
        add_action('product_cat_add_form_fields', [self::class, 'renderAddCategoryFields']);
        add_action('product_cat_edit_form_fields', [self::class, 'renderEditCategoryFields']);
        add_action('created_product_cat', [self::class, 'saveCategoryFields']);
        add_action('edited_product_cat', [self::class, 'saveCategoryFields']);
        add_filter('wp_nav_menu_objects', [self::class, 'filterSeasonalMenuItems']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueCatalogFilterAssets']);
        add_action('template_redirect', [self::class, 'canonicalizeCatalogCategoryFilter']);
        add_action('woocommerce_before_shop_loop', [self::class, 'renderCatalogFilter'], 15);
        add_action('woocommerce_before_shop_loop', [self::class, 'closeCatalogToolbar'], 40);
        add_action('woocommerce_single_product_summary', [self::class, 'renderProductAssurance'], 25);
        add_filter('woocommerce_output_related_products_args', [self::class, 'relatedProductArgs']);
        add_action('pre_get_posts', [self::class, 'resolveExactSkuSearch']);
        add_action('pre_get_posts', [self::class, 'applyCatalogCategoryFilter'], 20);
        add_filter('posts_search', [self::class, 'filterExactSkuSearch'], 20, 2);
        add_action('wp_head', [self::class, 'renderMetaDescription'], 1);
        add_action('wp_head', [self::class, 'renderArchiveCanonical'], 2);
        add_shortcode('petshop_categories', [self::class, 'renderCategoryGrid']);
        add_shortcode('petshop_seasonal_products', [self::class, 'renderSeasonalProducts']);
        add_shortcode('petshop_reviews', [self::class, 'renderReviews']);
        add_shortcode('petshop_featured_products', [self::class, 'renderFeaturedProducts']);
        add_shortcode('petshop_kits_section', [self::class, 'renderKitsSection']);
        add_shortcode('petshop_product_showcase', [self::class, 'renderProductShowcase']);
        add_shortcode('petshop_featured_products_grid', [self::class, 'renderFeaturedProductsGrid']);
        add_shortcode('petshop_kits_section_grid', [self::class, 'renderKitsSectionGrid']);
        add_shortcode('petshop_seasonal_products_grid', [self::class, 'renderSeasonalProductsGrid']);
        add_shortcode('petshop_product_showcase_grid', [self::class, 'renderProductShowcaseGrid']);
        add_shortcode('petshop_reviews_section', [self::class, 'renderReviewsSection']);
        add_shortcode('petshop_support_banner', [self::class, 'renderSupportBanner']);
        add_filter('render_block', [self::class, 'hideEmptyHomeSections'], 10, 2);
        add_filter('woocommerce_product_get_rating_html', [self::class, 'filterProductRatingHtml'], 10, 3);
    }

    public static function maybeEnsureStorefront(): void
    {
        $isCli = defined('WP_CLI') && WP_CLI;
        if (
            (!$isCli && !current_user_can('manage_woocommerce') && !current_user_can('manage_options'))
            || !class_exists('WooCommerce')
            || get_option(self::OPTION) === self::VERSION
        ) {
            return;
        }

        $lock = (int) get_option(self::LOCK_OPTION);
        if ($lock > 0 && $lock > time() - 300) {
            return;
        }
        if ($lock > 0) {
            delete_option(self::LOCK_OPTION);
        }
        if (!add_option(self::LOCK_OPTION, time(), '', false)) {
            return;
        }

        try {
            self::ensureStorefront();
            delete_option(self::ERROR_OPTION);
        } catch (\Throwable $error) {
            update_option(self::ERROR_OPTION, $error->getMessage(), false);
            error_log('Petshop storefront migration failed: ' . $error->getMessage());
        } finally {
            delete_option(self::LOCK_OPTION);
        }
    }

    public static function renderMigrationNotice(): void
    {
        $message = (string) get_option(self::ERROR_OPTION, '');
        if ($message === '' || !current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html(sprintf(__('Não foi possível atualizar a configuração da loja: %s', 'petshop-core'), $message));
        echo '</p></div>';
    }

    public static function renderPlaceholderProductNotice(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $productId = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if (
            !$screen instanceof \WP_Screen
            || $screen->base !== 'post'
            || $screen->post_type !== 'product'
            || $productId <= 0
            || !(bool) get_post_meta($productId, '_petshop_placeholder_004b', true)
        ) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__(
            'Produto demonstrativo do Plano 004: substitua a fotografia provisória e revise o conteúdo antes da publicação ao cliente.',
            'petshop-core'
        );
        echo '</p></div>';
    }

    public static function ensureStorefront(): void
    {
        if (!class_exists('WooCommerce') || get_option(self::OPTION) === self::VERSION) {
            return;
        }

        $isInitialInstall = get_option(self::OPTION, false) === false;
        $aboutId = self::ensurePage(
            'sobre-o-autelie',
            'Sobre o Auteliê',
            '<!-- wp:heading --><h2 class="wp-block-heading">Acessórios feitos para celebrar cada pet</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>O Auteliê Moda Pet reúne acessórios com acabamento cuidadoso para tutores e profissionais de banho e tosa.</p><!-- /wp:paragraph -->'
        );
        $supportId = self::ensurePage(
            'atendimento',
            'Atendimento',
            '<!-- wp:heading --><h2 class="wp-block-heading">Como podemos ajudar?</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Use os canais oficiais informados pela loja para tirar dúvidas sobre produtos, pedidos e cuidados com os acessórios.</p><!-- /wp:paragraph -->'
        );
        $shippingId = self::ensurePage(
            'envios-e-entregas',
            'Envios e entregas',
            '<!-- wp:heading --><h2 class="wp-block-heading">Envios e entregas</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Prazo, modalidade e valor de entrega são apresentados antes da conclusão do pedido, conforme o endereço informado.</p><!-- /wp:paragraph -->'
        );
        $personalizeId = self::ensurePage(
            'personalize',
            'Personalize',
            '<!-- wp:heading --><h2 class="wp-block-heading">Personalização em preparação</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Esta área está reservada para uma futura experiência de personalização. O catálogo atual continua disponível normalmente.</p><!-- /wp:paragraph -->'
        );
        $collectionsId = self::ensurePage(
            'colecoes',
            'Coleções',
            '<!-- wp:heading --><h2 class="wp-block-heading">Coleções para cada ocasião</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Conheça as coleções disponíveis e encontre acessórios para diferentes estilos e épocas do ano.</p><!-- /wp:paragraph -->'
            . '<!-- wp:shortcode -->[petshop_categories limit="8"]<!-- /wp:shortcode -->'
        );
        $policiesId = self::ensurePage(
            'politicas-da-loja',
            'Políticas da loja',
            '<!-- wp:heading --><h2 class="wp-block-heading">Políticas da loja</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Consulte aqui as políticas comerciais, de privacidade e de troca aprovadas para a loja antes da publicação.</p><!-- /wp:paragraph -->'
        );
        $wishlistId = self::ensurePage(
            'lista-de-desejos',
            'Lista de desejos',
            self::wishlistPageContent()
        );
        if (get_theme_mod('petshop_wishlist_page', null) === null) {
            set_theme_mod('petshop_wishlist_page', $wishlistId);
        }
        self::migrateWishlistPage($wishlistId);
        $heroId = self::placeholderAttachment('hero-wide');
        if ($heroId <= 0) {
            throw new \RuntimeException('Imagem hero-wide ausente; execute o seed 004b e tente novamente.');
        }
        $existingHome = get_page_by_path('inicio');
        $homeId = self::ensurePage(
            'inicio',
            'Início',
            self::homeContent(
                (string) wc_get_page_permalink('shop'),
                (string) get_permalink($supportId),
                $heroId
            )
        );
        if (!$existingHome instanceof \WP_Post) {
            self::stampNewManagedHome($homeId, (string) wc_get_page_permalink('shop'), $heroId);
        }

        self::migrateManagedHome(
            $homeId,
            (string) wc_get_page_permalink('shop'),
            (string) get_permalink($supportId),
            $heroId
        );
        if ($isInitialInstall) {
            self::upgradeWooCommerceBlocks();
            self::configureMenus($homeId, $aboutId, $supportId, $shippingId, $personalizeId, $policiesId);
            self::configureTheme($homeId);
            update_option('show_on_front', 'page');
            update_option('page_on_front', $homeId);
            update_option('woocommerce_coming_soon', 'no');
            update_option('woocommerce_hide_out_of_stock_items', 'yes');
        } else {
            self::addPolicyToManagedFooter($policiesId);
        }
        self::ensureCommercialMenu($collectionsId, $personalizeId);
        self::ensureHeaderDefaults();

        flush_rewrite_rules(false);

        update_option(self::OPTION, self::VERSION, false);
    }

    public static function renderAddCategoryFields(): void
    {
        wp_nonce_field('petshop_category_fields', 'petshop_category_nonce');
        ?>
        <div class="form-field">
            <label for="petshop_menu_order"><?php esc_html_e('Ordem comercial', 'petshop-core'); ?></label>
            <input type="number" min="0" name="petshop_menu_order" id="petshop_menu_order" value="0">
        </div>
        <div class="form-field">
            <label>
                <input type="checkbox" name="petshop_seasonal" value="1">
                <?php esc_html_e('Categoria sazonal', 'petshop-core'); ?>
            </label>
        </div>
        <div class="form-field">
            <label>
                <input type="checkbox" name="petshop_visible_in_menu" value="1" checked>
                <?php esc_html_e('Exibir na navegação', 'petshop-core'); ?>
            </label>
        </div>
        <div class="form-field">
            <label><?php esc_html_e('Ícone da vitrine', 'petshop-core'); ?></label>
            <?php CategoryIcons::renderPicker(''); ?>
        </div>
        <?php
    }

    public static function renderEditCategoryFields(\WP_Term $term): void
    {
        wp_nonce_field('petshop_category_fields', 'petshop_category_nonce');
        $order = (int) get_term_meta($term->term_id, 'petshop_menu_order', true);
        $seasonal = (bool) get_term_meta($term->term_id, 'petshop_seasonal', true);
        $visible = (bool) get_term_meta($term->term_id, 'petshop_visible_in_menu', true);
        $icon = (string) get_term_meta($term->term_id, CategoryIcons::META_KEY, true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="petshop_menu_order"><?php esc_html_e('Ordem comercial', 'petshop-core'); ?></label></th>
            <td><input type="number" min="0" name="petshop_menu_order" id="petshop_menu_order" value="<?php echo esc_attr((string) $order); ?>"></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e('Sazonalidade', 'petshop-core'); ?></th>
            <td>
                <label><input type="checkbox" name="petshop_seasonal" value="1" <?php checked($seasonal); ?>> <?php esc_html_e('Categoria sazonal', 'petshop-core'); ?></label><br>
                <label><input type="checkbox" name="petshop_visible_in_menu" value="1" <?php checked($visible); ?>> <?php esc_html_e('Exibir na navegação', 'petshop-core'); ?></label>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e('Ícone da vitrine', 'petshop-core'); ?></th>
            <td><?php CategoryIcons::renderPicker($icon); ?></td>
        </tr>
        <?php
    }

    public static function saveCategoryFields(int $termId): void
    {
        if (
            !isset($_POST['petshop_category_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['petshop_category_nonce'])),
                'petshop_category_fields'
            )
            || !current_user_can('manage_woocommerce')
        ) {
            return;
        }

        update_term_meta($termId, 'petshop_menu_order', isset($_POST['petshop_menu_order']) ? absint($_POST['petshop_menu_order']) : 0);
        update_term_meta($termId, 'petshop_seasonal', isset($_POST['petshop_seasonal']));
        update_term_meta($termId, 'petshop_visible_in_menu', isset($_POST['petshop_visible_in_menu']));

        if (array_key_exists(CategoryIcons::META_KEY, $_POST)) {
            $icon = sanitize_key((string) wp_unslash($_POST[CategoryIcons::META_KEY]));
            if ($icon === '' || !CategoryIcons::isValid($icon)) {
                delete_term_meta($termId, CategoryIcons::META_KEY);
            } else {
                update_term_meta($termId, CategoryIcons::META_KEY, $icon);
            }
        }
    }

    /**
     * @param array<int, \WP_Post> $items
     * @return array<int, \WP_Post>
     */
    public static function filterSeasonalMenuItems(array $items): array
    {
        return array_values(
            array_filter(
                $items,
                static function (\WP_Post $item): bool {
                    if ($item->type !== 'taxonomy' || $item->object !== 'product_cat') {
                        return true;
                    }

                    $termId = (int) $item->object_id;
                    return (bool) get_term_meta($termId, 'petshop_visible_in_menu', true);
                }
            )
        );
    }

    public static function renderCatalogFilter(): void
    {
        if (!is_shop() && !is_product_taxonomy()) {
            return;
        }

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'meta_key' => 'petshop_menu_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms) || $terms === []) {
            return;
        }

        $selectedSlugs = self::selectedCatalogCategorySlugs();
        if ($selectedSlugs === [] && is_product_category()) {
            $currentTerm = get_queried_object();
            if ($currentTerm instanceof \WP_Term) {
                $selectedSlugs = [$currentTerm->slug];
            }
        }
        self::$catalogLayoutOpen = true;

        echo '<aside class="petshop-catalog-sidebar" aria-labelledby="petshop-catalog-filter-title">';
        echo '<form class="petshop-catalog-filter" action="' . esc_url(wc_get_page_permalink('shop')) . '" method="get">';
        echo '<h2 id="petshop-catalog-filter-title" class="petshop-catalog-filter__title">' . esc_html__('Categorias', 'petshop-core') . '</h2>';
        echo '<div class="petshop-catalog-filter__search">';
        echo '<label for="petshop-category-search">' . esc_html__('Filtrar categorias', 'petshop-core') . '</label>';
        echo '<input id="petshop-category-search" type="search" placeholder="' . esc_attr__('Digite uma categoria', 'petshop-core') . '" autocomplete="off" aria-controls="petshop-category-options">';
        echo '</div>';
        echo '<p class="screen-reader-text" data-petshop-category-status aria-live="polite"></p>';
        echo '<ul id="petshop-category-options">';
        foreach ($terms as $term) {
            if (!(bool) get_term_meta($term->term_id, 'petshop_visible_in_menu', true)) {
                continue;
            }
            $inputId = 'petshop-category-' . (int) $term->term_id;
            echo '<li>';
            echo '<label for="' . esc_attr($inputId) . '">';
            echo '<input id="' . esc_attr($inputId) . '" type="checkbox" name="petshop_categories[]" value="' . esc_attr($term->slug) . '"' . checked(in_array($term->slug, $selectedSlugs, true), true, false) . '>';
            echo '<span class="petshop-catalog-filter__name">' . esc_html($term->name) . '</span>';
            echo '<span class="petshop-catalog-filter__count" aria-label="' . esc_attr(sprintf(_n('%d produto', '%d produtos', $term->count, 'petshop-core'), $term->count)) . '">' . esc_html((string) $term->count) . '</span>';
            echo '</label>';
            echo '</li>';
        }
        echo '</ul>';
        echo '<button class="petshop-button petshop-catalog-filter__apply" type="submit">' . esc_html__('Aplicar filtros', 'petshop-core') . '</button>';
        echo '</form>';
        echo '</aside>';
        echo '<div class="petshop-catalog-toolbar">';
    }

    public static function enqueueCatalogFilterAssets(): void
    {
        if (!is_shop() && !is_product_taxonomy()) {
            return;
        }

        $assetPath = dirname(__DIR__) . '/assets/js/catalog-filter.js';
        wp_enqueue_script(
            'petshop-catalog-filter',
            plugins_url('assets/js/catalog-filter.js', PETSHOP_CORE_FILE),
            [],
            is_file($assetPath) ? (string) filemtime($assetPath) : self::VERSION,
            true
        );
    }

    public static function applyCatalogCategoryFilter(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('product')) {
            return;
        }

        $selectedSlugs = self::selectedCatalogCategorySlugs();
        if ($selectedSlugs === []) {
            return;
        }

        $taxQuery = (array) $query->get('tax_query');
        $taxQuery[] = [
            'taxonomy' => 'product_cat',
            'field' => 'slug',
            'terms' => $selectedSlugs,
            'operator' => 'IN',
            'include_children' => true,
        ];
        $query->set('tax_query', $taxQuery);
    }

    public static function canonicalizeCatalogCategoryFilter(): void
    {
        if (!is_product_taxonomy()) {
            return;
        }

        $selectedSlugs = self::selectedCatalogCategorySlugs();
        if ($selectedSlugs === []) {
            return;
        }

        $url = add_query_arg(
            'petshop_categories',
            implode(',', $selectedSlugs),
            wc_get_page_permalink('shop')
        );
        if (isset($_GET['orderby']) && is_scalar($_GET['orderby'])) {
            $url = add_query_arg('orderby', sanitize_key(wp_unslash((string) $_GET['orderby'])), $url);
        }
        wp_safe_redirect($url, 302, 'Petshop catalog filter');
        exit;
    }

    /** @return list<string> */
    private static function selectedCatalogCategorySlugs(): array
    {
        if (!isset($_GET['petshop_categories'])) {
            return [];
        }

        $raw = wp_unslash($_GET['petshop_categories']);
        $values = is_array($raw) ? $raw : [$raw];
        $slugs = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            foreach (explode(',', (string) $value) as $candidate) {
                $slug = sanitize_title($candidate);
                if ($slug !== '') {
                    $slugs[] = $slug;
                }
            }
        }

        return array_values(array_unique($slugs));
    }

    public static function closeCatalogToolbar(): void
    {
        if (self::$catalogLayoutOpen) {
            echo '</div>';
            self::$catalogLayoutOpen = false;
        }
    }

    public static function resolveExactSkuSearch(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || !$query->is_search() || !class_exists('WooCommerce')) {
            return;
        }
        $postType = $query->get('post_type');
        if ($postType !== 'product' && $postType !== ['product']) {
            return;
        }
        $search = trim((string) $query->get('s'));
        if ($search === '') {
            return;
        }
        $productId = wc_get_product_id_by_sku($search);
        if ($productId <= 0) {
            return;
        }

        $product = wc_get_product($productId);
        if ($product && $product->is_type('variation')) {
            $productId = $product->get_parent_id();
        }

        $query->set('_petshop_exact_sku_product_id', $productId);
        $query->set('posts_per_page', 1);
    }

    public static function filterExactSkuSearch(string $searchSql, \WP_Query $query): string
    {
        $productId = (int) $query->get('_petshop_exact_sku_product_id');
        if ($productId <= 0) {
            return $searchSql;
        }

        global $wpdb;

        return $wpdb->prepare(" AND {$wpdb->posts}.ID = %d", $productId);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderCategoryGrid(array $attributes = []): string
    {
        $attributes = shortcode_atts(['limit' => 8], $attributes, 'petshop_categories');
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => 0,
            'meta_key' => 'petshop_menu_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms)) {
            return '';
        }

        $visibleTerms = array_values(
            array_filter(
                $terms,
                static fn (\WP_Term $term): bool => $term->slug !== 'promocoes'
                    && (bool) get_term_meta(
                        $term->term_id,
                        'petshop_visible_in_menu',
                        true
                    )
            )
        );
        $visibleTerms = array_slice($visibleTerms, 0, max(1, absint($attributes['limit'])));

        self::enqueueCategoryPreviewAssets();

        ob_start();
        echo '<ul class="petshop-category-grid" aria-label="' . esc_attr__('Categorias de produtos', 'petshop-core') . '">';
        foreach ($visibleTerms as $term) {
            $icon = CategoryIcons::resolveForTerm($term);
            $iconUrl = CategoryIcons::url($icon);
            $termLink = get_term_link($term);
            if (is_wp_error($termLink)) {
                continue;
            }
            $previewId = 'petshop-category-preview-' . (int) $term->term_id;
            $products = self::categoryPreviewProducts($term);
            $hasPreview = $products !== [];

            echo '<li class="petshop-category-card"' . ($hasPreview ? ' data-petshop-category-preview' : '') . '>';
            echo '<a class="petshop-category-card__trigger" href="' . esc_url($termLink) . '"';
            if ($hasPreview) {
                echo ' aria-expanded="false" aria-controls="' . esc_attr($previewId) . '"';
            }
            echo '>';
            echo '<span class="petshop-category-card__well">';
            echo '<span class="petshop-category-card__icon" style="--petshop-category-icon: url(\'' . esc_url($iconUrl) . '\')" aria-hidden="true"></span>';
            echo '</span>';
            echo '<span class="petshop-category-card__label">' . esc_html($term->name) . '</span>';
            echo '</a>';
            if ($hasPreview) {
                echo self::renderCategoryPreviewPanel($term, $products, $previewId, (string) $termLink);
            }
            echo '</li>';
        }
        echo '</ul>';

        return (string) ob_get_clean();
    }

    public static function enqueueCategoryPreviewAssets(): void
    {
        $assetPath = dirname(__DIR__) . '/assets/js/category-preview.js';
        wp_enqueue_script(
            'petshop-category-preview',
            plugins_url('assets/js/category-preview.js', PETSHOP_CORE_FILE),
            [],
            is_file($assetPath) ? (string) filemtime($assetPath) : self::VERSION,
            true
        );
    }

    /**
     * @return list<\WC_Product>
     */
    private static function categoryPreviewProducts(\WP_Term $term): array
    {
        if (!function_exists('wc_get_products')) {
            return [];
        }

        $candidates = wc_get_products([
            'status' => 'publish',
            'limit' => 12,
            'category' => [$term->slug],
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);

        if (!is_array($candidates) || $candidates === []) {
            return [];
        }

        $valid = array_values(array_filter(
            $candidates,
            static fn ($product): bool => $product instanceof \WC_Product
        ));
        $withImage = array_values(array_filter(
            $valid,
            static fn (\WC_Product $product): bool => $product->get_image_id() > 0
        ));

        return array_slice($withImage !== [] ? $withImage : $valid, 0, 3);
    }

    /**
     * @param list<\WC_Product> $products
     */
    private static function renderCategoryPreviewPanel(
        \WP_Term $term,
        array $products,
        string $previewId,
        string $termLink
    ): string {
        ob_start();
        echo '<div id="' . esc_attr($previewId) . '" class="petshop-category-preview" hidden aria-hidden="true" role="region" aria-label="'
            . esc_attr(sprintf(__('Prévia de %s', 'petshop-core'), $term->name))
            . '">';
        echo '<ul class="petshop-category-preview__products">';
        foreach ($products as $product) {
            $image = $product->get_image(
                'woocommerce_thumbnail',
                [
                    'class' => 'petshop-category-preview__image',
                    'alt' => $product->get_name(),
                    'loading' => 'lazy',
                    'decoding' => 'async',
                ]
            );
            echo '<li class="petshop-category-preview__item">';
            echo '<a class="petshop-category-preview__link" href="' . esc_url($product->get_permalink()) . '">';
            echo '<span class="petshop-category-preview__media">' . $image . '</span>';
            echo '<span class="petshop-category-preview__name">' . esc_html($product->get_name()) . '</span>';
            echo '<span class="petshop-category-preview__price">' . wp_kses_post($product->get_price_html()) . '</span>';
            echo '</a></li>';
        }
        echo '</ul>';
        echo '<a class="petshop-category-preview__cta" href="' . esc_url($termLink) . '">';
        echo esc_html(sprintf(__('Ver %s', 'petshop-core'), $term->name));
        echo '</a>';
        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderSeasonalProducts(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            [
                'limit' => 4,
                'columns' => 4,
                'title' => __('Coleção da estação', 'petshop-core'),
                'cta' => __('Ver todos →', 'petshop-core'),
                'cta_url' => '',
            ],
            $attributes,
            'petshop_seasonal_products'
        );

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'petshop_seasonal', 'value' => '1'],
                ['key' => 'petshop_visible_in_menu', 'value' => '1'],
            ],
        ]);
        if (is_wp_error($terms) || $terms === []) {
            return '';
        }

        $slugs = implode(',', wp_list_pluck($terms, 'slug'));
        $products = do_shortcode(sprintf(
            '[products limit="%d" columns="%d" category="%s" orderby="date" order="DESC"]',
            max(1, absint($attributes['limit'])),
            max(1, absint($attributes['columns'])),
            esc_attr($slugs)
        ));

        $ctaUrl = self::resolveSeasonalCtaUrl((string) $attributes['cta_url'], $terms);

        return self::wrapProductShowcase(
            'petshop-seasonal-section',
            'petshop-seasonal-heading',
            (string) $attributes['title'],
            (string) $attributes['cta'],
            $ctaUrl,
            '',
            $products
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderReviews(array $attributes = []): string
    {
        $attributes = shortcode_atts(['limit' => 3], $attributes, 'petshop_reviews');
        $reviews = get_comments([
            'status' => 'approve',
            'post_type' => 'product',
            'type' => 'review',
            'number' => max(1, absint($attributes['limit'])),
        ]);
        if ($reviews === []) {
            return '';
        }

        ob_start();
        echo '<ul class="petshop-review-grid" aria-label="' . esc_attr__('Avaliações de clientes', 'petshop-core') . '">';
        foreach ($reviews as $review) {
            $rating = (int) get_comment_meta($review->comment_ID, 'rating', true);
            echo '<li class="petshop-review-card">';
            if ($rating > 0) {
                echo wp_kses_post(wc_get_rating_html($rating));
            }
            echo '<blockquote><p>' . esc_html(wp_trim_words($review->comment_content, 35)) . '</p></blockquote>';
            echo '<cite>' . esc_html($review->comment_author) . '</cite>';
            echo '</li>';
        }
        echo '</ul>';

        return (string) ob_get_clean();
    }

    public static function filterProductRatingHtml(string $ratingHtml, float $rating, int $count): string
    {
        return $count > 0 ? $ratingHtml : '';
    }

    private static function hasCatalogSales(): bool
    {
        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'total_sales',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'NUMERIC',
                ],
            ],
            'no_found_rows' => true,
        ]);

        return $query->have_posts();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderFeaturedProducts(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            [
                'limit' => 4,
                'columns' => 4,
                'fallback_title' => '',
                'cta' => __('Ver todos →', 'petshop-core'),
                'cta_url' => '',
            ],
            $attributes,
            'petshop_featured_products'
        );

        $fallbackTitle = trim((string) $attributes['fallback_title']);
        if ($fallbackTitle === '') {
            $fallbackTitle = (string) get_theme_mod(
                'petshop_featured_section_title',
                __('Destaques da loja', 'petshop-core')
            );
        }

        $title = self::hasCatalogSales()
            ? __('Mais vendidos', 'petshop-core')
            : $fallbackTitle;

        $products = do_shortcode(
            sprintf(
                '[products limit="%d" columns="%d" orderby="popularity"]',
                max(1, absint($attributes['limit'])),
                max(1, absint($attributes['columns']))
            )
        );

        $ctaUrl = self::resolveShopCtaUrl((string) $attributes['cta_url']);

        return self::wrapProductShowcase(
            'petshop-featured-section',
            'petshop-featured-heading',
            $title,
            (string) $attributes['cta'],
            $ctaUrl,
            '',
            $products
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderKitsSection(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            [
                'limit' => 4,
                'columns' => 4,
                'title' => __('Economize comprando kits', 'petshop-core'),
                'intro' => __(
                    'Escolhas práticas para compor o visual e facilitar a rotina profissional.',
                    'petshop-core'
                ),
                'cta' => __('Ver todos →', 'petshop-core'),
                'category' => 'conjuntos',
            ],
            $attributes,
            'petshop_kits_section'
        );

        $term = get_term_by('slug', (string) $attributes['category'], 'product_cat');
        if (!$term instanceof \WP_Term || (int) $term->count <= 0) {
            return '';
        }

        $products = do_shortcode(
            sprintf(
                '[products limit="%d" columns="%d" category="%s" orderby="date" order="DESC"]',
                max(1, absint($attributes['limit'])),
                max(1, absint($attributes['columns'])),
                esc_attr((string) $attributes['category'])
            )
        );

        $ctaUrl = get_term_link($term);
        if (is_wp_error($ctaUrl)) {
            $ctaUrl = wc_get_page_permalink('shop');
        }

        return self::wrapProductShowcase(
            'petshop-kits-section petshop-section--soft',
            'petshop-kits-heading',
            (string) $attributes['title'],
            (string) $attributes['cta'],
            (string) $ctaUrl,
            (string) $attributes['intro'],
            $products
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderProductShowcase(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            [
                'limit' => 4,
                'columns' => 4,
                'title' => '',
                'intro' => '',
                'cta' => __('Ver todos →', 'petshop-core'),
                'cta_url' => '',
                'category' => '',
                'orderby' => 'date',
                'order' => 'DESC',
                'class' => 'petshop-professional-section',
            ],
            $attributes,
            'petshop_product_showcase'
        );

        $title = trim((string) $attributes['title']);
        if ($title === '') {
            return '';
        }

        $category = trim((string) $attributes['category']);
        $products = $category !== ''
            ? do_shortcode(sprintf(
                '[products limit="%d" columns="%d" category="%s" orderby="%s" order="%s"]',
                max(1, absint($attributes['limit'])),
                max(1, absint($attributes['columns'])),
                esc_attr($category),
                esc_attr((string) $attributes['orderby']),
                esc_attr((string) $attributes['order'])
            ))
            : do_shortcode(sprintf(
                '[products limit="%d" columns="%d" orderby="%s" order="%s"]',
                max(1, absint($attributes['limit'])),
                max(1, absint($attributes['columns'])),
                esc_attr((string) $attributes['orderby']),
                esc_attr((string) $attributes['order'])
            ));

        $ctaUrl = self::resolveCategoryCtaUrl((string) $attributes['cta_url'], $category);

        return self::wrapProductShowcase(
            (string) $attributes['class'],
            sanitize_title($title) . '-heading',
            $title,
            (string) $attributes['cta'],
            $ctaUrl,
            (string) $attributes['intro'],
            $products
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderFeaturedProductsGrid(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            ['limit' => 4, 'columns' => 4],
            $attributes,
            'petshop_featured_products_grid'
        );

        return self::renderProductGridHtml(
            do_shortcode(
                sprintf(
                    '[products limit="%d" columns="%d" orderby="popularity"]',
                    max(1, absint($attributes['limit'])),
                    max(1, absint($attributes['columns']))
                )
            )
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderKitsSectionGrid(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            ['limit' => 4, 'columns' => 4, 'category' => 'conjuntos'],
            $attributes,
            'petshop_kits_section_grid'
        );

        $term = get_term_by('slug', (string) $attributes['category'], 'product_cat');
        if (!$term instanceof \WP_Term || (int) $term->count <= 0) {
            return '';
        }

        return self::renderProductGridHtml(
            do_shortcode(
                sprintf(
                    '[products limit="%d" columns="%d" category="%s" orderby="date" order="DESC"]',
                    max(1, absint($attributes['limit'])),
                    max(1, absint($attributes['columns'])),
                    esc_attr((string) $attributes['category'])
                )
            )
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderSeasonalProductsGrid(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            ['limit' => 4, 'columns' => 4],
            $attributes,
            'petshop_seasonal_products_grid'
        );

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'petshop_seasonal', 'value' => '1'],
                ['key' => 'petshop_visible_in_menu', 'value' => '1'],
            ],
        ]);
        if (is_wp_error($terms) || $terms === []) {
            return '';
        }

        $slugs = implode(',', wp_list_pluck($terms, 'slug'));

        return self::renderProductGridHtml(
            do_shortcode(
                sprintf(
                    '[products limit="%d" columns="%d" category="%s" orderby="date" order="DESC"]',
                    max(1, absint($attributes['limit'])),
                    max(1, absint($attributes['columns'])),
                    esc_attr($slugs)
                )
            )
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderProductShowcaseGrid(array $attributes = []): string
    {
        $attributes = shortcode_atts(
            [
                'limit' => 4,
                'columns' => 4,
                'category' => '',
                'orderby' => 'date',
                'order' => 'DESC',
            ],
            $attributes,
            'petshop_product_showcase_grid'
        );

        $category = trim((string) $attributes['category']);
        $products = $category !== ''
            ? do_shortcode(sprintf(
                '[products limit="%d" columns="%d" category="%s" orderby="%s" order="%s"]',
                max(1, absint($attributes['limit'])),
                max(1, absint($attributes['columns'])),
                esc_attr($category),
                esc_attr((string) $attributes['orderby']),
                esc_attr((string) $attributes['order'])
            ))
            : do_shortcode(sprintf(
                '[products limit="%d" columns="%d" orderby="%s" order="%s"]',
                max(1, absint($attributes['limit'])),
                max(1, absint($attributes['columns'])),
                esc_attr((string) $attributes['orderby']),
                esc_attr((string) $attributes['order'])
            ));

        return self::renderProductGridHtml($products);
    }

    private static function isBlockEditorContext(): bool
    {
        if (is_admin()) {
            return true;
        }

        return defined('REST_REQUEST') && REST_REQUEST;
    }

    public static function hideEmptyHomeSections(string $content, array $block): string
    {
        if (($block['blockName'] ?? '') !== 'core/group' || self::isBlockEditorContext()) {
            return $content;
        }

        $className = (string) ($block['attrs']['className'] ?? '');
        if (str_contains($className, 'petshop-reviews-section')) {
            if (str_contains($content, 'petshop-review-card')) {
                return $content;
            }

            return '';
        }

        if (str_contains($className, 'petshop-product-showcase')) {
            if (preg_match('/class="[^"]*\bproduct\b[^"]*"/', $content)) {
                return $content;
            }

            return '';
        }

        return $content;
    }

    private static function renderProductGridHtml(string $productsHtml): string
    {
        if (!preg_match('/class="[^"]*\bproduct\b[^"]*"/', $productsHtml)) {
            return '';
        }

        return $productsHtml;
    }

    private static function showcaseSectionGutenbergMarkup(
        string $sectionClass,
        string $headingId,
        string $title,
        string $ctaLabel,
        string $ctaUrl,
        string $intro,
        string $gridShortcode
    ): string {
        $headingId = sanitize_html_class($headingId);
        $titleEsc = esc_html($title);
        $ctaLabelEsc = esc_html($ctaLabel);
        $ctaUrlEsc = esc_url($ctaUrl);
        $introBlock = '';
        if (trim($intro) !== '') {
            $introEsc = esc_html($intro);
            $introBlock = <<<INTRO


<!-- wp:paragraph {"className":"petshop-product-showcase__intro"} -->
<p class="petshop-product-showcase__intro">{$introEsc}</p>
<!-- /wp:paragraph -->
INTRO;
        }

        $ctaBlock = '';
        if (trim($ctaLabel) !== '' && trim($ctaUrl) !== '') {
            $ctaBlock = <<<CTA

<!-- wp:paragraph {"className":"petshop-section-head__cta"} -->
<p class="petshop-section-head__cta"><a class="petshop-section-head__link" href="{$ctaUrlEsc}">{$ctaLabelEsc}</a></p>
<!-- /wp:paragraph -->
CTA;
        }

        return <<<BLOCKS
<!-- wp:group {"tagName":"section","className":"petshop-section petshop-product-showcase {$sectionClass}","layout":{"type":"constrained"}} -->
<section class="wp-block-group petshop-section petshop-product-showcase {$sectionClass}"><!-- wp:group {"className":"petshop-section-head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group petshop-section-head"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading" id="{$headingId}">{$titleEsc}</h2>
<!-- /wp:heading -->
{$ctaBlock}</div>
<!-- /wp:group -->
{$introBlock}
<!-- wp:shortcode -->{$gridShortcode}<!-- /wp:shortcode --></section>
<!-- /wp:group -->
BLOCKS;
    }

    private static function reviewsSectionGutenbergMarkup(int $limit = 3): string
    {
        $titleEsc = esc_html__('Quem compra, conta', 'petshop-core');
        $introEsc = esc_html__(
            'Avaliações reais e aprovadas dos produtos aparecem nesta seção.',
            'petshop-core'
        );
        $gridShortcode = sprintf('[petshop_reviews limit="%d"]', max(1, $limit));

        return <<<BLOCKS
<!-- wp:group {"tagName":"section","className":"petshop-section petshop-reviews-section","layout":{"type":"constrained"}} -->
<section class="wp-block-group petshop-section petshop-reviews-section"><!-- wp:group {"className":"petshop-section-head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group petshop-section-head"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading" id="petshop-reviews-heading">{$titleEsc}</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"petshop-reviews-section__intro"} -->
<p class="petshop-reviews-section__intro">{$introEsc}</p>
<!-- /wp:paragraph -->
<!-- wp:shortcode -->{$gridShortcode}<!-- /wp:shortcode --></section>
<!-- /wp:group -->
BLOCKS;
    }

    private static function renderSectionHead(
        string $title,
        string $headingId,
        string $ctaLabel,
        string $ctaUrl
    ): string {
        $headingId = sanitize_html_class($headingId);
        if ($headingId === '') {
            $headingId = 'petshop-section-heading';
        }

        ob_start();
        echo '<div class="petshop-section-head">';
        echo '<h2 id="' . esc_attr($headingId) . '" class="wp-block-heading petshop-section-head__title">';
        echo esc_html($title);
        echo '</h2>';
        if (trim($ctaLabel) !== '' && trim($ctaUrl) !== '') {
            echo '<a class="petshop-section-head__link" href="' . esc_url($ctaUrl) . '">';
            echo esc_html($ctaLabel);
            echo '</a>';
        }
        echo '</div>';

        return (string) ob_get_clean();
    }

    private static function wrapProductShowcase(
        string $sectionClass,
        string $headingId,
        string $title,
        string $ctaLabel,
        string $ctaUrl,
        string $intro,
        string $productsHtml
    ): string {
        if (!preg_match('/class="[^"]*\bproduct\b[^"]*"/', $productsHtml)) {
            return '';
        }

        ob_start();
        echo '<section class="petshop-section petshop-product-showcase ' . esc_attr(trim($sectionClass)) . '" aria-labelledby="' . esc_attr(sanitize_html_class($headingId)) . '">';
        echo self::renderSectionHead($title, $headingId, $ctaLabel, $ctaUrl);
        if (trim($intro) !== '') {
            echo '<p class="petshop-product-showcase__intro">' . esc_html($intro) . '</p>';
        }
        echo $productsHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</section>';

        return (string) ob_get_clean();
    }

    private static function resolveShopCtaUrl(string $ctaUrl): string
    {
        $ctaUrl = trim($ctaUrl);
        if ($ctaUrl !== '') {
            return $ctaUrl;
        }

        $shopUrl = wc_get_page_permalink('shop');

        return is_string($shopUrl) ? $shopUrl : home_url('/shop/');
    }

    /**
     * @param list<string> $slugs
     */
    private static function buildCatalogCategoryFilterUrl(array $slugs): string
    {
        $slugs = array_values(array_unique(array_filter(array_map(
            static fn (string $slug): string => sanitize_title($slug),
            $slugs
        ))));

        if ($slugs === []) {
            return self::resolveShopCtaUrl('');
        }

        if (count($slugs) === 1) {
            $term = get_term_by('slug', $slugs[0], 'product_cat');
            if ($term instanceof \WP_Term) {
                $termLink = get_term_link($term);
                if (!is_wp_error($termLink)) {
                    return (string) $termLink;
                }
            }
        }

        $shopUrl = wc_get_page_permalink('shop');
        if (!is_string($shopUrl) || $shopUrl === '') {
            $shopUrl = home_url('/shop/');
        }

        return add_query_arg('petshop_categories', implode(',', $slugs), $shopUrl);
    }

    /**
     * @param list<\WP_Term> $terms
     */
    private static function resolveSeasonalCtaUrl(string $ctaUrl, array $terms): string
    {
        $ctaUrl = trim($ctaUrl);
        if ($ctaUrl !== '') {
            return $ctaUrl;
        }

        $slugs = array_values(array_filter(array_map(
            static fn (\WP_Term $term): string => $term->slug,
            array_filter($terms, static fn ($term): bool => $term instanceof \WP_Term)
        )));

        if (count($slugs) > 1) {
            return self::buildCatalogCategoryFilterUrl($slugs);
        }

        $collections = get_page_by_path('colecoes');
        if ($collections instanceof \WP_Post && $collections->post_status === 'publish') {
            return (string) get_permalink($collections);
        }

        return self::buildCatalogCategoryFilterUrl($slugs);
    }

    private static function resolveCategoryCtaUrl(string $ctaUrl, string $categorySlugs): string
    {
        $ctaUrl = trim($ctaUrl);
        if ($ctaUrl !== '') {
            return $ctaUrl;
        }

        $slugs = array_values(array_filter(array_map('trim', explode(',', $categorySlugs))));

        return self::buildCatalogCategoryFilterUrl($slugs);
    }

    private static function resolveSupportBannerUrl(string $fallbackUrl = ''): string
    {
        $url = trim((string) get_theme_mod('petshop_support_banner_url', ''));
        if ($url !== '') {
            return $url;
        }

        $url = trim((string) get_theme_mod('petshop_footer_whatsapp', ''));
        if ($url !== '') {
            return $url;
        }

        $supportPageId = (int) get_theme_mod('petshop_support_page', 0);
        if ($supportPageId > 0) {
            $permalink = get_permalink($supportPageId);
            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        return trim($fallbackUrl);
    }

    private static function supportBannerContent(int $imageId, string $url): string
    {
        if ($imageId <= 0 || trim($url) === '') {
            return '';
        }

        $imageUrl = wp_get_attachment_image_url($imageId, 'full') ?: '';
        if ($imageUrl === '') {
            return '';
        }

        $alt = trim((string) get_post_meta($imageId, '_wp_attachment_image_alt', true));
        if ($alt === '') {
            $alt = __(
                'Precisa de ajuda para escolher? Fale com nossa equipe no WhatsApp.',
                'petshop-core'
            );
        }

        $altAttribute = esc_attr($alt);
        $url = esc_url($url);
        $imageUrl = esc_url($imageUrl);
        $imageAttrs = wp_json_encode([
            'id' => $imageId,
            'sizeSlug' => 'full',
            'linkDestination' => 'custom',
            'className' => 'petshop-support-banner__image',
            'href' => $url,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<BLOCKS
<!-- wp:group {"className":"petshop-section petshop-support-banner","layout":{"type":"constrained"}} -->
<div class="wp-block-group petshop-section petshop-support-banner"><!-- wp:image {$imageAttrs} -->
<figure class="wp-block-image size-full petshop-support-banner__image"><a href="{$url}"><img src="{$imageUrl}" alt="{$altAttribute}" class="wp-image-{$imageId}"/></a></figure>
<!-- /wp:image --></div>
<!-- /wp:group -->
BLOCKS;
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private static function normalizeLinkedImageBlock(array $block): array
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $href = trim((string) ($attrs['href'] ?? ''));
        if (
            $href === ''
            && preg_match('/<a\s[^>]*href=(["\'])([^"\']+)\1/i', (string) ($block['innerHTML'] ?? ''), $matches)
        ) {
            $href = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if ($href === '') {
            return $block;
        }

        $attrs['linkDestination'] = 'custom';
        $attrs['href'] = $href;
        $block['attrs'] = $attrs;

        return $block;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private static function repairSupportBannerImageBlocks(array $blocks): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (
                ($block['blockName'] ?? '') === 'core/image'
                && str_contains((string) ($block['attrs']['className'] ?? ''), 'petshop-support-banner__image')
            ) {
                $block = self::normalizeLinkedImageBlock($block);
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::repairSupportBannerImageBlocks($block['innerBlocks']);
            }

            $updated[] = $block;
        }

        return $updated;
    }

    private static function applyHomeSchemaTwentyTwo(string $content): string
    {
        if (!str_contains($content, 'petshop-support-banner__image')) {
            return $content;
        }

        return serialize_blocks(self::repairSupportBannerImageBlocks(parse_blocks($content)));
    }

    private static function applyHomeSchemaTwentyThree(string $content): string
    {
        if (
            str_contains($content, 'petshop-reviews-section')
            && !str_contains($content, 'petshop-product-showcase petshop-reviews-section')
        ) {
            return $content;
        }

        $updated = str_replace(
            'petshop-section petshop-product-showcase petshop-reviews-section',
            'petshop-section petshop-reviews-section',
            $content
        );
        $updated = str_replace(
            'petshop-product-showcase__intro">Avaliações reais',
            'petshop-reviews-section__intro">Avaliações reais',
            $updated
        );

        return $updated;
    }

    private static function applyHomeSchemaTwentyFour(string $content, string $shopUrl, int $heroId): string
    {
        return HomeCampaignBlocks::insertCampaignsIfMissing($content, $heroId, $shopUrl);
    }

    public static function renderSupportBanner(): string
    {
        $imageId = (int) get_theme_mod('petshop_support_banner_image', 0);
        if ($imageId <= 0) {
            $imageId = self::ensureSupportBannerAttachment();
        }
        if ($imageId <= 0) {
            return '';
        }

        $url = self::resolveSupportBannerUrl();
        if ($url === '') {
            return '';
        }

        $markup = self::supportBannerContent($imageId, $url);
        if ($markup === '') {
            return '';
        }

        return do_blocks($markup);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderReviewsSection(array $attributes = []): string
    {
        $attributes = shortcode_atts(['limit' => 3], $attributes, 'petshop_reviews_section');
        $reviews = self::renderReviews($attributes);
        if ($reviews === '') {
            return '';
        }

        ob_start();
        echo '<section class="petshop-section petshop-reviews" aria-labelledby="petshop-reviews-heading">';
        echo '<h2 id="petshop-reviews-heading" class="wp-block-heading">';
        echo esc_html__('Quem compra, conta', 'petshop-core');
        echo '</h2>';
        echo '<p>';
        echo esc_html__(
            'Avaliações reais e aprovadas dos produtos aparecem nesta seção.',
            'petshop-core'
        );
        echo '</p>';
        echo $reviews; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</section>';

        return (string) ob_get_clean();
    }

    public static function renderProductAssurance(): void
    {
        $title = (string) get_theme_mod('petshop_product_assurance_title', 'Antes de adicionar ao carrinho');
        $text = (string) get_theme_mod(
            'petshop_product_assurance_text',
            'Confira o conteúdo do pacote, material, aplicação e cuidados descritos nesta página.'
        );

        echo '<div class="petshop-product-assurance" aria-label="' . esc_attr__('Informações de compra', 'petshop-core') . '">';
        echo '<strong>' . esc_html($title) . '</strong>';
        echo '<p>' . esc_html($text) . '</p>';
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function relatedProductArgs(array $args): array
    {
        $args['posts_per_page'] = 4;
        $args['columns'] = 4;

        return $args;
    }

    public static function renderMetaDescription(): void
    {
        if (self::hasSeoPlugin()) {
            return;
        }

        $description = '';
        if (is_product()) {
            $product = wc_get_product(get_queried_object_id());
            $description = $product ? ($product->get_short_description() ?: $product->get_description()) : '';
        } elseif (is_product_category()) {
            $description = term_description(get_queried_object_id(), 'product_cat');
        } elseif (is_shop()) {
            $description = (string) get_theme_mod(
                'petshop_shop_description',
                'Acessórios pet com acabamento cuidadoso para tutores e profissionais.'
            );
        }

        $description = wp_trim_words(wp_strip_all_tags($description), 28, '');
        if ($description !== '') {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }
    }

    public static function renderArchiveCanonical(): void
    {
        if (self::hasSeoPlugin() || (!is_shop() && !is_product_category() && !is_search())) {
            return;
        }

        $url = get_pagenum_link(max(1, (int) get_query_var('paged')));
        if (is_string($url) && $url !== '') {
            echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        }
    }

    private static function ensurePage(string $slug, string $title, string $content): int
    {
        $existing = get_page_by_path($slug);
        if ($existing instanceof \WP_Post) {
            return (int) $existing->ID;
        }

        $pageId = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_name' => $slug,
            'post_title' => $title,
            'post_content' => $content,
            'meta_input' => ['_petshop_managed_page' => 1],
        ], true);

        if (is_wp_error($pageId)) {
            throw new \RuntimeException($pageId->get_error_message());
        }

        return (int) $pageId;
    }

    private static function wishlistPageContent(): string
    {
        return '<!-- wp:paragraph --><p>Produtos que você salvou para comprar depois.</p><!-- /wp:paragraph -->'
            . '<!-- wp:shortcode -->[petshop_wishlist]<!-- /wp:shortcode -->';
    }

    private static function migrateWishlistPage(int $pageId): void
    {
        if ($pageId <= 0) {
            return;
        }

        $page = get_post($pageId);
        if (!$page instanceof \WP_Post) {
            return;
        }

        $legacy = [
            '<!-- wp:heading --><h2 class="wp-block-heading">Lista de desejos</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Produtos que você salvou para comprar depois.</p><!-- /wp:paragraph -->'
            . '<!-- wp:shortcode -->[petshop_wishlist]<!-- /wp:shortcode -->',
            '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">Lista de desejos</h2>' . "\n"
            . '<!-- /wp:heading -->' . "\n\n"
            . '<!-- wp:paragraph -->' . "\n" . '<p>Produtos que você salvou para comprar depois.</p>' . "\n"
            . '<!-- /wp:paragraph -->' . "\n\n"
            . '<!-- wp:shortcode -->' . "\n" . '[petshop_wishlist]' . "\n" . '<!-- /wp:shortcode -->',
        ];

        $current = trim((string) $page->post_content);
        if (!in_array($current, $legacy, true) && !in_array(str_replace(["\r\n", "\r"], "\n", $current), array_map(
            static fn (string $value): string => str_replace(["\r\n", "\r"], "\n", $value),
            $legacy
        ), true)) {
            return;
        }

        $updated = wp_update_post([
            'ID' => $pageId,
            'post_content' => self::wishlistPageContent(),
        ], true);

        if (is_wp_error($updated)) {
            throw new \RuntimeException($updated->get_error_message());
        }
    }

    private static function hasSeoPlugin(): bool
    {
        return defined('WPSEO_VERSION')
            || defined('RANK_MATH_VERSION')
            || defined('AIOSEO_VERSION')
            || defined('SEOPRESS_VERSION');
    }

    private static function upgradeWooCommerceBlocks(): void
    {
        $pages = [
            'woocommerce_cart_page_id' => '<!-- wp:woocommerce/cart /-->',
            'woocommerce_checkout_page_id' => '<!-- wp:woocommerce/checkout /-->',
        ];

        foreach ($pages as $option => $block) {
            $pageId = (int) get_option($option);
            $page = get_post($pageId);
            if (!$page instanceof \WP_Post) {
                continue;
            }

            $content = trim($page->post_content);
            if ($content === '' || in_array($content, ['[woocommerce_cart]', '[woocommerce_checkout]'], true)) {
                wp_update_post(['ID' => $pageId, 'post_content' => $block]);
            }
        }
    }

    private static function managedHomeContentPersisted(int $homeId, string $expected): bool
    {
        $saved = (string) get_post_field('post_content', $homeId);
        if ($saved === $expected) {
            return true;
        }

        $markers = [
            'petshop-benefits__content',
            'petshop-benefits__item',
            'petshop-benefits__copy',
            'petshop-hero',
        ];
        foreach ($markers as $marker) {
            if (str_contains($expected, $marker) && !str_contains($saved, $marker)) {
                return false;
            }
        }

        return $saved !== '';
    }

    private static function migrateManagedHome(
        int $homeId,
        string $shopUrl,
        string $supportUrl,
        int $heroId
    ): void
    {
        if (!(bool) get_post_meta($homeId, '_petshop_managed_page', true)) {
            return;
        }

        $content = (string) get_post_field('post_content', $homeId);
        $originalContent = $content;
        $legacy = '[product_categories number="8" parent="0" hide_empty="0" columns="4" orderby="menu_order"]';
        if (str_contains($content, $legacy)) {
            $content = str_replace($legacy, '[petshop_categories limit="8"]', $content);
        }

        $setSchemaTwo = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 2) {
            $newSections = <<<'BLOCKS'
<!-- wp:group {"className":"petshop-section petshop-section--soft","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section petshop-section--soft">
<!-- wp:heading --><h2 class="wp-block-heading">Novidades</h2><!-- /wp:heading -->
<!-- wp:shortcode -->[products limit="4" columns="4" orderby="date" order="DESC"]<!-- /wp:shortcode --></div><!-- /wp:group -->
<!-- wp:group {"className":"petshop-section petshop-seasonal","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section petshop-seasonal">
<!-- wp:heading --><h2 class="wp-block-heading">Coleção da estação</h2><!-- /wp:heading -->
<!-- wp:shortcode -->[petshop_seasonal_products limit="4" columns="4"]<!-- /wp:shortcode --></div><!-- /wp:group -->
<!-- wp:group {"className":"petshop-section petshop-reviews","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section petshop-reviews">
<!-- wp:heading --><h2 class="wp-block-heading">Quem compra, conta</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Avaliações reais e aprovadas dos produtos aparecem nesta seção.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->[petshop_reviews limit="3"]<!-- /wp:shortcode --></div><!-- /wp:group -->
BLOCKS;
            $anchor = '<!-- wp:group {"className":"petshop-section petshop-support-cta"';
            $position = strpos($content, $anchor);
            $content = $position === false
                ? $content . "\n" . $newSections
                : substr($content, 0, $position) . $newSections . "\n" . substr($content, $position);
            $setSchemaTwo = true;
        }

        $content = str_replace('href="/shop/"', 'href="' . esc_url($shopUrl) . '"', $content);
        $content = str_replace('href="/atendimento/"', 'href="' . esc_url($supportUrl) . '"', $content);
        $kitsHeading = '<h2 class="wp-block-heading">Kits e conjuntos</h2>';
        $kitsHeadingPosition = strpos($content, $kitsHeading);
        if ($kitsHeadingPosition !== false) {
            $beforeKits = substr($content, 0, $kitsHeadingPosition);
            $kitsClassPosition = strrpos($beforeKits, 'petshop-section petshop-section--soft');
            if ($kitsClassPosition !== false) {
                $content = substr_replace(
                    $content,
                    'petshop-section petshop-commerce-banner',
                    $kitsClassPosition,
                    strlen('petshop-section petshop-section--soft')
                );
            }
        }

        $setHeroSchema = false;
        $managedHeroMarkup = '';
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 7) {
            $heroClassPosition = strpos($content, '"className":"petshop-hero"');
            $heroStart = $heroClassPosition === false
                ? false
                : strrpos(substr($content, 0, $heroClassPosition), '<!-- wp:cover ');
            $heroEndMarker = '<!-- /wp:cover -->';
            if ($heroStart === false) {
                $heroStart = strpos($content, '<!-- wp:group {"className":"petshop-hero"');
                $heroEndMarker = '<!-- /wp:group -->';
            }
            $heroEnd = $heroStart === false ? false : strpos($content, $heroEndMarker, $heroStart);

            if ($heroStart === false || $heroEnd === false) {
                // A remoção ou substituição do hero no editor é uma escolha editorial válida.
                $setHeroSchema = true;
            } else {
                $heroEnd += strlen($heroEndMarker);
                $currentHero = substr($content, $heroStart, $heroEnd - $heroStart);
                $currentHash = hash('sha256', $currentHero);
                $knownHash = (string) get_post_meta($homeId, '_petshop_managed_hero_hash', true);
                $knownManaged = $knownHash !== '' && hash_equals($knownHash, $currentHash);
                $legacyManaged = $knownHash === ''
                    && hash_equals(
                        hash('sha256', self::legacyHeroContent($shopUrl, $heroId)),
                        $currentHash
                    );
                $hydratedCurrent = self::hydrateHeroAlt($currentHero);
                $currentManaged = $knownHash === ''
                    && hash_equals(
                        hash('sha256', self::campaignHeroContent($shopUrl, $heroId)),
                        hash('sha256', $hydratedCurrent)
                    );

                if ($legacyManaged) {
                    $managedHeroMarkup = self::heroContent($shopUrl, $heroId);
                    $content = substr($content, 0, $heroStart)
                        . $managedHeroMarkup
                        . substr($content, $heroEnd);
                } elseif ($knownManaged || $currentManaged) {
                    $managedHeroMarkup = $hydratedCurrent;
                    $content = substr($content, 0, $heroStart)
                        . $managedHeroMarkup
                        . substr($content, $heroEnd);
                }
                // Qualquer outro conteúdo é uma customização e deve permanecer intacto.
                $setHeroSchema = true;
            }
        }

        $setSchemaEight = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 8) {
            $heroClassPosition = strpos($content, '"className":"petshop-hero"');
            $heroStart = $heroClassPosition === false
                ? false
                : strrpos(substr($content, 0, $heroClassPosition), '<!-- wp:cover ');
            $heroEnd = $heroStart === false ? false : strpos($content, '<!-- /wp:cover -->', $heroStart);

            if ($heroStart !== false && $heroEnd !== false) {
                $heroEnd += strlen('<!-- /wp:cover -->');
                $currentHero = substr($content, $heroStart, $heroEnd - $heroStart);
                $currentHash = hash('sha256', $currentHero);
                $knownHash = (string) get_post_meta($homeId, '_petshop_managed_hero_hash', true);
                $isManaged = ($managedHeroMarkup !== '' && hash_equals(hash('sha256', $managedHeroMarkup), $currentHash))
                    || ($knownHash !== '' && hash_equals($knownHash, $currentHash))
                    || hash_equals(hash('sha256', self::heroContent($shopUrl, $heroId)), $currentHash)
                    || hash_equals(hash('sha256', self::campaignHeroContent($shopUrl, $heroId)), $currentHash);

                if ($isManaged) {
                    $managedHeroMarkup = self::heroContent($shopUrl, $heroId);
                    $content = substr($content, 0, $heroStart)
                        . $managedHeroMarkup
                        . substr($content, $heroEnd);
                    $heroEnd = $heroStart + strlen($managedHeroMarkup);
                } else {
                    delete_post_meta($homeId, '_petshop_managed_hero_hash');
                }
            } else {
                delete_post_meta($homeId, '_petshop_managed_hero_hash');
            }

            if (!str_contains($content, '"className":"petshop-benefits"')) {
                $benefits = self::benefitsContent();
                $insertAt = $heroEnd === false ? 0 : $heroEnd;
                $content = substr($content, 0, $insertAt)
                    . "\n" . $benefits
                    . substr($content, $insertAt);
            }
            $setSchemaEight = true;
        }

        $setSchemaNine = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 9) {
            $heroClassPosition = strpos($content, '"className":"petshop-hero"');
            $heroStart = $heroClassPosition === false
                ? false
                : strrpos(substr($content, 0, $heroClassPosition), '<!-- wp:cover ');
            $heroEnd = $heroStart === false ? false : strpos($content, '<!-- /wp:cover -->', $heroStart);
            if ($heroStart !== false && $heroEnd !== false) {
                $heroEnd += strlen('<!-- /wp:cover -->');
                $currentHero = substr($content, $heroStart, $heroEnd - $heroStart);
                $currentHash = hash('sha256', $currentHero);
                $knownHash = (string) get_post_meta($homeId, '_petshop_managed_hero_hash', true);
                $isManaged = ($managedHeroMarkup !== '' && hash_equals(hash('sha256', $managedHeroMarkup), $currentHash))
                    || ($knownHash !== '' && hash_equals($knownHash, $currentHash))
                    || hash_equals(hash('sha256', self::invalidInstitutionalHeroContent($shopUrl, $heroId)), $currentHash)
                    || hash_equals(hash('sha256', self::heroContent($shopUrl, $heroId)), $currentHash);
                if ($isManaged) {
                    $managedHeroMarkup = self::heroContent($shopUrl, $heroId);
                    $content = substr($content, 0, $heroStart)
                        . $managedHeroMarkup
                        . substr($content, $heroEnd);
                } else {
                    delete_post_meta($homeId, '_petshop_managed_hero_hash');
                }
            } else {
                delete_post_meta($homeId, '_petshop_managed_hero_hash');
            }
            $setSchemaNine = true;
        }

        $setSchemaTen = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 10) {
            $content = self::applyHomeSchemaTen($content, $supportUrl);
            $setSchemaTen = true;
        }

        $setSchemaEleven = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 11) {
            $content = self::applyHomeSchemaTen($content, $supportUrl);
            $setSchemaEleven = true;
        }

        $setSchemaTwelve = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 12) {
            $content = self::applyHomeSchemaTen($content, $supportUrl);
            $setSchemaTwelve = true;
        }

        $setSchemaThirteen = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 13) {
            $content = self::applyHomeSchemaThirteen($content);
            $setSchemaThirteen = true;
        }

        $setSchemaFourteen = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 14) {
            $content = self::applyHomeSchemaFourteen($content);
            $setSchemaFourteen = true;
        }

        $setSchemaFifteen = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 15) {
            $content = self::applyHomeSchemaFifteen($content);
            $setSchemaFifteen = true;
        }

        $setSchemaSixteen = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 16) {
            $content = self::applyHomeSchemaSixteen($content);
            $setSchemaSixteen = true;
        }

        $setSchemaSeventeen = false;
        if (
            (int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 17
            || !str_contains($content, '[petshop_product_showcase')
        ) {
            $content = self::applyHomeSchemaSeventeen($content);
            $setSchemaSeventeen = true;
        }

        $setSchemaEighteen = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 18) {
            $content = self::applyHomeSchemaEighteen($content);
            $setSchemaEighteen = true;
        }

        $setSchemaNineteen = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 19) {
            $content = self::applyHomeSchemaNineteen($content, $supportUrl);
            $setSchemaNineteen = true;
        }

        $setSchemaTwenty = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 20) {
            $content = self::applyHomeSchemaTwenty($content, $shopUrl);
            $setSchemaTwenty = true;
        }

        $setSchemaTwentyOne = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 21) {
            $content = self::applyHomeSchemaTwentyOne($content);
            $setSchemaTwentyOne = true;
        }

        $setSchemaTwentyTwo = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 22) {
            $content = self::applyHomeSchemaTwentyTwo($content);
            $setSchemaTwentyTwo = true;
        }

        $setSchemaTwentyThree = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 23) {
            $content = self::applyHomeSchemaTwentyThree($content);
            $setSchemaTwentyThree = true;
        }

        $setSchemaTwentyFour = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 24) {
            $content = self::applyHomeSchemaTwentyFour($content, $shopUrl, $heroId);
            $setSchemaTwentyFour = true;
        }

        if ($content !== $originalContent) {
            wp_save_post_revision($homeId);
        }
        $saved = wp_update_post(['ID' => $homeId, 'post_content' => $content], true);
        if (is_wp_error($saved)) {
            throw new \RuntimeException($saved->get_error_message());
        }
        if (!self::managedHomeContentPersisted($homeId, $content)) {
            throw new \RuntimeException('A atualização da Home não foi persistida integralmente.');
        }
        if ($setSchemaTwo) {
            update_post_meta($homeId, '_petshop_home_schema_version', 2);
        }
        if ($setHeroSchema) {
            update_post_meta($homeId, '_petshop_home_schema_version', 7);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 7) {
                throw new \RuntimeException('Não foi possível confirmar o schema da Home.');
            }
            if ($managedHeroMarkup !== '') {
                $managedHash = hash('sha256', $managedHeroMarkup);
                update_post_meta($homeId, '_petshop_managed_hero_hash', $managedHash);
                if ((string) get_post_meta($homeId, '_petshop_managed_hero_hash', true) !== $managedHash) {
                    throw new \RuntimeException('Não foi possível confirmar a assinatura do hero gerenciado.');
                }
            }
        }
        if ($setSchemaEight) {
            update_post_meta($homeId, '_petshop_home_schema_version', 8);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 8) {
                throw new \RuntimeException('Não foi possível confirmar o schema institucional da Home.');
            }
            if ($managedHeroMarkup !== '') {
                update_post_meta($homeId, '_petshop_managed_hero_hash', hash('sha256', $managedHeroMarkup));
            }
        }
        if ($setSchemaNine) {
            update_post_meta($homeId, '_petshop_home_schema_version', 9);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 9) {
                throw new \RuntimeException('Não foi possível confirmar o schema válido do Gutenberg.');
            }
            if ($managedHeroMarkup !== '') {
                update_post_meta($homeId, '_petshop_managed_hero_hash', hash('sha256', $managedHeroMarkup));
            }
        }
        if ($setSchemaTen) {
            update_post_meta($homeId, '_petshop_home_schema_version', 10);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 10) {
                throw new \RuntimeException('Não foi possível confirmar o schema comercial da Home.');
            }
        }
        if ($setSchemaEleven) {
            update_post_meta($homeId, '_petshop_home_schema_version', 11);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 11) {
                throw new \RuntimeException('Não foi possível confirmar a ordem comercial da Home.');
            }
        }
        if ($setSchemaTwelve) {
            update_post_meta($homeId, '_petshop_home_schema_version', 12);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 12) {
                throw new \RuntimeException('Não foi possível confirmar a estrutura comercial da Home.');
            }
        }
        if ($setSchemaThirteen) {
            update_post_meta($homeId, '_petshop_home_schema_version', 13);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 13) {
                throw new \RuntimeException('Não foi possível confirmar a faixa de benefícios da Home.');
            }
        }
        if ($setSchemaFourteen) {
            update_post_meta($homeId, '_petshop_home_schema_version', 14);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 14) {
                throw new \RuntimeException('Não foi possível confirmar o layout da faixa de benefícios.');
            }
        }
        if ($setSchemaFifteen) {
            update_post_meta($homeId, '_petshop_home_schema_version', 15);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 15) {
                throw new \RuntimeException('Não foi possível confirmar os textos da faixa de benefícios.');
            }
        }
        if ($setSchemaSixteen) {
            update_post_meta($homeId, '_petshop_home_schema_version', 16);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 16) {
                throw new \RuntimeException('Não foi possível confirmar a centralização da faixa de benefícios.');
            }
        }
        if ($setSchemaSeventeen) {
            update_post_meta($homeId, '_petshop_home_schema_version', 17);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 17) {
                throw new \RuntimeException('Não foi possível confirmar o layout unificado das vitrines da Home.');
            }
        }
        if ($setSchemaEighteen) {
            update_post_meta($homeId, '_petshop_home_schema_version', 18);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 18) {
                throw new \RuntimeException('Não foi possível confirmar o banner de atendimento da Home.');
            }
        }
        if ($setSchemaNineteen) {
            update_post_meta($homeId, '_petshop_home_schema_version', 19);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 19) {
                throw new \RuntimeException('Não foi possível confirmar o banner editável de atendimento da Home.');
            }
        }
        if ($setSchemaTwenty) {
            update_post_meta($homeId, '_petshop_home_schema_version', 20);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 20) {
                throw new \RuntimeException('Não foi possível confirmar os cabeçalhos editáveis das vitrines da Home.');
            }
        }
        if ($setSchemaTwentyOne) {
            update_post_meta($homeId, '_petshop_home_schema_version', 21);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 21) {
                throw new \RuntimeException('Não foi possível confirmar a seção editável de avaliações da Home.');
            }
        }
        if ($setSchemaTwentyTwo) {
            update_post_meta($homeId, '_petshop_home_schema_version', 22);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 22) {
                throw new \RuntimeException('Não foi possível confirmar o bloco de imagem do banner de atendimento.');
            }
        }
        if ($setSchemaTwentyThree) {
            update_post_meta($homeId, '_petshop_home_schema_version', 23);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 23) {
                throw new \RuntimeException('Não foi possível confirmar a seção de avaliações da Home.');
            }
        }
        if ($setSchemaTwentyFour) {
            update_post_meta($homeId, '_petshop_home_schema_version', 24);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 24) {
                throw new \RuntimeException('Não foi possível confirmar a faixa de banners de campanha da Home.');
            }
        }
    }

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
            set_theme_mod('petshop_benefit_text', 'Acabamento cuidadoso para tutores e profissionais');
        }
        if (get_theme_mod('petshop_support_label', null) === null) {
            set_theme_mod('petshop_support_label', 'Atendimento');
        }
        if (get_theme_mod('petshop_account_label', null) === null) {
            set_theme_mod('petshop_account_label', 'Minha conta');
        }
        if (get_theme_mod('petshop_wishlist_label', null) === null) {
            set_theme_mod('petshop_wishlist_label', 'Lista de desejos');
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
                    throw new \RuntimeException('Categoria ausente para o menu comercial: ' . $target['slug']);
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
        if (!is_array($items) || count($items) !== count($targets)) {
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

    private static function legacyHeroContent(string $shopUrl, int $heroId): string
    {
        $heroUrl = esc_url(wp_get_attachment_image_url($heroId, 'full') ?: '');
        $shopUrl = esc_url($shopUrl);

        return <<<BLOCKS
<!-- wp:cover {"url":"{$heroUrl}","id":{$heroId},"dimRatio":15,"overlayColor":"white","minHeight":440,"minHeightUnit":"px","contentPosition":"center left","className":"petshop-hero"} -->
<div class="wp-block-cover has-custom-content-position is-position-center-left petshop-hero" style="min-height:440px"><span aria-hidden="true" class="wp-block-cover__background has-white-background-color has-background-dim-15 has-background-dim"></span><img class="wp-block-cover__image-background wp-image-{$heroId}" alt="" src="{$heroUrl}" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:group {"className":"petshop-hero__copy","layout":{"type":"default"}} --><div class="wp-block-group petshop-hero__copy">
<!-- wp:paragraph {"className":"petshop-eyebrow"} --><p class="petshop-eyebrow">Acessórios para banho e tosa</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">O acabamento que faz seu cliente lembrar</h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Laços, bandanas, gravatas e finalizações para valorizar cada atendimento.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$shopUrl}">Comprar acessórios</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div><!-- /wp:group --></div></div>
<!-- /wp:cover -->
BLOCKS;
    }

    private static function hydrateHeroAlt(string $heroMarkup): string
    {
        if (!preg_match('/wp-image-(\d+)/', $heroMarkup, $matches)) {
            return $heroMarkup;
        }
        $openingEnd = strpos($heroMarkup, '-->');
        $opening = $openingEnd === false ? '' : substr($heroMarkup, 0, $openingEnd);
        $alt = '';
        if (preg_match('/"alt":"([^"]+)"/u', $opening, $altMatch)) {
            $decoded = json_decode('"' . $altMatch[1] . '"', true);
            $alt = is_string($decoded) ? $decoded : '';
        }
        if (
            $alt === ''
            && preg_match(
                '/<img\b[^>]*\bclass="[^"]*wp-block-cover__image-background[^"]*"[^>]*\balt="([^"]+)"/',
                $heroMarkup,
                $altMatch
            )
        ) {
            $alt = html_entity_decode($altMatch[1], ENT_QUOTES);
        }
        if ($alt === '') {
            $alt = (string) get_post_meta((int) $matches[1], '_wp_attachment_image_alt', true);
        }
        if ($alt === '') {
            return $heroMarkup;
        }

        if ($openingEnd !== false) {
            if (str_contains($opening, '"alt":""')) {
                $opening = str_replace(
                    '"alt":""',
                    '"alt":' . wp_json_encode($alt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $opening
                );
                $heroMarkup = $opening . substr($heroMarkup, $openingEnd);
            } elseif (!str_contains($opening, '"alt":')) {
                $opening = str_replace(
                    '"dimRatio":',
                    '"alt":' . wp_json_encode($alt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ',"dimRatio":',
                    $opening
                );
                $heroMarkup = $opening . substr($heroMarkup, $openingEnd);
            }
        }

        if (
            preg_match(
                '/<img\b[^>]*\bclass="[^"]*wp-block-cover__image-background[^"]*"[^>]*\balt="[^"]+"/',
                $heroMarkup
            )
        ) {
            return $heroMarkup;
        }

        return (string) preg_replace(
            '/(<img\b[^>]*\bclass="[^"]*wp-block-cover__image-background[^"]*"[^>]*\balt=")[^"]*(")/',
            '$1' . esc_attr($alt) . '$2',
            $heroMarkup,
            1
        );
    }

    private static function campaignHeroContent(string $shopUrl, int $heroId): string
    {
        $heroUrl = wp_get_attachment_image_url($heroId, 'full') ?: '';
        $heroAlt = (string) get_post_meta($heroId, '_wp_attachment_image_alt', true);
        $heroAltJson = wp_json_encode($heroAlt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $heroAltAttribute = esc_attr($heroAlt);
        $destination = get_term_link('dia-dos-pais', 'product_cat');
        $destination = is_wp_error($destination) ? $shopUrl : $destination;
        $destination = esc_url($destination);
        $heroUrl = esc_url($heroUrl);

        return <<<BLOCKS
<!-- wp:cover {"url":"{$heroUrl}","id":{$heroId},"alt":{$heroAltJson},"dimRatio":15,"overlayColor":"white","minHeight":440,"minHeightUnit":"px","contentPosition":"center left","align":"full","className":"petshop-hero"} -->
<div class="wp-block-cover alignfull has-custom-content-position is-position-center-left petshop-hero" style="min-height:440px"><span aria-hidden="true" class="wp-block-cover__background has-white-background-color has-background-dim-15 has-background-dim"></span><img class="wp-block-cover__image-background wp-image-{$heroId}" alt="{$heroAltAttribute}" src="{$heroUrl}" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:group {"className":"petshop-hero__copy","layout":{"type":"default"}} --><div class="wp-block-group petshop-hero__copy">
<!-- wp:paragraph {"className":"petshop-eyebrow"} --><p class="petshop-eyebrow">Coleção Dia dos Pais</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">O detalhe que <span class="petshop-hero__accent">fideliza seu cliente</span></h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Gravatas, laços e adesivos temáticos que vão transformar cada atendimento em uma experiência especial e encantar o tutor.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$destination}">Ver a coleção de Dia dos Pais</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
<!-- wp:paragraph {"className":"petshop-hero__note"} --><p class="petshop-hero__note">Frete grátis para todo o Brasil acima de R$ 299</p><!-- /wp:paragraph -->
</div><!-- /wp:group --></div></div>
<!-- /wp:cover -->
BLOCKS;
    }

    private static function invalidInstitutionalHeroContent(string $shopUrl, int $heroId): string
    {
        $heroUrl = esc_url(wp_get_attachment_image_url($heroId, 'full') ?: '');
        $heroAlt = (string) get_post_meta($heroId, '_wp_attachment_image_alt', true);
        $heroAltJson = wp_json_encode($heroAlt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $heroAltAttribute = esc_attr($heroAlt);
        $featuredUrl = esc_url($shopUrl);
        $kitsUrl = get_term_link('conjuntos', 'product_cat');
        $kitsUrl = esc_url(is_wp_error($kitsUrl) ? $shopUrl : $kitsUrl);

        return <<<BLOCKS
<!-- wp:cover {"url":"{$heroUrl}","id":{$heroId},"alt":{$heroAltJson},"dimRatio":15,"overlayColor":"white","minHeight":440,"minHeightUnit":"px","contentPosition":"center left","align":"full","className":"petshop-hero"} -->
<div class="wp-block-cover alignfull has-custom-content-position is-position-center-left petshop-hero" style="min-height:440px"><span aria-hidden="true" class="wp-block-cover__background has-white-background-color has-background-dim-15 has-background-dim"></span><img class="wp-block-cover__image-background wp-image-{$heroId}" alt="{$heroAltAttribute}" src="{$heroUrl}" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:group {"className":"petshop-hero__copy","layout":{"type":"default"}} --><div class="wp-block-group petshop-hero__copy">
<!-- wp:paragraph {"className":"petshop-eyebrow"} --><p class="petshop-eyebrow">Acessórios para banho e tosa</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Acessórios que valorizam cada banho e tosa</h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Bandanas, laços, gravatas e adesivos com acabamento profissional e opções para diferentes volumes.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$featuredUrl}">Ver destaques da loja</a></div><!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="{$kitsUrl}">Conhecer kits econômicos</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div><!-- /wp:group --></div></div>
<!-- /wp:cover -->
BLOCKS;
    }

    private static function heroContent(string $shopUrl, int $heroId): string
    {
        $heroUrl = esc_url(wp_get_attachment_image_url($heroId, 'full') ?: '');
        $heroAlt = (string) get_post_meta($heroId, '_wp_attachment_image_alt', true);
        $heroAltJson = wp_json_encode($heroAlt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $heroAltAttribute = esc_attr($heroAlt);
        $featuredUrl = esc_url($shopUrl);
        $kitsUrl = get_term_link('conjuntos', 'product_cat');
        $kitsUrl = esc_url(is_wp_error($kitsUrl) ? $shopUrl : $kitsUrl);

        return <<<BLOCKS
<!-- wp:cover {"url":"{$heroUrl}","id":{$heroId},"alt":{$heroAltJson},"dimRatio":0,"minHeight":480,"minHeightUnit":"px","contentPosition":"center left","align":"full","className":"petshop-hero"} -->
<div class="wp-block-cover alignfull has-custom-content-position is-position-center-left petshop-hero" style="min-height:480px"><img class="wp-block-cover__image-background wp-image-{$heroId}" alt="{$heroAltAttribute}" src="{$heroUrl}" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:group {"className":"petshop-hero__copy","layout":{"type":"default"}} --><div class="wp-block-group petshop-hero__copy">
<!-- wp:paragraph {"className":"petshop-eyebrow"} --><p class="petshop-eyebrow">Acessórios para banho e tosa</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Acessórios que valorizam cada banho e tosa</h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Bandanas, laços, gravatas e adesivos com acabamento profissional e opções para diferentes volumes.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$featuredUrl}">Ver destaques da loja</a></div><!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="{$kitsUrl}">Conhecer kits econômicos</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div><!-- /wp:group --></div></div>
<!-- /wp:cover -->
BLOCKS;
    }

    private static function benefitsContent(array $overrides = []): string
    {
        $items = [
            [
                'class' => 'petshop-benefits__item--delivery',
                'title' => __('Pronta entrega', 'petshop-core'),
                'detail' => __('Produtos à pronta entrega', 'petshop-core'),
            ],
            [
                'class' => 'petshop-benefits__item--volume',
                'title' => __('Condições para volume', 'petshop-core'),
                'detail' => __('Preços especiais para pet shops', 'petshop-core'),
            ],
            [
                'class' => 'petshop-benefits__item--shipping',
                'title' => __('Frete para todo o Brasil', 'petshop-core'),
                'detail' => __('Envio rápido e seguro', 'petshop-core'),
            ],
        ];

        foreach ($overrides as $index => $override) {
            if (!isset($items[$index]) || !is_array($override)) {
                continue;
            }
            if (isset($override['title']) && trim((string) $override['title']) !== '') {
                $items[$index]['title'] = (string) $override['title'];
            }
            if (isset($override['detail']) && trim((string) $override['detail']) !== '') {
                $items[$index]['detail'] = (string) $override['detail'];
            }
        }

        $itemsMarkup = '';
        foreach ($items as $item) {
            $class = esc_attr($item['class']);
            $title = esc_html($item['title']);
            $detail = esc_html($item['detail']);
            $itemsMarkup .= <<<ITEM
<!-- wp:group {"className":"petshop-benefits__item {$class}","layout":{"type":"default"}} -->
<div class="wp-block-group petshop-benefits__item {$class}">
<!-- wp:group {"className":"petshop-benefits__content","layout":{"type":"default"}} -->
<div class="wp-block-group petshop-benefits__content">
<!-- wp:image {"className":"petshop-benefits__icon","linkDestination":"none"} -->
<figure class="wp-block-image petshop-benefits__icon"><img alt="" /></figure>
<!-- /wp:image -->
<!-- wp:group {"className":"petshop-benefits__copy","layout":{"type":"default"}} -->
<div class="wp-block-group petshop-benefits__copy">
<!-- wp:paragraph {"className":"petshop-benefits__title"} --><p class="petshop-benefits__title"><strong>{$title}</strong></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"petshop-benefits__detail"} --><p class="petshop-benefits__detail">{$detail}</p><!-- /wp:paragraph -->
</div><!-- /wp:group -->
</div><!-- /wp:group -->
</div><!-- /wp:group -->

ITEM;
        }

        return <<<BLOCKS
<!-- wp:group {"align":"full","className":"petshop-benefits","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull petshop-benefits">
<!-- wp:group {"className":"petshop-benefits__inner","layout":{"type":"default"}} -->
<div class="wp-block-group petshop-benefits__inner">
{$itemsMarkup}</div><!-- /wp:group --></div><!-- /wp:group -->
BLOCKS;
    }

    private static function applyHomeSchemaThirteen(string $content): string
    {
        if (str_contains($content, 'petshop-benefits__item')) {
            return $content;
        }

        if (!str_contains($content, 'petshop-benefits')) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceBenefitsBlocks($blocks);

        return serialize_blocks($blocks);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private static function replaceBenefitsBlocks(array $blocks): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (
                ($block['blockName'] ?? '') === 'core/group'
                && str_contains((string) ($block['attrs']['className'] ?? ''), 'petshop-benefits')
                && ($block['attrs']['align'] ?? '') === 'full'
            ) {
                $replacement = parse_blocks(trim(self::benefitsContent(self::benefitsOverridesFromBlock($block))));
                foreach ($replacement as $replacementBlock) {
                    if (($replacementBlock['blockName'] ?? '') !== null) {
                        $updated[] = $replacementBlock;
                    }
                }
                continue;
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceBenefitsBlocks($block['innerBlocks']);
            }

            $updated[] = $block;
        }

        return $updated;
    }

    private static function applyHomeSchemaTwentyOne(string $content): string
    {
        if (str_contains($content, 'petshop-reviews-section')) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceLegacyReviewsShortcodeBlocks($blocks);

        return serialize_blocks($blocks);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private static function replaceLegacyReviewsShortcodeBlocks(array $blocks): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/shortcode') {
                $shortcode = trim(strip_tags((string) ($block['innerHTML'] ?? '')));
                if (preg_match('/^\[petshop_reviews_section\b/', $shortcode)) {
                    $attributes = shortcode_parse_atts(
                        (string) preg_replace('/^\[petshop_reviews_section\s*|\]$/', '', $shortcode)
                    ) ?: [];
                    $limit = max(1, absint($attributes['limit'] ?? 3));
                    foreach (parse_blocks(self::reviewsSectionGutenbergMarkup($limit)) as $parsedBlock) {
                        $updated[] = $parsedBlock;
                    }
                    continue;
                }
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceLegacyReviewsShortcodeBlocks($block['innerBlocks']);
            }

            $updated[] = $block;
        }

        return $updated;
    }

    private static function applyHomeSchemaTwenty(string $content, string $shopUrl): string
    {
        if (self::homeUsesEditableShowcaseHeads($content)) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceLegacyShowcaseShortcodeBlocks($blocks, $shopUrl);

        return serialize_blocks($blocks);
    }

    private static function homeUsesEditableShowcaseHeads(string $content): bool
    {
        if (!str_contains($content, 'petshop-section-head')) {
            return false;
        }

        return !preg_match(
            '/\[petshop_(featured_products|kits_section|seasonal_products|product_showcase)(?!_grid)\b/',
            $content
        );
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private static function replaceLegacyShowcaseShortcodeBlocks(array $blocks, string $shopUrl): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/shortcode') {
                $shortcode = trim(strip_tags((string) ($block['innerHTML'] ?? '')));
                $replacement = self::editableShowcaseMarkupFromShortcode($shortcode, $shopUrl);
                if ($replacement !== null) {
                    foreach (parse_blocks($replacement) as $parsedBlock) {
                        $updated[] = $parsedBlock;
                    }
                    continue;
                }
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceLegacyShowcaseShortcodeBlocks(
                    $block['innerBlocks'],
                    $shopUrl
                );
            }

            $updated[] = $block;
        }

        return $updated;
    }

    private static function editableShowcaseMarkupFromShortcode(string $shortcode, string $shopUrl): ?string
    {
        if (preg_match('/^\[petshop_featured_products\b/', $shortcode)) {
            $attributes = shortcode_parse_atts(
                (string) preg_replace('/^\[petshop_featured_products\s*|\]$/', '', $shortcode)
            ) ?: [];
            $fallbackTitle = trim((string) ($attributes['fallback_title'] ?? ''));
            if ($fallbackTitle === '') {
                $fallbackTitle = (string) get_theme_mod(
                    'petshop_featured_section_title',
                    __('Destaques da loja', 'petshop-core')
                );
            }
            $title = self::hasCatalogSales()
                ? __('Mais vendidos', 'petshop-core')
                : $fallbackTitle;
            $cta = (string) ($attributes['cta'] ?? __('Ver todos →', 'petshop-core'));
            $ctaUrl = self::resolveShopCtaUrl((string) ($attributes['cta_url'] ?? ''));
            $limit = max(1, absint($attributes['limit'] ?? 4));
            $columns = max(1, absint($attributes['columns'] ?? 4));

            return self::showcaseSectionGutenbergMarkup(
                'petshop-featured-section',
                'petshop-featured-heading',
                $title,
                $cta,
                $ctaUrl,
                '',
                sprintf('[petshop_featured_products_grid limit="%d" columns="%d"]', $limit, $columns)
            );
        }

        if (preg_match('/^\[petshop_kits_section\b/', $shortcode)) {
            $attributes = shortcode_parse_atts(
                (string) preg_replace('/^\[petshop_kits_section\s*|\]$/', '', $shortcode)
            ) ?: [];
            $category = (string) ($attributes['category'] ?? 'conjuntos');
            $term = get_term_by('slug', $category, 'product_cat');
            if (!$term instanceof \WP_Term || (int) $term->count <= 0) {
                return null;
            }
            $ctaUrl = get_term_link($term);
            if (is_wp_error($ctaUrl)) {
                $ctaUrl = $shopUrl;
            }
            $limit = max(1, absint($attributes['limit'] ?? 4));
            $columns = max(1, absint($attributes['columns'] ?? 4));

            return self::showcaseSectionGutenbergMarkup(
                'petshop-kits-section petshop-section--soft',
                'petshop-kits-heading',
                (string) ($attributes['title'] ?? __('Economize comprando kits', 'petshop-core')),
                (string) ($attributes['cta'] ?? __('Ver todos →', 'petshop-core')),
                (string) $ctaUrl,
                (string) ($attributes['intro'] ?? __(
                    'Escolhas práticas para compor o visual e facilitar a rotina profissional.',
                    'petshop-core'
                )),
                sprintf(
                    '[petshop_kits_section_grid limit="%d" columns="%d" category="%s"]',
                    $limit,
                    $columns,
                    esc_attr($category)
                )
            );
        }

        if (preg_match('/^\[petshop_seasonal_products\b/', $shortcode)) {
            $attributes = shortcode_parse_atts(
                (string) preg_replace('/^\[petshop_seasonal_products\s*|\]$/', '', $shortcode)
            ) ?: [];
            $terms = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => true,
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => 'petshop_seasonal', 'value' => '1'],
                    ['key' => 'petshop_visible_in_menu', 'value' => '1'],
                ],
            ]);
            if (is_wp_error($terms) || $terms === []) {
                return null;
            }
            $limit = max(1, absint($attributes['limit'] ?? 4));
            $columns = max(1, absint($attributes['columns'] ?? 4));

            return self::showcaseSectionGutenbergMarkup(
                'petshop-seasonal-section',
                'petshop-seasonal-heading',
                (string) ($attributes['title'] ?? __('Coleção da estação', 'petshop-core')),
                (string) ($attributes['cta'] ?? __('Ver todos →', 'petshop-core')),
                self::resolveSeasonalCtaUrl((string) ($attributes['cta_url'] ?? ''), $terms),
                '',
                sprintf('[petshop_seasonal_products_grid limit="%d" columns="%d"]', $limit, $columns)
            );
        }

        if (preg_match('/^\[petshop_product_showcase\b/', $shortcode)) {
            $attributes = shortcode_parse_atts(
                (string) preg_replace('/^\[petshop_product_showcase\s*|\]$/', '', $shortcode)
            ) ?: [];
            $title = trim((string) ($attributes['title'] ?? ''));
            if ($title === '') {
                return null;
            }
            $category = trim((string) ($attributes['category'] ?? ''));
            $limit = max(1, absint($attributes['limit'] ?? 4));
            $columns = max(1, absint($attributes['columns'] ?? 4));
            $orderby = esc_attr((string) ($attributes['orderby'] ?? 'date'));
            $order = esc_attr((string) ($attributes['order'] ?? 'DESC'));
            $gridShortcode = sprintf(
                '[petshop_product_showcase_grid limit="%d" columns="%d" category="%s" orderby="%s" order="%s"]',
                $limit,
                $columns,
                esc_attr($category),
                $orderby,
                $order
            );

            return self::showcaseSectionGutenbergMarkup(
                (string) ($attributes['class'] ?? 'petshop-professional-section'),
                sanitize_title($title) . '-heading',
                $title,
                (string) ($attributes['cta'] ?? __('Ver todos →', 'petshop-core')),
                self::resolveCategoryCtaUrl((string) ($attributes['cta_url'] ?? ''), $category),
                (string) ($attributes['intro'] ?? ''),
                $gridShortcode
            );
        }

        return null;
    }

    private static function applyHomeSchemaNineteen(string $content, string $supportUrl): string
    {
        if (
            str_contains($content, 'petshop-support-banner')
            && str_contains($content, 'wp-block-image')
            && !str_contains($content, '[petshop_support_banner]')
        ) {
            return $content;
        }

        $imageId = (int) get_theme_mod('petshop_support_banner_image', 0);
        if ($imageId <= 0) {
            $imageId = self::ensureSupportBannerAttachment();
        }

        $url = self::resolveSupportBannerUrl($supportUrl);
        $banner = self::supportBannerContent($imageId, $url);
        if ($banner === '') {
            return $content;
        }

        $replacementBlocks = parse_blocks($banner);
        if ($replacementBlocks === []) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceEditableSupportBannerBlocks($blocks, $replacementBlocks[0]);

        return serialize_blocks($blocks);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<string, mixed> $replacement
     * @return array<int, array<string, mixed>>
     */
    private static function replaceEditableSupportBannerBlocks(array $blocks, array $replacement): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/shortcode') {
                $shortcode = trim(strip_tags((string) ($block['innerHTML'] ?? '')));
                if ($shortcode === '[petshop_support_banner]') {
                    $updated[] = $replacement;
                    continue;
                }
            }

            if (($block['blockName'] ?? '') === 'core/group') {
                $className = (string) ($block['attrs']['className'] ?? '');
                if (
                    str_contains($className, 'petshop-support-cta')
                    || (
                        str_contains($className, 'petshop-support-banner')
                        && !self::blockContainsImage($block)
                    )
                ) {
                    $updated[] = $replacement;
                    continue;
                }
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceEditableSupportBannerBlocks($block['innerBlocks'], $replacement);
            }

            $updated[] = $block;
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function blockContainsImage(array $block): bool
    {
        if (($block['blockName'] ?? '') === 'core/image') {
            return true;
        }

        foreach ($block['innerBlocks'] ?? [] as $innerBlock) {
            if (is_array($innerBlock) && self::blockContainsImage($innerBlock)) {
                return true;
            }
        }

        return false;
    }

    private static function applyHomeSchemaEighteen(string $content): string
    {
        if (str_contains($content, '[petshop_support_banner]')) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceSupportBannerBlock($blocks);

        return serialize_blocks($blocks);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private static function replaceSupportBannerBlock(array $blocks): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/group') {
                $className = (string) ($block['attrs']['className'] ?? '');
                if (str_contains($className, 'petshop-support-cta')) {
                    $updated[] = self::shortcodeBlock('[petshop_support_banner]');
                    continue;
                }
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceSupportBannerBlock($block['innerBlocks']);
            }

            $updated[] = $block;
        }

        return $updated;
    }

    private static function applyHomeSchemaSeventeen(string $content): string
    {
        if (str_contains($content, '[petshop_product_showcase')) {
            return $content;
        }

        $content = (string) preg_replace_callback(
            '/\[petshop_featured_products(?!\s[^\]]*cta=)([^\]]*)\]/',
            static fn (array $matches): string => '[petshop_featured_products' . $matches[1] . ' cta="Ver todos →"]',
            $content
        );
        $content = (string) preg_replace_callback(
            '/\[petshop_kits_section(?!\s[^\]]*cta=)([^\]]*)\]/',
            static fn (array $matches): string => '[petshop_kits_section' . $matches[1] . ' cta="Ver todos →"]',
            $content
        );

        $blocks = parse_blocks($content);
        $blocks = self::replaceHomeShowcaseBlocks($blocks);

        return serialize_blocks($blocks);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private static function replaceHomeShowcaseBlocks(array $blocks): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/group') {
                $className = (string) ($block['attrs']['className'] ?? '');
                if (str_contains($className, 'petshop-seasonal')) {
                    $updated[] = self::shortcodeBlock(
                        '[petshop_seasonal_products limit="4" columns="4" title="Coleção da estação" cta="Ver todos →"]'
                    );
                    continue;
                }
                if (str_contains($className, 'petshop-professional')) {
                    $updated[] = self::shortcodeBlock(
                        '[petshop_product_showcase limit="4" columns="4" title="Seleção para banho e tosa" intro="Modelos pensados para finalização profissional, apresentação de kits e recompra recorrente." cta="Ver todos →" category="adesivos,gravatas,lacos" orderby="date"]'
                    );
                    continue;
                }
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceHomeShowcaseBlocks($block['innerBlocks']);
            }

            $updated[] = $block;
        }

        return $updated;
    }

    private static function shortcodeBlock(string $shortcode): array
    {
        return [
            'blockName' => 'core/shortcode',
            'attrs' => [],
            'innerBlocks' => [],
            'innerHTML' => $shortcode,
            'innerContent' => [$shortcode],
        ];
    }

    private static function applyHomeSchemaSixteen(string $content): string
    {
        if (!str_contains($content, 'petshop-benefits__item') || str_contains($content, 'petshop-benefits__content')) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceBenefitsBlocks($blocks);

        return serialize_blocks($blocks);
    }

    private static function applyHomeSchemaFifteen(string $content): string
    {
        if (!str_contains($content, 'petshop-benefits')) {
            return $content;
        }

        return str_replace(
            '<strong>Envio para todo o Brasil</strong>',
            '<strong>Frete para todo o Brasil</strong>',
            $content
        );
    }

    private static function applyHomeSchemaFourteen(string $content): string
    {
        if (!str_contains($content, 'petshop-benefits__item') || str_contains($content, 'petshop-benefits__copy')) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceBenefitsBlocks($blocks);

        return serialize_blocks($blocks);
    }

    /**
     * @param array<string, mixed> $benefitsBlock
     * @return list<array{title: string, detail?: string}>
     */
    private static function benefitsOverridesFromBlock(array $benefitsBlock): array
    {
        if (str_contains((string) ($benefitsBlock['attrs']['className'] ?? ''), 'petshop-benefits__item')) {
            return self::benefitItemOverrides($benefitsBlock);
        }

        foreach ($benefitsBlock['innerBlocks'] ?? [] as $innerBlock) {
            if (str_contains((string) ($innerBlock['attrs']['className'] ?? ''), 'petshop-benefits__inner')) {
                return self::benefitItemOverrides($innerBlock);
            }
        }

        return self::legacyBenefitOverrides($benefitsBlock);
    }

    /**
     * @param array<string, mixed> $containerBlock
     * @return list<array{title: string, detail?: string}>
     */
    private static function benefitItemOverrides(array $containerBlock): array
    {
        $overrides = [];

        foreach ($containerBlock['innerBlocks'] ?? [] as $block) {
            if (($block['blockName'] ?? '') !== 'core/group') {
                continue;
            }
            if (!str_contains((string) ($block['attrs']['className'] ?? ''), 'petshop-benefits__item')) {
                continue;
            }

            $title = '';
            $detail = '';
            $collect = static function (array $blocks) use (&$collect, &$title, &$detail): void {
                foreach ($blocks as $inner) {
                    $className = (string) ($inner['attrs']['className'] ?? '');
                    if (($inner['blockName'] ?? '') === 'core/paragraph' && str_contains($className, 'petshop-benefits__title')) {
                        $title = trim(wp_strip_all_tags((string) ($inner['innerHTML'] ?? '')));
                    }
                    if (($inner['blockName'] ?? '') === 'core/paragraph' && str_contains($className, 'petshop-benefits__detail')) {
                        $detail = trim(wp_strip_all_tags((string) ($inner['innerHTML'] ?? '')));
                    }
                    if (!empty($inner['innerBlocks'])) {
                        $collect($inner['innerBlocks']);
                    }
                }
            };
            $collect($block['innerBlocks'] ?? []);

            if ($title !== '') {
                $override = ['title' => $title];
                if ($detail !== '') {
                    $override['detail'] = $detail;
                }
                $overrides[] = $override;
            }
        }

        return $overrides;
    }

    /**
     * @param array<string, mixed> $benefitsBlock
     * @return list<array{title: string, detail?: string}>
     */
    private static function legacyBenefitOverrides(array $benefitsBlock): array
    {
        $lines = [];
        $collect = static function (array $blocks) use (&$collect, &$lines): void {
            foreach ($blocks as $block) {
                if (($block['blockName'] ?? '') === 'core/paragraph') {
                    $text = trim(wp_strip_all_tags((string) ($block['innerHTML'] ?? '')));
                    if ($text !== '') {
                        $lines[] = $text;
                    }
                }
                if (!empty($block['innerBlocks'])) {
                    $collect($block['innerBlocks']);
                }
            }
        };
        $collect($benefitsBlock['innerBlocks'] ?? []);

        $overrides = [];
        foreach (array_slice($lines, 0, 3) as $line) {
            $overrides[] = ['title' => $line];
        }

        return $overrides;
    }

    private static function applyHomeSchemaTen(string $content, string $supportUrl): string
    {
        $heroEnd = strpos($content, '<!-- /wp:cover -->');
        if ($heroEnd === false) {
            return $content;
        }

        $heroEnd += strlen('<!-- /wp:cover -->');

        return trim(substr($content, 0, $heroEnd))
            . "\n"
            . self::benefitsContent()
            . "\n"
            . self::managedHomeTail($supportUrl);
    }

    private static function managedHomeTail(string $supportUrl): string
    {
        $shopUrl = self::resolveShopCtaUrl('');
        $featuredTitle = self::hasCatalogSales()
            ? __('Mais vendidos', 'petshop-core')
            : (string) get_theme_mod(
                'petshop_featured_section_title',
                __('Destaques da loja', 'petshop-core')
            );
        $kitsTerm = get_term_by('slug', 'conjuntos', 'product_cat');
        $kitsUrl = $kitsTerm instanceof \WP_Term ? get_term_link($kitsTerm) : $shopUrl;
        if (is_wp_error($kitsUrl)) {
            $kitsUrl = $shopUrl;
        }
        $seasonalTerms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'petshop_seasonal', 'value' => '1'],
                ['key' => 'petshop_visible_in_menu', 'value' => '1'],
            ],
        ]);
        $seasonalTerms = is_wp_error($seasonalTerms) ? [] : $seasonalTerms;
        $seasonalUrl = self::resolveSeasonalCtaUrl('', $seasonalTerms);
        $professionalUrl = self::resolveCategoryCtaUrl('', 'adesivos,gravatas,lacos');
        $imageId = self::ensureSupportBannerAttachment();
        $banner = self::supportBannerContent($imageId, self::resolveSupportBannerUrl($supportUrl));

        $featured = self::showcaseSectionGutenbergMarkup(
            'petshop-featured-section',
            'petshop-featured-heading',
            $featuredTitle,
            __('Ver todos →', 'petshop-core'),
            $shopUrl,
            '',
            '[petshop_featured_products_grid limit="4" columns="4"]'
        );
        $kits = self::showcaseSectionGutenbergMarkup(
            'petshop-kits-section petshop-section--soft',
            'petshop-kits-heading',
            __('Economize comprando kits', 'petshop-core'),
            __('Ver todos →', 'petshop-core'),
            (string) $kitsUrl,
            __('Escolhas práticas para compor o visual e facilitar a rotina profissional.', 'petshop-core'),
            '[petshop_kits_section_grid limit="4" columns="4" category="conjuntos"]'
        );
        $seasonal = self::showcaseSectionGutenbergMarkup(
            'petshop-seasonal-section',
            'petshop-seasonal-heading',
            __('Coleção da estação', 'petshop-core'),
            __('Ver todos →', 'petshop-core'),
            $seasonalUrl,
            '',
            '[petshop_seasonal_products_grid limit="4" columns="4"]'
        );
        $professional = self::showcaseSectionGutenbergMarkup(
            'petshop-professional-section',
            'selecao-para-banho-e-tosa-heading',
            __('Seleção para banho e tosa', 'petshop-core'),
            __('Ver todos →', 'petshop-core'),
            $professionalUrl,
            __(
                'Modelos pensados para finalização profissional, apresentação de kits e recompra recorrente.',
                'petshop-core'
            ),
            '[petshop_product_showcase_grid limit="4" columns="4" category="adesivos,gravatas,lacos" orderby="date"]'
        );
        $reviews = self::reviewsSectionGutenbergMarkup(3);

        return <<<BLOCKS
<!-- wp:group {"className":"petshop-section","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section">
<!-- wp:heading --><h2 class="wp-block-heading">Compre por categoria</h2><!-- /wp:heading -->
<!-- wp:shortcode -->[petshop_categories limit="8"]<!-- /wp:shortcode --></div><!-- /wp:group -->
{$featured}
{$kits}
{$seasonal}
{$professional}
{$reviews}
{$banner}
BLOCKS;
    }

    private static function homeContent(string $shopUrl, string $supportUrl, int $heroId): string
    {
        $campaigns = HomeCampaignBlocks::initialCampaignsBlockMarkup($heroId, $shopUrl);

        return self::heroContent($shopUrl, $heroId)
            . "\n"
            . self::benefitsContent()
            . ($campaigns !== '' ? "\n" . $campaigns : '')
            . "\n"
            . self::managedHomeTail($supportUrl);
    }
}
