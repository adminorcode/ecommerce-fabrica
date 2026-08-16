<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce ativo.');
}

$failures = [];
$createdProducts = [];
$createdTerms = [];

$createTerm = static function (string $name, string $slug, array $meta = []) use (&$createdTerms): int {
    $existing = get_term_by('slug', $slug, 'product_cat');
    if ($existing instanceof WP_Term) {
        $termId = (int) $existing->term_id;
    } else {
        $created = wp_insert_term($name, 'product_cat', ['slug' => $slug]);
        if (is_wp_error($created)) {
            throw new RuntimeException($created->get_error_message());
        }
        $termId = (int) $created['term_id'];
        $createdTerms[] = $termId;
    }

    foreach ($meta as $key => $value) {
        update_term_meta($termId, $key, $value);
    }

    return $termId;
};

$createProduct = static function (string $name, string $sku, array $categoryIds = [], int $sales = 0) use (&$createdProducts): int {
    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_sku($sku);
    $product->set_regular_price('29.90');
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_category_ids($categoryIds);
    $product->set_total_sales($sales);
    $productId = $product->save();
    $createdProducts[] = $productId;

    return $productId;
};

try {
    $categoryId = $createTerm('Conjuntos Plano 016', 'conjuntos-plano-016');
    $seasonalId = $createTerm('Sazonal Plano 016', 'sazonal-plano-016', [
        'petshop_seasonal' => '1',
        'petshop_visible_in_menu' => '1',
    ]);
    $manualA = $createProduct('Produto Manual A Plano 016', 'P016-A', [$categoryId], 8);
    $manualB = $createProduct('Produto Manual B Plano 016', 'P016-B', [$categoryId, $seasonalId], 3);

    $registry = WP_Block_Type_Registry::get_instance();
    if (!$registry->is_registered('petshop/product-grid')) {
        $failures[] = 'Bloco petshop/product-grid nao registrado';
    }

    $blockType = $registry->get_registered('petshop/product-grid');
    if ($blockType === null || (int) ($blockType->api_version ?? 0) !== 3) {
        $failures[] = 'Bloco petshop/product-grid deveria usar apiVersion 3';
    }

    $sanitized = Petshop\Core\ProductGridBlock::sanitizeAttributes([
        'selectionMode' => 'manual',
        'productIds' => [$manualA, $manualA, -10, $manualB],
        'categoryIds' => [$categoryId, $categoryId],
        'limit' => 99,
        'columns' => 1,
        'orderby' => 'invalid',
        'order' => 'sideways',
    ]);
    if ($sanitized['productIds'] !== [$manualA, $manualB] || $sanitized['limit'] !== 20 || $sanitized['columns'] !== 2) {
        $failures[] = 'Sanitizacao de IDs, limite ou colunas falhou';
    }

    $nameRequest = new WP_REST_Request('GET', '/petshop/v1/product-grid/products');
    $nameRequest->set_param('search', 'Produto Manual A Plano 016');
    $nameResults = Petshop\Core\ProductGridBlock::searchProducts($nameRequest)->get_data();
    if (!in_array($manualA, wp_list_pluck($nameResults, 'id'), true)) {
        $failures[] = 'Busca REST por nome nao encontrou produto esperado';
    }

    $skuRequest = new WP_REST_Request('GET', '/petshop/v1/product-grid/products');
    $skuRequest->set_param('search', 'P016-B');
    $skuResults = Petshop\Core\ProductGridBlock::searchProducts($skuRequest)->get_data();
    if (!in_array($manualB, wp_list_pluck($skuResults, 'id'), true)) {
        $failures[] = 'Busca REST por SKU nao encontrou produto esperado';
    }

    $manualShortcode = Petshop\Core\ProductGridBlock::shortcodeAttributes([
        'selectionMode' => 'manual',
        'productIds' => [$manualB, 999999, $manualA],
        'limit' => 4,
        'columns' => 4,
    ]);
    if (($manualShortcode['ids'] ?? '') !== $manualB . ',' . $manualA || ($manualShortcode['orderby'] ?? '') !== 'post__in') {
        $failures[] = 'Selecao manual nao preservou ordem dos IDs validos';
    }

    $categoryShortcode = Petshop\Core\ProductGridBlock::shortcodeAttributes([
        'selectionMode' => 'category',
        'categoryIds' => [$categoryId],
        'limit' => 4,
        'columns' => 4,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
    if (($categoryShortcode['category'] ?? '') !== 'conjuntos-plano-016' || ($categoryShortcode['order'] ?? '') !== 'ASC') {
        $failures[] = 'Modo categoria nao converteu IDs de termo para slug consultavel';
    }

    $seasonalShortcode = Petshop\Core\ProductGridBlock::shortcodeAttributes([
        'selectionMode' => 'seasonal',
        'limit' => 4,
        'columns' => 4,
    ]);
    if (!is_array($seasonalShortcode) || !str_contains((string) ($seasonalShortcode['category'] ?? ''), 'sazonal-plano-016')) {
        $failures[] = 'Modo sazonal nao encontrou categorias sazonais visiveis no menu';
    }

    $manualHtml = do_blocks(Petshop\Core\ProductGridBlock::blockMarkup([
        'selectionMode' => 'manual',
        'productIds' => [$manualB, $manualA],
        'limit' => 2,
        'columns' => 2,
    ]));
    if (!str_contains($manualHtml, 'Produto Manual B Plano 016') || !str_contains($manualHtml, 'Produto Manual A Plano 016')) {
        $failures[] = 'Renderizacao manual nao exibiu produtos publicados e visiveis';
    }
    if (strpos($manualHtml, 'Produto Manual B Plano 016') > strpos($manualHtml, 'Produto Manual A Plano 016')) {
        $failures[] = 'Renderizacao manual nao preservou ordem definida';
    }

    $emptyHtml = do_blocks(Petshop\Core\ProductGridBlock::blockMarkup([
        'selectionMode' => 'manual',
        'productIds' => [999999],
    ]));
    if ($emptyHtml !== '') {
        $failures[] = 'Consulta vazia deveria renderizar string vazia';
    }

    $legacy = '<!-- wp:group {"className":"petshop-product-showcase","layout":{"type":"constrained"}} -->'
        . '<div class="wp-block-group petshop-product-showcase">'
        . '<!-- wp:heading --><h2 class="wp-block-heading">Teste</h2><!-- /wp:heading -->'
        . '<!-- wp:shortcode -->[petshop_featured_products_grid limit="4" columns="4"]<!-- /wp:shortcode -->'
        . '<!-- wp:shortcode -->[petshop_kits_section_grid limit="3" columns="3" category="conjuntos-plano-016"]<!-- /wp:shortcode -->'
        . '<!-- wp:shortcode -->[petshop_seasonal_products_grid limit="2" columns="2"]<!-- /wp:shortcode -->'
        . '<!-- wp:shortcode -->[petshop_product_showcase_grid limit="5" columns="4" category="conjuntos-plano-016" orderby="title" order="ASC"]<!-- /wp:shortcode -->'
        . '<!-- wp:shortcode -->[petshop_unknown_grid]<!-- /wp:shortcode -->'
        . '</div><!-- /wp:group -->';
    $schema25 = Petshop\Core\Migration\HomeMigrator::registry()[25];
    $shopUrl = (string) wc_get_page_permalink('shop');
    $migrated = $schema25($legacy, $shopUrl);
    $second = $schema25($migrated, $shopUrl);
    if (substr_count($migrated, 'wp:petshop/product-grid') !== 4) {
        $failures[] = 'Migracao deveria criar quatro instancias de petshop/product-grid';
    }
    if (!str_contains($migrated, '[petshop_unknown_grid]')) {
        $failures[] = 'Shortcode desconhecido deveria permanecer intacto';
    }
    if ($migrated !== $second) {
        $failures[] = 'Migracao do schema 25 deveria ser idempotente';
    }

    $outerLegacy = '<!-- wp:shortcode -->[petshop_seasonal_products limit="4" columns="4" title="Coleção da estação" cta="Ver todos →"]<!-- /wp:shortcode -->'
        . '<!-- wp:shortcode -->[petshop_product_showcase limit="4" columns="4" title="Seleção para banho e tosa" intro="Modelos pensados para finalização profissional, apresentação de kits e recompra recorrente." cta="Ver todos →" category="conjuntos-plano-016" orderby="date"]<!-- /wp:shortcode -->';
    $outerMigrated = $schema25($outerLegacy, $shopUrl);
    if (substr_count($outerMigrated, 'wp:petshop/product-grid') !== 2) {
        $failures[] = 'Migracao deveria converter shortcodes de secao legados em blocos product-grid';
    }
    if (preg_match('/\[petshop_(seasonal_products|product_showcase)\b/', $outerMigrated)) {
        $failures[] = 'Migracao deixou shortcodes de secao legados no editor';
    }
    if (!Petshop\Core\Migration\HomeMigrator::needsProductGridShortcodeRepair($outerLegacy, $shopUrl)) {
        $failures[] = 'Reparo do schema 25 deveria detectar shortcodes de secao legados';
    }
    if (Petshop\Core\Migration\HomeMigrator::needsProductGridShortcodeRepair($outerMigrated, $shopUrl)) {
        $failures[] = 'Reparo do schema 25 nao deveria repetir apos migrar shortcodes de secao';
    }

    $homeId = (int) get_option('page_on_front');
    if ($homeId <= 0) {
        $failures[] = 'Home estatica nao configurada para teste de migracao real';
    } else {
        $original = [
            'content' => (string) get_post_field('post_content', $homeId),
            'schema' => get_post_meta($homeId, '_petshop_home_schema_version', true),
            'managed' => get_post_meta($homeId, '_petshop_managed_page', true),
            'version' => get_option('petshop_storefront_version'),
            'error' => get_option('petshop_storefront_migration_error'),
        ];

        try {
            wp_update_post(['ID' => $homeId, 'post_content' => wp_slash($legacy)]);
            update_post_meta($homeId, '_petshop_managed_page', 1);
            update_post_meta($homeId, '_petshop_home_schema_version', 24);
            update_option('petshop_storefront_version', '3.1.0', false);
            delete_option('petshop_storefront_migration_error');

            Petshop\Core\StorefrontExperience::maybeEnsureStorefront();

            $after = (string) get_post_field('post_content', $homeId);
            if (substr_count($after, 'wp:petshop/product-grid') !== 4) {
                $failures[] = 'Fluxo real de migracao nao persistiu quatro blocos petshop/product-grid na Home';
            }
            if (preg_match('/\[petshop_(featured_products|kits_section|seasonal_products|product_showcase)_grid\b/', $after)) {
                $failures[] = 'Fluxo real de migracao deixou shortcodes de grade reconhecidos na Home';
            }
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 25) {
                $failures[] = 'Fluxo real de migracao nao avancou schema da Home para 25';
            }
            if (get_option('petshop_storefront_migration_error', '') !== '') {
                $failures[] = 'Fluxo real de migracao registrou erro de storefront';
            }
        } finally {
            wp_update_post(['ID' => $homeId, 'post_content' => wp_slash((string) $original['content'])]);
            if ($original['managed'] === '') {
                delete_post_meta($homeId, '_petshop_managed_page');
            } else {
                update_post_meta($homeId, '_petshop_managed_page', $original['managed']);
            }
            update_post_meta($homeId, '_petshop_home_schema_version', $original['schema']);
            update_option('petshop_storefront_version', $original['version'], false);
            if ($original['error'] === false) {
                delete_option('petshop_storefront_migration_error');
            } else {
                update_option('petshop_storefront_migration_error', $original['error'], false);
            }
        }

        try {
            wp_update_post(['ID' => $homeId, 'post_content' => wp_slash($outerLegacy)]);
            update_post_meta($homeId, '_petshop_managed_page', 1);
            update_post_meta($homeId, '_petshop_home_schema_version', 25);
            update_option('petshop_storefront_version', '3.1.0', false);
            delete_option('petshop_storefront_migration_error');

            Petshop\Core\StorefrontExperience::maybeEnsureStorefront();

            $afterRepair = (string) get_post_field('post_content', $homeId);
            if (substr_count($afterRepair, 'wp:petshop/product-grid') !== 2) {
                $failures[] = 'Reparo real do schema 25 nao converteu shortcodes de secao legados';
            }
            if (preg_match('/\[petshop_(seasonal_products|product_showcase)\b/', $afterRepair)) {
                $failures[] = 'Reparo real do schema 25 deixou shortcodes de secao legados';
            }
        } finally {
            wp_update_post(['ID' => $homeId, 'post_content' => wp_slash((string) $original['content'])]);
            if ($original['managed'] === '') {
                delete_post_meta($homeId, '_petshop_managed_page');
            } else {
                update_post_meta($homeId, '_petshop_managed_page', $original['managed']);
            }
            update_post_meta($homeId, '_petshop_home_schema_version', $original['schema']);
            update_option('petshop_storefront_version', $original['version'], false);
            if ($original['error'] === false) {
                delete_option('petshop_storefront_migration_error');
            } else {
                update_option('petshop_storefront_migration_error', $original['error'], false);
            }
        }
    }
} finally {
    foreach ($createdProducts as $productId) {
        wp_delete_post($productId, true);
    }
    foreach (array_reverse($createdTerms) as $termId) {
        wp_delete_term($termId, 'product_cat');
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Validacao PHP do Plano 016 reprovada.');
}

WP_CLI::success('Bloco petshop/product-grid, consultas e migracao do Plano 016 aprovados.');
