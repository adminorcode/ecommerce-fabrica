<?php

use Petshop\Core\ProductGridBlock;

if (!defined('ABSPATH')) {
    exit(1);
}

/**
 * @param array<int, array<string, mixed>> $blocks
 * @return list<int>
 */
function petshop_commercial_grid_product_ids_from_blocks(array $blocks): array
{
    $ids = [];
    foreach ($blocks as $block) {
        if (($block['blockName'] ?? '') === 'petshop/product-grid') {
            foreach ((array) ($block['attrs']['productIds'] ?? []) as $productId) {
                $productId = absint($productId);
                if ($productId > 0 && !in_array($productId, $ids, true)) {
                    $ids[] = $productId;
                }
            }
        }

        foreach (petshop_commercial_grid_product_ids_from_blocks((array) ($block['innerBlocks'] ?? [])) as $productId) {
            if (!in_array($productId, $ids, true)) {
                $ids[] = $productId;
            }
        }
    }

    return $ids;
}

/**
 * @return list<int>
 */
function petshop_commercial_page_grid_product_ids(string $slug): array
{
    $page = get_page_by_path($slug);

    return $page instanceof WP_Post
        ? petshop_commercial_grid_product_ids_from_blocks(parse_blocks((string) $page->post_content))
        : [];
}

/**
 * @param list<int> $productIds
 */
function petshop_commercial_ensure_category(string $name, string $slug, array $productIds, int $order): int
{
    $slug = sanitize_title($slug);
    $term = get_term_by('slug', $slug, 'product_cat');
    if (!$term instanceof WP_Term) {
        $created = wp_insert_term($name, 'product_cat', ['slug' => $slug]);
        if (is_wp_error($created)) {
            throw new RuntimeException($created->get_error_message());
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
        if (!$product instanceof WC_Product) {
            continue;
        }
        $categoryIds = array_values(array_unique(array_merge($product->get_category_ids(), [$termId])));
        if ($categoryIds === $product->get_category_ids()) {
            continue;
        }
        $product->set_category_ids($categoryIds);
        $product->save();
    }

    return $termId;
}

function petshop_commercial_category_url(string $categorySlug): string
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

function petshop_commercial_sync_view_all_link(string $content, string $categorySlug): string
{
    $link = '<!-- wp:paragraph {"className":"petshop-section-head__cta"} --><p class="petshop-section-head__cta"><a class="petshop-section-head__link" href="' . esc_url(petshop_commercial_category_url($categorySlug)) . '">Ver tudo</a></p><!-- /wp:paragraph -->';
    $content = preg_replace(
        '/<!-- wp:paragraph \{"className":"petshop-section-head__cta"\} -->.*?<!-- \/wp:paragraph -->/s',
        '',
        $content
    );
    if (!is_string($content)) {
        return '';
    }

    $updated = preg_replace(
        '/(<div class="wp-block-group petshop-section-head">.*?<!-- \/wp:heading -->)/s',
        '$1' . $link,
        $content,
        1
    );

    return is_string($updated) ? $updated : $content;
}

function petshop_commercial_sync_page(string $pageSlug, string $categorySlug, int $categoryId): bool
{
    $page = get_page_by_path($pageSlug);
    if (!$page instanceof WP_Post) {
        return false;
    }

    $block = ProductGridBlock::blockMarkup([
        'selectionMode' => 'category',
        'categoryIds' => [$categoryId],
        'limit' => 20,
        'columns' => 4,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ]);

    $originalContent = (string) $page->post_content;
    $content = petshop_commercial_sync_view_all_link($originalContent, $categorySlug);
    $content = preg_replace('/<!-- wp:petshop\/product-grid\s+\{.*?\}\s+\/-->/s', $block, $content, 1, $count);
    if (!is_string($content) || $count < 1 || $content === $originalContent) {
        return false;
    }

    $updated = wp_update_post([
        'ID' => (int) $page->ID,
        'post_content' => wp_slash($content),
    ], true);

    if (is_wp_error($updated)) {
        throw new RuntimeException($updated->get_error_message());
    }

    return true;
}

$premiumProductIds = petshop_commercial_page_grid_product_ids('premium');
$premiumCategoryId = petshop_commercial_ensure_category('Produtos premium', 'premium', $premiumProductIds, 95);

$animalTerm = get_term_by('slug', 'animal-republik', 'product_cat');
$animalUpdated = $animalTerm instanceof WP_Term
    ? petshop_commercial_sync_page('animal-republik', 'animal-republik', (int) $animalTerm->term_id)
    : false;
$premiumUpdated = petshop_commercial_sync_page('premium', 'premium', $premiumCategoryId);

echo 'commercial-page-catalog-links: animal=' . ($animalUpdated ? 'updated' : 'preserved')
    . ' premium=' . ($premiumUpdated ? 'updated' : 'preserved')
    . ' premium_products=' . count($premiumProductIds) . "\n";
