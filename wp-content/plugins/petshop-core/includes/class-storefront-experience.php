<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

final class StorefrontExperience
{
    private const VERSION = '1.9.3';
    private const OPTION = 'petshop_storefront_version';
    private const LOCK_OPTION = 'petshop_storefront_migration_lock';
    private const ERROR_OPTION = 'petshop_storefront_migration_error';

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
        add_action('woocommerce_before_shop_loop', [self::class, 'renderCategoryIntroduction'], 5);
        add_action('woocommerce_before_shop_loop', [self::class, 'renderCatalogFilter'], 15);
        add_action('woocommerce_single_product_summary', [self::class, 'renderProductAssurance'], 25);
        add_filter('woocommerce_output_related_products_args', [self::class, 'relatedProductArgs']);
        add_action('wp_head', [self::class, 'renderMetaDescription'], 1);
        add_action('wp_head', [self::class, 'renderArchiveCanonical'], 2);
        add_shortcode('petshop_categories', [self::class, 'renderCategoryGrid']);
        add_shortcode('petshop_seasonal_products', [self::class, 'renderSeasonalProducts']);
        add_shortcode('petshop_reviews', [self::class, 'renderReviews']);
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
        $policiesId = self::ensurePage(
            'politicas-da-loja',
            'Políticas da loja',
            '<!-- wp:heading --><h2 class="wp-block-heading">Políticas da loja</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Consulte aqui as políticas comerciais, de privacidade e de troca aprovadas para a loja antes da publicação.</p><!-- /wp:paragraph -->'
        );
        $heroId = self::placeholderAttachment('hero-wide');
        if ($heroId <= 0) {
            throw new \RuntimeException('Imagem hero-wide ausente; execute o seed 004b e tente novamente.');
        }
        $homeId = self::ensurePage(
            'inicio',
            'Início',
            self::homeContent(
                (string) wc_get_page_permalink('shop'),
                (string) get_permalink($supportId),
                $heroId
            )
        );

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
        <?php
    }

    public static function renderEditCategoryFields(\WP_Term $term): void
    {
        wp_nonce_field('petshop_category_fields', 'petshop_category_nonce');
        $order = (int) get_term_meta($term->term_id, 'petshop_menu_order', true);
        $seasonal = (bool) get_term_meta($term->term_id, 'petshop_seasonal', true);
        $visible = (bool) get_term_meta($term->term_id, 'petshop_visible_in_menu', true);
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

        echo '<nav class="petshop-catalog-filter" aria-label="' . esc_attr__('Filtrar por categoria', 'petshop-core') . '">';
        echo '<span class="petshop-catalog-filter__label">' . esc_html__('Comprar por categoria:', 'petshop-core') . '</span>';
        echo '<ul>';
        foreach ($terms as $term) {
            if (!(bool) get_term_meta($term->term_id, 'petshop_visible_in_menu', true)) {
                continue;
            }
            echo '<li><a href="' . esc_url(get_term_link($term)) . '">' . esc_html($term->name) . '</a></li>';
        }
        echo '</ul></nav>';
    }

    public static function renderCategoryIntroduction(): void
    {
        if (!is_product_category()) {
            return;
        }
        $term = get_queried_object();
        if (!$term instanceof \WP_Term || trim((string) $term->description) === '') {
            return;
        }

        echo '<div class="petshop-category-introduction">';
        echo wp_kses_post(wpautop($term->description));
        echo '</div>';
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
                static fn (\WP_Term $term): bool => (bool) get_term_meta(
                    $term->term_id,
                    'petshop_visible_in_menu',
                    true
                )
            )
        );
        $visibleTerms = array_slice($visibleTerms, 0, max(1, absint($attributes['limit'])));

        ob_start();
        echo '<ul class="petshop-category-grid" aria-label="' . esc_attr__('Categorias de produtos', 'petshop-core') . '">';
        foreach ($visibleTerms as $term) {
            $thumbnailId = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
            $attachmentAlt = $thumbnailId > 0
                ? (string) get_post_meta($thumbnailId, '_wp_attachment_image_alt', true)
                : '';
            $image = $thumbnailId > 0
                ? wp_get_attachment_image(
                    $thumbnailId,
                    'woocommerce_thumbnail',
                    false,
                    $attachmentAlt === '' ? ['alt' => $term->name] : []
                )
                : wc_placeholder_img('woocommerce_thumbnail', ['alt' => $term->name]);
            echo '<li class="petshop-category-card">';
            echo '<a href="' . esc_url(get_term_link($term)) . '">';
            echo wp_kses_post($image);
            echo '<span>' . esc_html($term->name) . '</span>';
            echo '</a></li>';
        }
        echo '</ul>';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderSeasonalProducts(array $attributes = []): string
    {
        $attributes = shortcode_atts(['limit' => 4, 'columns' => 4], $attributes, 'petshop_seasonal_products');
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
        return do_shortcode(sprintf(
            '[products limit="%d" columns="%d" category="%s" orderby="date" order="DESC"]',
            max(1, absint($attributes['limit'])),
            max(1, absint($attributes['columns'])),
            esc_attr($slugs)
        ));
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
                        hash('sha256', self::heroContent($shopUrl, $heroId)),
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

        if ($content !== $originalContent) {
            wp_save_post_revision($homeId);
        }
        $saved = wp_update_post(['ID' => $homeId, 'post_content' => $content], true);
        if (is_wp_error($saved)) {
            throw new \RuntimeException($saved->get_error_message());
        }
        if ((string) get_post_field('post_content', $homeId) !== $content) {
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
        set_theme_mod('custom_logo', self::ensureLogoAttachment());
        if (in_array(get_option('blogname'), ['Petshop', 'Petshop Local', 'Autelie Moda Pet'], true)) {
            update_option('blogname', 'Auteliê Moda Pet');
        }
        if (in_array(get_option('blogdescription'), ['', 'Tudo para o seu pet'], true)) {
            update_option('blogdescription', 'Acessórios pet com personalidade');
        }
        update_post_meta($homeId, 'blocksy_post_meta_options', ['has_hero_section' => 'disabled']);
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
<!-- wp:group {"className":"petshop-hero__copy","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-hero__copy">
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

    private static function heroContent(string $shopUrl, int $heroId): string
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
<!-- wp:group {"className":"petshop-hero__copy","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-hero__copy">
<!-- wp:paragraph {"className":"petshop-eyebrow"} --><p class="petshop-eyebrow">Coleção Dia dos Pais</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">O detalhe que <span class="petshop-hero__accent">fideliza seu cliente</span></h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Gravatas, laços e adesivos temáticos que vão transformar cada atendimento em uma experiência especial e encantar o tutor.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$destination}">Ver a coleção de Dia dos Pais</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
<!-- wp:paragraph {"className":"petshop-hero__note"} --><p class="petshop-hero__note">Frete grátis para todo o Brasil acima de R$ 299</p><!-- /wp:paragraph -->
</div><!-- /wp:group --></div></div>
<!-- /wp:cover -->
BLOCKS;
    }

    private static function homeContent(string $shopUrl, string $supportUrl, int $heroId): string
    {
        $shopUrl = esc_url($shopUrl);
        $supportUrl = esc_url($supportUrl);

        return self::heroContent($shopUrl, $heroId) . <<<BLOCKS
<!-- wp:group {"className":"petshop-section","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section">
<!-- wp:heading --><h2 class="wp-block-heading">Compre por categoria</h2><!-- /wp:heading -->
<!-- wp:shortcode -->[petshop_categories limit="8"]<!-- /wp:shortcode --></div><!-- /wp:group -->
<!-- wp:group {"className":"petshop-section petshop-commerce-banner","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section petshop-commerce-banner">
<!-- wp:heading --><h2 class="wp-block-heading">Kits e conjuntos</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Escolhas práticas para compor o visual e facilitar a rotina profissional.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->[products limit="4" columns="4" category="conjuntos" orderby="date" order="DESC"]<!-- /wp:shortcode --></div><!-- /wp:group -->
<!-- wp:group {"className":"petshop-section","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section">
<!-- wp:heading --><h2 class="wp-block-heading">Mais procurados</h2><!-- /wp:heading -->
<!-- wp:shortcode -->[products limit="4" columns="4" orderby="popularity"]<!-- /wp:shortcode --></div><!-- /wp:group -->
<!-- wp:group {"className":"petshop-section petshop-section--soft","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section petshop-section--soft">
<!-- wp:heading --><h2 class="wp-block-heading">Novidades</h2><!-- /wp:heading -->
<!-- wp:shortcode -->[products limit="4" columns="4" orderby="date" order="DESC"]<!-- /wp:shortcode --></div><!-- /wp:group -->
<!-- wp:group {"className":"petshop-section petshop-seasonal","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section petshop-seasonal">
<!-- wp:heading --><h2 class="wp-block-heading">Coleção da estação</h2><!-- /wp:heading -->
<!-- wp:shortcode -->[petshop_seasonal_products limit="4" columns="4"]<!-- /wp:shortcode --></div><!-- /wp:group -->
<!-- wp:group {"className":"petshop-section petshop-professional","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section petshop-professional">
<!-- wp:heading --><h2 class="wp-block-heading">Seleção para banho e tosa</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Modelos pensados para finalização profissional, apresentação de kits e recompra recorrente.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->[products limit="4" columns="4" category="adesivos,gravatas,lacos" orderby="date" order="DESC"]<!-- /wp:shortcode --></div><!-- /wp:group -->
<!-- wp:group {"className":"petshop-section petshop-reviews","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section petshop-reviews">
<!-- wp:heading --><h2 class="wp-block-heading">Quem compra, conta</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Avaliações reais e aprovadas dos produtos aparecem nesta seção.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->[petshop_reviews limit="3"]<!-- /wp:shortcode --></div><!-- /wp:group -->
<!-- wp:group {"className":"petshop-section petshop-support-cta","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section petshop-support-cta">
<!-- wp:heading --><h2 class="wp-block-heading">Precisa de ajuda para escolher?</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Fale com nosso atendimento e encontre a opção adequada para o seu pet ou negócio.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$supportUrl}">Falar com atendimento</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->
BLOCKS;
    }
}
