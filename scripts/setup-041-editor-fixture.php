<?php
defined('ABSPATH') || exit;
if (get_option('petshop_gate_041_editor')) WP_CLI::error('041 editor fixture already exists; cleanup required');
$login = 'gate041-editor-' . strtolower(wp_generate_password(10, false));
$password = wp_generate_password(28, false);
$userId = wp_insert_user(['user_login' => $login, 'user_pass' => $password, 'user_email' => $login . '@example.test', 'role' => 'editor']);
if (is_wp_error($userId)) WP_CLI::error('Unable to create synthetic editor');
$fixture = ['user_id' => $userId, 'media_ids' => [], 'page_id' => 0];
update_option('petshop_gate_041_editor', $fixture, false);
foreach (['a', 'b'] as $suffix) {
    $upload = wp_upload_bits('gate041-editor-' . $suffix . '.png', null, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aH9sAAAAASUVORK5CYII='));
    if (!empty($upload['error'])) WP_CLI::error('Unable to create synthetic media');
    $id = wp_insert_attachment(['post_title' => 'Synthetic editor image 041', 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'post_author' => $userId], $upload['file']);
    if (is_wp_error($id) || !$id) {
        wp_delete_file($upload['file']);
        WP_CLI::error('Unable to register synthetic media');
    }
    update_post_meta($id, '_petshop_gate_041_editor', '1');
    $fixture['media_ids'][] = $id;
    update_option('petshop_gate_041_editor', $fixture, false);
}
$cart = (int) get_option('woocommerce_cart_page_id');
$content = (string) get_post_field('post_content', $cart);
$content .= serialize_block(['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>Synthetic editable paragraph 041</p>', 'innerContent' => ['<p>Synthetic editable paragraph 041</p>']]);
$page = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Synthetic Gutenberg 041', 'post_author' => $userId, 'post_content' => $content]);
if (is_wp_error($page) || !$page) WP_CLI::error('Unable to create synthetic editor page');
update_post_meta($page, '_petshop_gate_041_editor', '1');
$fixture['page_id'] = $page;
update_option('petshop_gate_041_editor', $fixture, false);
echo wp_json_encode(['login' => $login, 'password' => $password, 'pageId' => $page,
    'media' => array_map(static fn($id) => ['id' => $id, 'url' => wp_get_attachment_url($id)], $fixture['media_ids'])]);
