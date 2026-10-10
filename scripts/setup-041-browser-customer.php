<?php

defined('ABSPATH') || exit;
$existing = get_option('petshop_gate_041_customer', 0);
if ($existing && get_userdata((int) $existing)) WP_CLI::error('Previous 041 test customer requires cleanup before creating another fixture.');
$login = 'gate041-browser-' . strtolower(wp_generate_password(10, false));
$password = wp_generate_password(28, false);
$id = wp_insert_user(['user_login' => $login, 'user_pass' => $password, 'user_email' => $login . '@example.test', 'role' => 'customer']);
if (is_wp_error($id)) WP_CLI::error('Unable to create 041 customer fixture');
$customer = new WC_Customer($id);
foreach (['billing', 'shipping'] as $group) {
    foreach (['country' => 'BR', 'state' => 'SP', 'postcode' => '01310100', 'city' => 'Sao Paulo', 'address_1' => 'Synthetic street 041', 'address_2' => 'Synthetic complement 041'] as $field => $value) {
        $setter = 'set_' . $group . '_' . $field;
        $customer->{$setter}($value);
    }
}
$customer->save();
update_option('petshop_gate_041_customer', $id, false);
require_once __DIR__ . '/lib/041-performance-fixtures.php';
$products = petshop041PerformanceProducts();
echo wp_json_encode(['id' => $id, 'login' => $login, 'password' => $password, 'products' => $products]);
