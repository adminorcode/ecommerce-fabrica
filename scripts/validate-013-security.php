<?php

defined('ABSPATH') || exit(1);
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('Execute este teste com WP-CLI.');

$order = wc_create_order(['status' => 'pending', 'created_via' => 'petshop-plan-013-security']);
if (is_wp_error($order) || !$order instanceof WC_Order) WP_CLI::error('Nao foi possivel criar o pedido temporario.');

$orderId = $order->get_id();
$passed = false;
try {
    $order->set_billing_email('security-013@example.test');
    $order->set_total(73.40);
    $order->save();
    $key = $order->get_order_key();

    $checks = [
        'analytics sem chave' => Petshop\Core\Analytics\FunnelEvents::purchaseData($orderId, '', 0) === [],
        'analytics chave incorreta' => Petshop\Core\Analytics\FunnelEvents::purchaseData($orderId, 'chave-incorreta', 0) === [],
        'analytics autorizado' => Petshop\Core\Analytics\FunnelEvents::purchaseData($orderId, $key, 0)['value'] === 73.4,
        'conta sem email' => !Petshop\Core\WooCommerce\GuestAccount::requestMatchesOrder($order, $key, ''),
        'conta email incorreto' => !Petshop\Core\WooCommerce\GuestAccount::requestMatchesOrder($order, $key, 'outra@example.test'),
        'conta chave incorreta' => !Petshop\Core\WooCommerce\GuestAccount::requestMatchesOrder($order, 'chave-incorreta', 'security-013@example.test'),
        'conta autorizada' => Petshop\Core\WooCommerce\GuestAccount::requestMatchesOrder($order, $key, 'SECURITY-013@example.test'),
    ];
    $order->set_status('failed');
    $order->save();
    $checks['conta status inelegivel'] = !Petshop\Core\WooCommerce\GuestAccount::requestMatchesOrder($order, $key, 'security-013@example.test');
    $failed = array_keys(array_filter($checks, static fn (bool $check): bool => !$check));
    $passed = $failed === [];
} finally {
    $cleanup = wc_get_order($orderId);
    if ($cleanup instanceof WC_Order) $cleanup->delete(true);
}

if (!$passed) WP_CLI::error('Validacao de seguranca 013 falhou para: ' . implode(', ', $failed ?? []) . '.');
WP_CLI::success('Order key, confirmacao de email e status elegivel validados sem exposicao de pedido.');
