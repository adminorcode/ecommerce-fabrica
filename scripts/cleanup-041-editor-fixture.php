<?php
defined('ABSPATH') || exit;
require_once ABSPATH . 'wp-admin/includes/user.php';
$fixture = get_option('petshop_gate_041_editor', []);
$page = (int) ($fixture['page_id'] ?? 0);
if ($page && get_post_meta($page, '_petshop_gate_041_editor', true) === '1') wp_delete_post($page, true);
foreach ($fixture['media_ids'] ?? [] as $id) {
    if (get_post_meta($id, '_petshop_gate_041_editor', true) === '1') wp_delete_attachment($id, true);
}
$user = get_userdata((int) ($fixture['user_id'] ?? 0));
if ($user && str_starts_with($user->user_login, 'gate041-editor-')) wp_delete_user($user->ID);
delete_option('petshop_gate_041_editor');
WP_CLI::success('041 synthetic editor, page and media removed');
