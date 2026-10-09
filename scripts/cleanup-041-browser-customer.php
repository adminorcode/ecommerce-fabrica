<?php

defined('ABSPATH') || exit;
require_once ABSPATH . 'wp-admin/includes/user.php';
$id = (int) get_option('petshop_gate_041_customer', 0);
$user = get_userdata($id);
if ($user && str_starts_with($user->user_login, 'gate041-browser-')) wp_delete_user($id);
delete_option('petshop_gate_041_customer');
require_once __DIR__ . '/lib/041-performance-fixtures.php';
petshop041CleanupPerformanceProducts();
WP_CLI::success('041 synthetic customer removed.');
