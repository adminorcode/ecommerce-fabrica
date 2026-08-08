<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta auditoria com WP-CLI e WooCommerce ativo.');
}

$products = wc_get_products(['status' => 'publish', 'limit' => -1]);
if ($products === []) {
    WP_CLI::error('Nenhum produto publicado para auditar.');
}

$failures = [];
foreach ($products as $product) {
    $name = $product->get_name();
    if ($product->get_sku() === '') {
        $failures[] = "Produto sem SKU: {$name}";
    }
    if ((float) $product->get_price() <= 0) {
        $failures[] = "Produto sem preço válido: {$name}";
    }
    if ($product->get_category_ids() === []) {
        $failures[] = "Produto sem categoria: {$name}";
    }
    if ($product->get_image_id() <= 0) {
        $failures[] = "Produto sem imagem: {$name}";
        continue;
    }
    if ((string) get_post_meta($product->get_image_id(), '_wp_attachment_image_alt', true) === '') {
        $failures[] = "Produto sem texto alternativo: {$name}";
    }
    if (trim($product->get_short_description() . $product->get_description()) === '') {
        $failures[] = "Produto sem descrição comercial: {$name}";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Auditoria editorial reprovada: ' . count($failures) . ' pendência(s).');
}

WP_CLI::success('Auditoria editorial aprovada para ' . count($products) . ' produto(s) publicado(s).');
