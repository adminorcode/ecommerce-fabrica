<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validação com WP-CLI e WooCommerce ativo.');
}

$manifestPath = __DIR__ . '/data/004b-products.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$failures = [];
$categoryTargets = [];
$skus = array_map(static fn (array $record): string => (string) $record['sku'], $manifest['products']);
if (count($skus) !== 26 || count(array_unique($skus)) !== 26) {
    $failures[] = 'Manifesto deve conter exatamente 26 SKUs únicos';
}

foreach ($manifest['products'] as $record) {
    $sku = (string) $record['sku'];
    $productId = wc_get_product_id_by_sku($sku);
    if ($productId <= 0) {
        $failures[] = "Produto ausente: {$sku}";
        continue;
    }

    $product = wc_get_product($productId);
    if (!$product instanceof WC_Product || $product->get_status() !== 'publish') {
        $failures[] = "Produto não publicado: {$sku}";
        continue;
    }
    if ($product->get_image_id() <= 0) {
        $failures[] = "Produto sem imagem: {$sku}";
    }
    $normalizeName = static fn (string $name): string => trim((string) preg_replace('/\s+/u', ' ', $name));
    if ($normalizeName($product->get_name()) !== $normalizeName((string) $record['name'])) {
        $failures[] = "Nome divergente do catálogo: {$sku}";
    }
    if ((string) $product->get_regular_price() !== wc_format_decimal($record['price'])) {
        $failures[] = "Preço divergente do catálogo: {$sku}";
    }
    if ((string) get_post_meta($productId, '_petshop_placeholder_source_workbook', true) !== $manifest['sourceWorkbook']) {
        $failures[] = "Produto sem rastreabilidade do XLSX: {$sku}";
    }

    $expectedSlugs = isset($record['categories'])
        ? array_values(array_unique(array_map('strval', (array) $record['categories'])))
        : [(string) $record['category']];
    $assignedSlugs = wp_get_post_terms($productId, 'product_cat', ['fields' => 'slugs']);
    if (is_wp_error($assignedSlugs)) {
        $failures[] = "Falha ao consultar categorias: {$sku}";
        continue;
    }
    foreach ($expectedSlugs as $slug) {
        $categoryTargets[$slug] = ($categoryTargets[$slug] ?? 0) + 1;
        if (!in_array($slug, $assignedSlugs, true)) {
            $failures[] = "Categoria {$slug} ausente no produto {$sku}";
        }
    }
}

foreach ($categoryTargets as $slug => $target) {
    $term = get_term_by('slug', $slug, 'product_cat');
    if (!$term instanceof WP_Term) {
        $failures[] = "Categoria ausente: {$slug}";
        continue;
    }
    if ((int) $term->count < $target) {
        $failures[] = "Categoria {$slug} tem {$term->count} produtos; esperado ao menos {$target}";
    }
    if ((int) get_term_meta($term->term_id, 'thumbnail_id', true) <= 0) {
        $failures[] = "Categoria sem imagem: {$slug}";
    }
    if (trim((string) $term->description) === '') {
        $failures[] = "Categoria sem descrição editável: {$slug}";
    }
}

$minimumCategoryCounts = [
    'promocoes' => 1,
    'adesivos' => 2,
    'babador' => 2,
    'bandanas' => 2,
    'colarinhos' => 2,
    'conjuntos' => 2,
    'copa' => 2,
    'dia-dos-pais' => 1,
    'festa-junina' => 2,
    'gargantilhas' => 2,
    'gravatas' => 2,
    'inverno' => 2,
    'lacos' => 2,
    'penteados' => 2,
];
$actualCategorySlugs = array_keys($categoryTargets);
$expectedCategorySlugs = array_keys($minimumCategoryCounts);
sort($actualCategorySlugs);
sort($expectedCategorySlugs);
if ($actualCategorySlugs !== $expectedCategorySlugs) {
    $failures[] = 'As 14 categorias-alvo do plano não correspondem ao manifesto';
}
foreach ($minimumCategoryCounts as $slug => $minimum) {
    $term = get_term_by('slug', $slug, 'product_cat');
    if (!$term instanceof WP_Term || (int) $term->count < $minimum) {
        $actual = $term instanceof WP_Term ? (int) $term->count : 0;
        $failures[] = "Mínimo fixo não atendido em {$slug}: {$actual}/{$minimum}";
    }
}

