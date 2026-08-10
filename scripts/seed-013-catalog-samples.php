<?php

defined('ABSPATH') || exit(1);
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('Execute este seed com WP-CLI.');
if (!class_exists('WooCommerce')) WP_CLI::error('WooCommerce não está ativo.');
if (!in_array(wp_get_environment_type(), ['local', 'development'], true) && getenv('PETSHOP_ALLOW_FIXTURE_SEED') !== '1') {
    WP_CLI::error('Fixtures do Plano 013 só podem ser criadas em ambiente local/desenvolvimento ou com opt-in explícito.');
}

$attachmentIds = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'post_mime_type' => 'image',
    'posts_per_page' => 3,
    'fields' => 'ids',
    'meta_query' => [['key' => '_petshop_placeholder_key', 'compare' => 'EXISTS']],
]);
if (count($attachmentIds) < 3) WP_CLI::error('São necessárias três imagens da Biblioteca de mídia para as amostras.');

$category = get_term_by('slug', 'bandanas', 'product_cat');
if (!$category instanceof WP_Term) WP_CLI::error('Categoria bandanas ausente.');

$ensureAttribute = static function (string $slug, string $label, array $terms): array {
    $taxonomy = wc_attribute_taxonomy_name($slug);
    $attributeId = wc_attribute_taxonomy_id_by_name($slug);
    if ($attributeId <= 0) {
        $attributeId = wc_create_attribute(['name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
        if (is_wp_error($attributeId)) WP_CLI::error($attributeId->get_error_message());
        delete_transient('wc_attribute_taxonomies');
    }
    if (!taxonomy_exists($taxonomy)) register_taxonomy($taxonomy, ['product'], ['hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false]);
    $termIds = [];
    foreach ($terms as $termSlug => $termLabel) {
        $term = get_term_by('slug', $termSlug, $taxonomy);
        if (!$term instanceof WP_Term) {
            $created = wp_insert_term($termLabel, $taxonomy, ['slug' => $termSlug]);
            if (is_wp_error($created)) WP_CLI::error($created->get_error_message());
            $termIds[$termSlug] = (int) $created['term_id'];
        } else {
            $termIds[$termSlug] = (int) $term->term_id;
        }
    }
    return ['id' => (int) $attributeId, 'taxonomy' => $taxonomy, 'terms' => $termIds];
};

$color = $ensureAttribute('color', 'Cor', ['azul' => 'Azul', 'coral' => 'Coral']);
$size = $ensureAttribute('size', 'Tamanho', ['p' => 'P', 'm' => 'M']);
foreach (['azul' => '#2b6cb0', 'coral' => '#ff6f61'] as $slug => $value) {
    $termId = (int) ($color['terms'][$slug] ?? 0);
    if ($termId > 0 && get_term_meta($termId, '_petshop_color_value', true) === '') update_term_meta($termId, '_petshop_color_value', $value);
}

$baseData = static function (WC_Product $product, string $name, string $sku, int $imageId) use ($category): void {
    $product->set_name($name);
    $product->set_sku($sku);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_category_ids([(int) $category->term_id]);
    $product->set_image_id($imageId);
    $product->set_description('Amostra administrável do Plano 013 para validar conteúdo, logística e experiência de compra.');
    $product->set_short_description('Produto de demonstração com cadastro completo e editável no WooCommerce.');
    $product->set_manage_stock(true);
    $product->set_stock_quantity(20);
    $product->set_stock_status('instock');
    $product->update_meta_data('_petshop_fixture_013', '1');
    $product->update_meta_data('_petshop_production_lead', '2 a 4 dias úteis');
    $product->update_meta_data('_petshop_materials', 'Tecido e acabamento descritos para a amostra.');
    $product->update_meta_data('_petshop_contents', '1 acessório.');
    $product->update_meta_data('_petshop_care', 'Limpar suavemente e secar à sombra.');
    $product->update_meta_data('_petshop_measurements', 'Confira as medidas cadastradas antes da compra.');
};

$created = [];
if (wc_get_product_id_by_sku('PLAN013-SIMPLE') <= 0) {
    $simple = new WC_Product_Simple();
    $baseData($simple, 'Bandana Essencial — amostra Plano 013', 'PLAN013-SIMPLE', (int) $attachmentIds[0]);
    $simple->set_regular_price('39.90');
    $simple->save();
    $created[] = $simple->get_id();
}

if (wc_get_product_id_by_sku('PLAN013-VARIABLE') <= 0) {
    $variable = new WC_Product_Variable();
    $baseData($variable, 'Bandana com variações — amostra Plano 013', 'PLAN013-VARIABLE', (int) $attachmentIds[1]);
    $attributes = [];
    foreach ([$color, $size] as $definition) {
        $attribute = new WC_Product_Attribute();
        $attribute->set_id($definition['id']);
        $attribute->set_name($definition['taxonomy']);
        $attribute->set_options(array_values($definition['terms']));
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $attributes[] = $attribute;
    }
    $variable->set_attributes($attributes);
    $variable->save();
    foreach ([['azul', 'p', '44.90'], ['coral', 'm', '49.90']] as $index => [$colorSlug, $sizeSlug, $price]) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($variable->get_id());
        $variation->set_sku('PLAN013-VAR-' . strtoupper($colorSlug . '-' . $sizeSlug));
        $variation->set_attributes(['pa_color' => $colorSlug, 'pa_size' => $sizeSlug]);
        $variation->set_regular_price($price);
        $variation->set_manage_stock(true);
        $variation->set_stock_quantity(10);
        $variation->set_stock_status('instock');
        $variation->set_image_id((int) $attachmentIds[min($index + 1, 2)]);
        $variation->update_meta_data('_petshop_production_lead', $index === 0 ? '2 dias úteis' : '4 dias úteis');
        $variation->save();
    }
    WC_Product_Variable::sync($variable->get_id());
    $created[] = $variable->get_id();
}

if (wc_get_product_id_by_sku('PLAN012-READY') <= 0) {
    $prepared = new WC_Product_Simple();
    $baseData($prepared, 'Plaquinha preparada para personalização — amostra', 'PLAN012-READY', (int) $attachmentIds[2]);
    $prepared->set_regular_price('59.90');
    $prepared->update_meta_data('_petshop_personalization_ready', 'yes');
    $prepared->save();
    $created[] = $prepared->get_id();
}

WP_CLI::success('Amostras do Plano 013 disponíveis. Criadas nesta execução: ' . count($created) . '.');
