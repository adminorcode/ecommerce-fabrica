<?php

defined('ABSPATH') || exit(1);

function petshop_ar_assert(mixed $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$manifest = json_decode((string) file_get_contents(__DIR__ . '/data/animal-republik-lancamentos.json'), true, 512, JSON_THROW_ON_ERROR);
$category = get_term_by('slug', (string) $manifest['category']['slug'], 'product_cat');
petshop_ar_assert($category instanceof WP_Term, 'Categoria Animal Republik ausente.');

$page = get_page_by_path('animal-republik');
petshop_ar_assert($page instanceof WP_Post, 'Pagina Animal Republik ausente.');
petshop_ar_assert(!str_contains((string) $page->post_content, 'petshop-commercial-context'), 'Pagina Animal Republik ainda exibe bloco de contexto removido.');
petshop_ar_assert(!str_contains((string) $page->post_content, 'Curadoria edit'), 'Pagina Animal Republik ainda exibe texto de curadoria removido.');
petshop_ar_assert(str_contains((string) $page->post_content, 'petshop-section-head__link'), 'Pagina Animal Republik sem link Ver tudo.');
petshop_ar_assert(str_contains((string) $page->post_content, 'product_cat'), 'Link Ver tudo da Animal Republik nao aponta para filtro de categoria.');
petshop_ar_assert(str_contains((string) $page->post_content, 'animal-republik'), 'Link Ver tudo da Animal Republik sem categoria esperada.');

$foundCategoryBlock = false;
$foundLimit20 = false;
foreach (parse_blocks((string) $page->post_content) as $block) {
    $queue = [$block];
    while ($queue !== []) {
        $current = array_shift($queue);
        if (($current['blockName'] ?? '') === 'petshop/product-grid') {
            $attrs = (array) ($current['attrs'] ?? []);
            if (($attrs['selectionMode'] ?? '') === 'category' && in_array((int) $category->term_id, array_map('intval', (array) ($attrs['categoryIds'] ?? [])), true)) {
                $foundCategoryBlock = true;
                $foundLimit20 = (int) ($attrs['limit'] ?? 0) === 20;
            }
        }
        foreach ((array) ($current['innerBlocks'] ?? []) as $inner) {
            $queue[] = $inner;
        }
    }
}
petshop_ar_assert($foundCategoryBlock, 'Pagina Animal Republik nao usa a categoria Animal Republik no bloco de vitrine.');
petshop_ar_assert($foundLimit20, 'Vitrine Animal Republik nao esta configurada para ate 20 produtos.');

$tagSlugs = array_map(static fn (array $tag): string => (string) $tag['slug'], (array) $manifest['tags']);

foreach ((array) $manifest['products'] as $record) {
    $record = (array) $record;
    $productId = wc_get_product_id_by_sku((string) $record['sku']);
    petshop_ar_assert($productId > 0, 'Produto Animal Republik ausente: ' . $record['sku']);

    $product = wc_get_product($productId);
    petshop_ar_assert($product instanceof WC_Product, 'Produto Animal Republik invalido: ' . $record['sku']);
    petshop_ar_assert($product->get_status() === 'publish', 'Produto Animal Republik nao publicado: ' . $record['sku']);
    petshop_ar_assert($product->get_name() !== '', 'Produto Animal Republik sem titulo: ' . $record['sku']);
    petshop_ar_assert((float) $product->get_price() > 0, 'Produto Animal Republik sem preco: ' . $record['sku']);
    petshop_ar_assert(in_array((int) $category->term_id, $product->get_category_ids(), true), 'Produto sem categoria Animal Republik: ' . $record['sku']);

    $imageId = (int) $product->get_image_id();
    petshop_ar_assert($imageId > 0 && get_post($imageId) instanceof WP_Post, 'Produto Animal Republik sem imagem: ' . $record['sku']);
    petshop_ar_assert(trim((string) get_post_meta($imageId, '_wp_attachment_image_alt', true)) !== '', 'Imagem Animal Republik sem alt: ' . $record['sku']);
    petshop_ar_assert((string) get_post_meta($imageId, '_petshop_animal_republik_source_id', true) === (string) $record['sourceId'], 'Imagem Animal Republik sem assinatura de origem: ' . $record['sku']);
    petshop_ar_assert((string) $product->get_meta('_petshop_animal_republik_source_id') === (string) $record['sourceId'], 'Produto Animal Republik sem assinatura de origem: ' . $record['sku']);

    $productTags = wp_get_post_terms($productId, 'product_tag', ['fields' => 'slugs']);
    petshop_ar_assert(is_array($productTags), 'Tags invalidas no produto Animal Republik: ' . $record['sku']);
    foreach ($tagSlugs as $tagSlug) {
        petshop_ar_assert(in_array($tagSlug, $productTags, true), 'Produto Animal Republik sem tag ' . $tagSlug . ': ' . $record['sku']);
    }
}

WP_CLI::success('Produtos Animal Republik cadastrados e vinculados a pagina comercial.');
