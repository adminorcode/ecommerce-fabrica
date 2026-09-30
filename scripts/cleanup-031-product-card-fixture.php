<?php

if (!defined('ABSPATH')) {
    exit;
}

$signature = '_petshop_plan_031_fixture';
$homeSnapshotOption = 'petshop_plan_031_home_snapshot';
$comingSoonSnapshotOption = 'petshop_plan_031_coming_soon_snapshot';

$restore_option = static function (string $name, mixed $value): void {
    if ($value === null) {
        delete_option($name);
        return;
    }

    update_option($name, $value, false);
};

$comingSoonSnapshot = get_option($comingSoonSnapshotOption, null);
if (is_array($comingSoonSnapshot)) {
    $restore_option('woocommerce_coming_soon', $comingSoonSnapshot['woocommerce_coming_soon'] ?? null);
    $restore_option('woocommerce_store_pages_only', $comingSoonSnapshot['woocommerce_store_pages_only'] ?? null);
    delete_option($comingSoonSnapshotOption);
}

$homeId = (int) get_option('page_on_front');
$snapshot = get_option($homeSnapshotOption, null);
if ($homeId > 0 && is_string($snapshot)) {
    wp_update_post([
        'ID' => $homeId,
        'post_content' => $snapshot,
    ]);
}
delete_option($homeSnapshotOption);

$products = get_posts([
    'post_type' => ['product', 'product_variation'],
    'post_status' => 'any',
    'meta_key' => $signature,
    'meta_value' => '1',
    'fields' => 'ids',
    'posts_per_page' => -1,
]);

foreach ($products as $productId) {
    wp_delete_post((int) $productId, true);
}

$attachments = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'meta_key' => $signature,
    'fields' => 'ids',
    'posts_per_page' => -1,
]);

foreach ($attachments as $attachmentId) {
    wp_delete_attachment((int) $attachmentId, true);
}

$category = get_term_by('slug', 'plano-031-card-variavel', 'product_cat');
if ($category instanceof WP_Term && (string) get_term_meta((int) $category->term_id, $signature, true) === '1') {
    wp_delete_term((int) $category->term_id, 'product_cat');
}

delete_option('petshop_plan_031_fixture');

WP_CLI::success('Fixture 031 removido.');