foreach ($manifest['images'] as $key => $image) {
    $attachments = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_key' => '_petshop_placeholder_key',
        'meta_value' => $key,
    ]);
    if (count($attachments) !== 1) {
        $failures[] = "Imagem {$key} possui " . count($attachments) . ' anexos; esperado 1';
        continue;
    }
    $attachmentId = (int) $attachments[0];
    if ((string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true) === '') {
        $failures[] = "Imagem sem alt: {$key}";
    }
    if ((string) get_post_meta($attachmentId, '_petshop_placeholder_source', true) !== (string) $image['source']) {
        $failures[] = "Fonte divergente: {$key}";
    }
    if ((string) get_post_meta($attachmentId, '_petshop_placeholder_license', true) !== $manifest['license']) {
        $failures[] = "Licença divergente: {$key}";
    }
}

$homeId = (int) get_option('page_on_front');
$homeContent = (string) get_post_field('post_content', $homeId);

$walkBlocks = static function (array $blocks) use (&$walkBlocks): array {
    $flat = [];
    foreach ($blocks as $block) {
        $flat[] = $block;
        if (!empty($block['innerBlocks'])) {
            $flat = array_merge($flat, $walkBlocks($block['innerBlocks']));
        }
    }
    return $flat;
};
$heroBlock = null;
foreach ($walkBlocks(parse_blocks($homeContent)) as $block) {
    if (
        ($block['blockName'] ?? '') === 'core/cover'
        && str_contains((string) ($block['attrs']['className'] ?? ''), 'petshop-hero')
    ) {
        $heroBlock = $block;
        break;
    }
}
if ($heroBlock === null) {
    $failures[] = 'Home sem bloco Cover editável do hero';
} else {
    if (($heroBlock['attrs']['align'] ?? '') !== 'full') {
        $failures[] = 'Hero não está configurado como full-bleed';
    }
    if ((int) ($heroBlock['attrs']['id'] ?? 0) <= 0 || trim((string) ($heroBlock['attrs']['alt'] ?? '')) === '') {
        $failures[] = 'Imagem ou alt editável ausente no hero';
    }
    $heroHtml = render_block($heroBlock);
    if (!preg_match('/<a\b[^>]*href="([^"]+)"/', $heroHtml, $matches)) {
        $failures[] = 'Hero sem CTA editável';
    } else {
        $ctaUrl = html_entity_decode($matches[1], ENT_QUOTES);
        $path = trim((string) wp_parse_url($ctaUrl, PHP_URL_PATH), '/');
        $pathParts = $path === '' ? [] : explode('/', $path);
        $categorySlug = count($pathParts) >= 2 && $pathParts[count($pathParts) - 2] === 'product-category'
            ? $pathParts[count($pathParts) - 1]
            : '';
        $shopPageId = (int) get_option('woocommerce_shop_page_id');
        $shopUrl = $shopPageId > 0 ? (string) get_permalink($shopPageId) : '';
        $targetExists = url_to_postid($ctaUrl) > 0
            || (
                $shopUrl !== ''
                && untrailingslashit($ctaUrl) === untrailingslashit($shopUrl)
            )
            || ($categorySlug !== '' && get_term_by('slug', $categorySlug, 'product_cat') instanceof WP_Term);
        if (!$targetExists) {
            $failures[] = 'CTA do hero não aponta para conteúdo cadastrado';
        }
    }
}
if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 7) {
    $failures[] = 'Schema da Home anterior ao hero full-bleed';
}

$fatherTerm = get_term_by('slug', 'dia-dos-pais', 'product_cat');
if (
    !$fatherTerm instanceof WP_Term
    || !(bool) get_term_meta($fatherTerm->term_id, 'petshop_seasonal', true)
    || !(bool) get_term_meta($fatherTerm->term_id, 'petshop_visible_in_menu', true)
) {
    $failures[] = 'Categoria Dia dos Pais não está sazonal e visível';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('004b inválido: ' . count($failures) . ' falha(s).');
}

WP_CLI::success(
    sprintf(
        '004b válido: %d produtos, %d categorias-alvo, %d imagens e hero full-bleed editável.',
        count($manifest['products']),
        count($categoryTargets),
        count($manifest['images'])
    )
);
