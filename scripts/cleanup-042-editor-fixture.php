<?php
declare(strict_types=1);
defined('ABSPATH') || exit;
$fixture = get_option('petshop_gate_042_editor');
if (!is_array($fixture)) {
    return;
}
$pageId = (int) ($fixture['page_id'] ?? 0);
if ($pageId > 0 && get_post_meta($pageId, '_petshop_gate_042_editor', true)) {
    wp_delete_post($pageId, true);
}
$user = get_user_by('id', (int) ($fixture['user_id'] ?? 0));
if ($user && str_starts_with($user->user_login, 'gate042-editor-')) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($user->ID);
}
delete_option('petshop_gate_042_editor');
echo "042 editor fixtures cleaned.\n";
