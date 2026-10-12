<?php
declare(strict_types=1);
defined('ABSPATH') || exit;
if (get_option('petshop_gate_042_editor')) {
    WP_CLI::error('042 editor fixture exists; cleanup required.');
}
$login = 'gate042-editor-' . strtolower(wp_generate_password(10, false));
$password = wp_generate_password(28, true);
$userId = wp_insert_user(['user_login' => $login, 'user_pass' => $password, 'user_email' => $login . '@example.invalid', 'role' => 'editor']);
if (is_wp_error($userId)) {
    WP_CLI::error('Unable to create disposable editor.');
}
$fixture = ['user_id' => $userId, 'page_id' => 0];
update_option('petshop_gate_042_editor', $fixture, false);
$pageId = wp_insert_post([
    'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Synthetic login 042',
    'post_author' => $userId, 'post_content' => wp_slash(get_post_field('post_content', wc_get_page_id('myaccount'))),
]);
if (is_wp_error($pageId) || !$pageId) {
    WP_CLI::error('Unable to create disposable editor page.');
}
update_post_meta($pageId, '_petshop_gate_042_editor', 1);
$fixture['page_id'] = $pageId;
update_option('petshop_gate_042_editor', $fixture, false);
echo wp_json_encode(['login' => $login, 'password' => $password, 'pageId' => $pageId]);
