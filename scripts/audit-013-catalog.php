<?php

defined('ABSPATH') || exit(1);
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('Execute esta auditoria com WP-CLI.');

$issues = [];
$products = wc_get_products(['status' => 'publish', 'limit' => -1, 'return' => 'objects']);
foreach ($products as $product) {
    if (!$product instanceof WC_Product) continue;
    $productIssues = [];
    if (trim($product->get_sku()) === '') $productIssues[] = 'SKU ausente';
    if ($product->get_price() === '') $productIssues[] = 'preço ausente';
    if ($product->get_category_ids() === []) $productIssues[] = 'categoria ausente';
    if ($product->get_image_id() <= 0) $productIssues[] = 'imagem ausente';
    elseif (trim((string) get_post_meta($product->get_image_id(), '_wp_attachment_image_alt', true)) === '') $productIssues[] = 'alt da imagem ausente';
    if (trim($product->get_description()) === '') $productIssues[] = 'descrição ausente';
    if ($product->is_type('variable') && $product->get_children() === []) $productIssues[] = 'variações ausentes';
    if ($productIssues !== []) $issues[] = ['id' => $product->get_id(), 'sku' => $product->get_sku(), 'name' => $product->get_name(), 'issues' => $productIssues];
}

WP_CLI::log(wp_json_encode(['products' => count($products), 'products_with_issues' => count($issues), 'issues' => $issues], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
WP_CLI::success('Auditoria concluída; inconsistências são relatório operacional e não foram alteradas automaticamente.');
