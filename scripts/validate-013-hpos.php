<?php

defined('ABSPATH') || exit(1);
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('Execute este teste com WP-CLI.');

$order = wc_create_order(['status' => 'pending', 'created_via' => 'petshop-plan-013-validation']);
if (is_wp_error($order) || !$order instanceof WC_Order) WP_CLI::error('Nao foi possivel criar o pedido temporario.');

$orderId = $order->get_id();
$passed = false;
$originalUserId = get_current_user_id();
$originalPost = $_POST;
try {
    $order->set_billing_email('validation-013@example.test');
    $order->save();

    $adminIds = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ids']);
    if ($adminIds === []) WP_CLI::error('Administrador indisponivel para validar a metabox.');
    wp_set_current_user((int) $adminIds[0]);
    $_POST = [
        '_petshop_tracking_nonce' => wp_create_nonce('petshop_save_tracking_' . $orderId),
        '_petshop_tracking_carrier' => 'Transportadora de teste',
        '_petshop_tracking_code' => 'PLAN013-TRACK',
        '_petshop_tracking_url' => 'javascript:alert(1)',
    ];
    Petshop\Core\WooCommerce\OrderTracking::saveMetaBox($orderId);

    wc_delete_shop_order_transients($orderId);
    $reloaded = wc_get_order($orderId);
    ob_start();
    if ($reloaded instanceof WC_Order) Petshop\Core\WooCommerce\OrderTracking::renderForCustomer($reloaded);
    $rendered = (string) ob_get_clean();
    $passed = $reloaded instanceof WC_Order
        && $reloaded->get_meta('_petshop_tracking_carrier', true) === 'Transportadora de teste'
        && $reloaded->get_meta('_petshop_tracking_code', true) === 'PLAN013-TRACK'
        && $reloaded->get_meta('_petshop_tracking_url', true) === ''
        && str_contains($rendered, 'Transportadora de teste')
        && str_contains($rendered, 'PLAN013-TRACK')
        && !str_contains($rendered, 'javascript:');
} finally {
    $_POST = $originalPost;
    wp_set_current_user($originalUserId);
    $cleanup = wc_get_order($orderId);
    if ($cleanup instanceof WC_Order) $cleanup->delete(true);
}

if (!$passed) WP_CLI::error('CRUD de rastreamento nao persistiu os metadados do pedido.');
$enabled = class_exists(Automattic\WooCommerce\Utilities\OrderUtil::class)
    && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
WP_CLI::success('Rastreamento validado exclusivamente via WC_Order CRUD; HPOS ' . ($enabled ? 'ativo.' : 'compativel, mas inativo neste ambiente.'));
