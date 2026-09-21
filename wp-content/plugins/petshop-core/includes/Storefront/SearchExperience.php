<?php

declare(strict_types=1);

namespace Petshop\Core\Storefront;

defined('ABSPATH') || exit;

final class SearchExperience
{
    public static function bootstrap(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('woocommerce_no_products_found', [self::class, 'renderNoResults'], 20);
        add_filter('get_the_archive_title', [self::class, 'filterSearchTitle']);
        add_filter('woocommerce_page_title', [self::class, 'filterSearchTitle']);
        add_filter('document_title_parts', [self::class, 'filterDocumentTitle']);
    }

    public static function enqueueAssets(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $path = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/storefront-search.js';
        wp_enqueue_script(
            'petshop-storefront-search',
            plugins_url('assets/js/storefront-search.js', PETSHOP_CORE_FILE),
            [],
            is_file($path) ? (string) filemtime($path) : '1.0.0',
            true
        );
        wp_add_inline_script(
            'petshop-storefront-search',
            'window.petshopSearchConfig=' . wp_json_encode([
                'endpoint' => wp_make_link_relative(rest_url('wc/store/v1/products')),
                'minimumCharacters' => 2,
                'noResults' => __('Nenhum produto encontrado.', 'petshop-core'),
                'loading' => __('Buscando produtos…', 'petshop-core'),
                'resultsLabel' => __('Sugestões de produtos', 'petshop-core'),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';',
            'before'
        );
    }

    public static function renderNoResults(): void
    {
        if (!is_search()) {
            return;
        }

        $query = trim(get_search_query());
        echo '<section class="petshop-search-empty" aria-labelledby="petshop-search-empty-title">';
        echo '<h2 id="petshop-search-empty-title">' . esc_html__('Não encontramos esse produto', 'petshop-core') . '</h2>';
        if ($query !== '') {
            echo '<p>' . esc_html(sprintf(
                /* translators: %s: search query */
                __('A busca por “%s” não retornou produtos. Confira a escrita ou explore as alternativas abaixo.', 'petshop-core'),
                $query
            )) . '</p>';
        }

        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'number' => 6,
            'orderby' => 'count',
            'order' => 'DESC',
        ]);
        if (is_array($categories) && $categories !== []) {
            echo '<h3>' . esc_html__('Categorias disponíveis', 'petshop-core') . '</h3><ul class="petshop-search-empty__categories">';
            foreach ($categories as $category) {
                if (!$category instanceof \WP_Term) {
                    continue;
                }
                $url = get_term_link($category);
                if (is_wp_error($url)) {
                    continue;
                }
                echo '<li><a href="' . esc_url($url) . '">' . esc_html($category->name) . '</a></li>';
            }
            echo '</ul>';
        }
        echo do_shortcode('[products limit="4" columns="4" orderby="popularity"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</section>';
    }

    public static function filterSearchTitle(string $title): string
    {
        if (!is_search()) {
            return $title;
        }

        return sprintf(
            /* translators: %s: search query */
            __('Resultados para “%s”', 'petshop-core'),
            get_search_query()
        );
    }

    /** @param array<string, string> $parts @return array<string, string> */
    public static function filterDocumentTitle(array $parts): array
    {
        if (is_search()) {
            $parts['title'] = self::filterSearchTitle($parts['title'] ?? '');
        }

        return $parts;
    }
}
