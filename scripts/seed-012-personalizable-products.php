<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute este seed com WP-CLI e WooCommerce ativo.');
}

use Petshop\Core\Personalization\Infrastructure\ProductSettings;

$ensureAttachment = static function (string $basename, int $r, int $g, int $b): int {
    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => '_petshop_012_fixture',
        'meta_value' => $basename,
    ]);
    if (is_array($existing) && isset($existing[0])) {
        return (int) $existing[0];
    }

    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('GD necessário para gerar mockups de teste.');
    }

    $image = imagecreatetruecolor(600, 600);
    $bg = imagecolorallocate($image, $r, $g, $b);
    imagefill($image, 0, 0, $bg);
    $fg = imagecolorallocate($image, 255, 255, 255);
    imagestring($image, 5, 180, 290, $basename, $fg);

    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        throw new RuntimeException((string) $uploads['error']);
    }

    $filename = trailingslashit($uploads['path']) . $basename . '-' . wp_generate_password(6, false) . '.png';
    if (!imagepng($image, $filename)) {
        imagedestroy($image);
        throw new RuntimeException('Falha ao gravar mockup PNG.');
    }
    imagedestroy($image);

    $attachmentId = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => $basename,
        'post_status' => 'inherit',
        'post_content' => '',
    ], $filename);

    if (is_wp_error($attachmentId) || $attachmentId <= 0) {
        throw new RuntimeException('Falha ao criar attachment.');
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata((int) $attachmentId, $filename);
    if (is_array($metadata) && $metadata !== []) {
        wp_update_attachment_metadata((int) $attachmentId, $metadata);
    }
    update_post_meta((int) $attachmentId, '_petshop_012_fixture', $basename);

    return (int) $attachmentId;
};

$ensureProduct = static function (array $spec, int $mockupId): int {
    $productId = wc_get_product_id_by_sku($spec['sku']);
    $product = $productId > 0 ? wc_get_product($productId) : new WC_Product_Simple();
    if (!$product instanceof WC_Product) {
        throw new RuntimeException('Produto inválido para ' . $spec['sku']);
    }

    $product->set_name($spec['name']);
    $product->set_sku($spec['sku']);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_regular_price('49.90');
    $product->set_price('49.90');
    $product->set_manage_stock(false);
    $product->set_stock_status('instock');
    $product->set_image_id($mockupId);
    $product->set_description('Fixture administrável do Plano 012. Substitua mockup e textos no produto.');
    $savedId = (int) $product->save();

    $term = get_term_by('slug', $spec['category'], 'product_cat');
    if ($term instanceof WP_Term) {
        wp_set_object_terms($savedId, [(int) $term->term_id], 'product_cat', false);
    }

    update_post_meta($savedId, ProductSettings::META_ENABLED, 'yes');
    update_post_meta($savedId, ProductSettings::META_INSTRUCTION, 'Ajuste o texto e a imagem dentro da área marcada.');
    update_post_meta($savedId, ProductSettings::META_MOCKUP_ID, $mockupId);
    update_post_meta($savedId, ProductSettings::META_WIDTH_MM, (string) $spec['width']);
    update_post_meta($savedId, ProductSettings::META_HEIGHT_MM, (string) $spec['height']);
    update_post_meta($savedId, ProductSettings::META_DPI, (string) $spec['dpi']);
    update_post_meta($savedId, ProductSettings::META_ALLOW_TEXT, 'yes');
    update_post_meta($savedId, ProductSettings::META_ALLOW_IMAGE, $spec['allow_image'] ? 'yes' : 'no');
    update_post_meta($savedId, ProductSettings::META_MAX_TEXT_BOXES, '2');
    update_post_meta($savedId, ProductSettings::META_FONTS, ProductSettings::DEFAULT_FONTS);
    update_post_meta($savedId, ProductSettings::META_COLORS, ProductSettings::DEFAULT_COLORS);

    return $savedId;
};

$catalog = [
    [
        'sku' => 'PLAN012-BANDANA',
        'name' => 'Bandana personalizável — amostra 012',
        'width' => 280.0,
        'height' => 280.0,
        'dpi' => 150,
        'allow_image' => false,
        'category' => 'bandanas',
        'mockup' => '012-bandana-mockup',
        'rgb' => [23, 103, 106],
    ],
    [
        'sku' => 'PLAN012-LACO',
        'name' => 'Laço personalizável — amostra 012',
        'width' => 80.0,
        'height' => 50.0,
        'dpi' => 150,
        'allow_image' => false,
        'category' => 'lacos',
        'mockup' => '012-laco-mockup',
        'rgb' => [226, 112, 58],
    ],
    [
        'sku' => 'PLAN012-ADESIVO',
        'name' => 'Adesivo personalizável — amostra 012',
        'width' => 100.0,
        'height' => 100.0,
        'dpi' => 300,
        'allow_image' => true,
        'category' => 'acessorios',
        'mockup' => '012-adesivo-mockup',
        'rgb' => [70, 70, 70],
    ],
];

$ids = [];
foreach ($catalog as $item) {
    $mockupId = $ensureAttachment($item['mockup'], $item['rgb'][0], $item['rgb'][1], $item['rgb'][2]);
    $ids[$item['sku']] = $ensureProduct($item, $mockupId);
}

$legacyId = wc_get_product_id_by_sku('PLAN012-READY');
if ($legacyId > 0) {
    update_post_meta($legacyId, ProductSettings::META_ENABLED, 'yes');
    update_post_meta($legacyId, ProductSettings::META_WIDTH_MM, '100');
    update_post_meta($legacyId, ProductSettings::META_HEIGHT_MM, '100');
    update_post_meta($legacyId, ProductSettings::META_DPI, '150');
    update_post_meta($legacyId, ProductSettings::META_ALLOW_TEXT, 'yes');
    update_post_meta($legacyId, ProductSettings::META_ALLOW_IMAGE, 'yes');
    $ids['PLAN012-READY'] = $legacyId;
}

\Petshop\Core\Personalization\PersonalizationModule::maybeMigrate();

WP_CLI::success('Fixtures Plano 012: ' . wp_json_encode($ids));
