<?php

defined('ABSPATH') || exit(1);

/**
 * @param mixed $condition
 */
function petshop_018_assert($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @return list<int>
 */
function petshop_018_product_ids_from_blocks(string $content): array
{
    $ids = [];
    foreach (parse_blocks($content) as $block) {
        $queue = [$block];
        while ($queue !== []) {
            $current = array_shift($queue);
            if (($current['blockName'] ?? '') === 'petshop/product-grid') {
                $attrs = (array) ($current['attrs'] ?? []);
                if (($attrs['selectionMode'] ?? '') === 'manual') {
                    foreach ((array) ($attrs['productIds'] ?? []) as $id) {
                        $ids[] = absint($id);
                    }
                } elseif (($attrs['selectionMode'] ?? '') === 'category') {
                    $categoryIds = array_map('absint', (array) ($attrs['categoryIds'] ?? []));
                    if ($categoryIds !== []) {
                        $query = new WP_Query([
                            'post_type' => 'product',
                            'post_status' => 'publish',
                            'posts_per_page' => 20,
                            'fields' => 'ids',
                            'tax_query' => [
                                [
                                    'taxonomy' => 'product_cat',
                                    'field' => 'term_id',
                                    'terms' => $categoryIds,
                                ],
                            ],
                        ]);
                        foreach ($query->posts as $id) {
                            $ids[] = absint($id);
                        }
                    }
                } else {
                    petshop_018_assert(false, 'Vitrine comercial deve usar selecao manual ou categoria.');
                }
            }
            foreach ((array) ($current['innerBlocks'] ?? []) as $inner) {
                $queue[] = $inner;
            }
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

/**
 * @return array<string, mixed>
 */
function petshop_018_snapshot_page(WP_Post $page): array
{
    return [
        'post_content' => (string) $page->post_content,
        'post_status' => (string) $page->post_status,
    ];
}

$pages = [
    'animal-republik' => [
        'title' => 'Animal Republik',
        'menu_label' => 'Animal Republik',
        'required' => ['Animal Republik', 'Fornecedor oficial', 'wp:petshop/product-grid'],
        'placeholder_key' => 'commercial-animal-republik-placeholder',
        'category_slug' => 'animal-republik',
    ],
    'premium' => [
        'title' => 'Produtos premium',
        'menu_label' => 'Premium',
        'required' => ['Produtos premium', 'Critério de curadoria', 'wp:petshop/product-grid'],
        'placeholder_key' => 'commercial-premium-placeholder',
        'category_slug' => 'premium',
    ],
];

$locations = get_theme_mod('nav_menu_locations', []);
$menuId = (int) ($locations['petshop-primary'] ?? 0);
petshop_018_assert($menuId > 0, 'Menu principal nao atribuido.');
$menuItems = wp_get_nav_menu_items($menuId);
petshop_018_assert(is_array($menuItems), 'Menu principal invalido.');

$originalVersion = get_option('petshop_storefront_version', null);
$snapshots = [];

try {
    foreach ($pages as $slug => $expectation) {
        $page = get_page_by_path($slug);
        petshop_018_assert($page instanceof WP_Post, "Pagina ausente: {$slug}");
        petshop_018_assert((string) $page->post_title === $expectation['title'], "Titulo inesperado em {$slug}");
        petshop_018_assert((bool) get_post_meta((int) $page->ID, '_petshop_managed_commercial_page_018', true), "Pagina {$slug} sem assinatura do Plano 018.");
        petshop_018_assert($page->post_status === 'publish', "Pagina {$slug} deveria estar publicada com a vitrine atual.");

        foreach ($expectation['required'] as $fragment) {
            petshop_018_assert(str_contains((string) $page->post_content, $fragment), "Conteudo de {$slug} sem fragmento obrigatorio: {$fragment}");
        }

        $blocks = parse_blocks((string) $page->post_content);
        $blockNames = array_filter(array_map(static fn (array $block): ?string => $block['blockName'] ?? null, $blocks));
        petshop_018_assert(in_array('core/group', $blockNames, true), "Pagina {$slug} sem grupo Gutenberg raiz.");
        petshop_018_assert(str_contains((string) $page->post_content, 'wp:cover'), "Pagina {$slug} sem hero editavel em bloco Capa.");
        petshop_018_assert(str_contains((string) $page->post_content, 'wp-block-cover__image-background'), "Pagina {$slug} sem imagem editavel no hero.");
        petshop_018_assert(str_contains((string) $page->post_content, 'wp:buttons'), "Pagina {$slug} sem CTA editavel.");
        petshop_018_assert(str_contains((string) $page->post_content, 'petshop-section-head__link'), "Pagina {$slug} sem link Ver tudo na vitrine.");
        petshop_018_assert(str_contains((string) $page->post_content, 'Ver tudo'), "Pagina {$slug} sem texto Ver tudo.");
        petshop_018_assert(str_contains((string) $page->post_content, 'product_cat'), "Link Ver tudo de {$slug} nao aponta para filtro de categoria.");
        petshop_018_assert(str_contains((string) $page->post_content, (string) $expectation['category_slug']), "Link Ver tudo de {$slug} sem categoria esperada.");

        $option = 'petshop_' . str_replace('-', '_', (string) $expectation['placeholder_key']) . '_attachment_id';
        $attachmentId = (int) get_option($option);
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_petshop_placeholder_key',
            'meta_value' => $expectation['placeholder_key'],
        ]);
        if ($attachmentId <= 0 && $attachments !== []) {
            $attachmentId = (int) $attachments[0];
        }
        petshop_018_assert($attachmentId > 0 && get_post($attachmentId) instanceof WP_Post, "Imagem placeholder ausente para {$slug}.");
        petshop_018_assert(
            (string) get_post_meta($attachmentId, '_petshop_placeholder_key', true) === (string) $expectation['placeholder_key'],
            "Imagem placeholder incorreta para {$slug}."
        );
        $alt = (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
        petshop_018_assert(trim($alt) !== '', "Imagem de {$slug} sem alt editavel.");

        $productIds = petshop_018_product_ids_from_blocks((string) $page->post_content);
        petshop_018_assert(count($productIds) > 0, "Vitrine de {$slug} sem produtos reais selecionados.");
        foreach ($productIds as $productId) {
            $product = wc_get_product($productId);
            petshop_018_assert($product instanceof WC_Product, "Produto invalido na vitrine de {$slug}: {$productId}");
            petshop_018_assert($product->get_status() === 'publish', "Produto nao publicado na vitrine de {$slug}: {$productId}");
            petshop_018_assert(in_array($product->get_catalog_visibility(), ['visible', 'catalog'], true), "Produto oculto na vitrine de {$slug}: {$productId}");
            petshop_018_assert((float) $product->get_price() > 0, "Produto sem preco na vitrine de {$slug}: {$productId}");
        }

        $hasMenuItem = false;
        foreach ($menuItems as $item) {
            if ($item instanceof WP_Post && (int) $item->object_id === (int) $page->ID) {
                $hasMenuItem = true;
                break;
            }
        }
        petshop_018_assert($hasMenuItem, "Pagina publicada {$slug} nao esta na navegacao principal.");

        $snapshots[(int) $page->ID] = petshop_018_snapshot_page($page);
        $edited = (string) $page->post_content . "\n<!-- wp:paragraph --><p>sentinela-plano-018</p><!-- /wp:paragraph -->";
        $updated = wp_update_post(['ID' => (int) $page->ID, 'post_content' => wp_slash($edited)], true);
        petshop_018_assert(!is_wp_error($updated), "Nao foi possivel preparar teste de persistencia em {$slug}.");
    }

    update_option('petshop_storefront_version', '3.1.0', false);
    Petshop\Core\StorefrontExperience::maybeEnsureStorefront();

    foreach (array_keys($snapshots) as $pageId) {
        $after = (string) get_post_field('post_content', $pageId);
        petshop_018_assert(str_contains($after, 'sentinela-plano-018'), "Reprovisionamento sobrescreveu conteudo da pagina {$pageId}.");
    }
} finally {
    foreach ($snapshots as $pageId => $snapshot) {
        wp_update_post([
            'ID' => (int) $pageId,
            'post_content' => wp_slash((string) $snapshot['post_content']),
            'post_status' => (string) $snapshot['post_status'],
        ]);
    }
    if ($originalVersion === null) {
        delete_option('petshop_storefront_version');
    } else {
        update_option('petshop_storefront_version', $originalVersion, false);
    }
}

WP_CLI::success('Paginas comerciais P1 do Plano 018 aprovadas.');
