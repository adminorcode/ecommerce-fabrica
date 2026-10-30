<?php

declare(strict_types=1);

use Petshop\Core\WooCommerce\AccountLoginContent;

if (!defined('ABSPATH') || !class_exists('WooCommerce')) {
    throw new RuntimeException('042 requires the local WordPress/WooCommerce runtime.');
}

// Cleanup targets only the disposable customer explicitly named by the browser gate.
if (($args[0] ?? '') === 'cleanup') {
    $email = (string) ($args[1] ?? '');
    if (!preg_match('/^petshop042-[0-9]+@example\.invalid$/', $email)) {
        throw new RuntimeException('Invalid disposable fixture email.');
    }
    $user = get_user_by('email', $email);
    if ($user) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user->ID);
    }
    echo "042 disposable browser fixture cleaned.\n";
    return;
}

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
AccountLoginContent::provision();
$pageId = wc_get_page_id('myaccount');
$original = (string) get_post_field('post_content', $pageId);
$version = get_post_meta($pageId, '_petshop_account_login_042', true);
$check(str_contains($original, 'petshop-account-scene'), 'Scene not provisioned.');
$check(substr_count($original, '[woocommerce_my_account]') === 1, 'Authentication shortcode must be unique.');
$check(str_contains($original, 'wp:heading') && str_contains($original, 'wp:image'), 'Native editorial blocks absent.');
$blocks = parse_blocks($original);
$check(parse_blocks(serialize_blocks($blocks)) === $blocks, 'Block serialization round trip changed structure.');
$images = get_posts(['post_type' => 'attachment', 'meta_key' => '_petshop_login_art_042', 'meta_value' => 1, 'fields' => 'ids']);
$check(count($images) === 1, 'Artwork imported more than once.');
$imagePath = get_attached_file($images[0]);
$check(filesize($imagePath) <= 350000, 'Artwork exceeds 350 KB.');
$query = $GLOBALS['wp_query'];
$currentUser = get_current_user_id();
$userId = 0;
try {
    // Simulate a merchant changing all editorial categories and the image alt/source.
    $edited = str_replace([
        'Acesse sua conta para acompanhar pedidos e favoritos.',
        'Acompanhe seus pedidos', 'Mais estilo para quem você ama',
        'Cachorro e gato usando acessórios em tons de verde e laranja.',
    ], [
        'Introdução editada pelo cliente 042.', 'Benefício editado 042.',
        'Frase editada 042.', 'Descrição alternativa editada 042.',
    ], $original);
    $replacementImage = wp_get_attachment_url($images[0]) . '?editorial-042=1';
    $edited = str_replace((string) wp_get_attachment_url($images[0]), $replacementImage, $edited);
    wp_update_post(['ID' => $pageId, 'post_content' => wp_slash($edited)]);
    $edited = (string) get_post_field('post_content', $pageId);
    delete_post_meta($pageId, '_petshop_account_login_042');
    AccountLoginContent::provision();
    AccountLoginContent::provision();
    $check(get_post_field('post_content', $pageId) === $edited, 'Reprovision overwrote merchant edits.');
    $GLOBALS['wp_query'] = new WP_Query(['page_id' => $pageId]);
    wp_set_current_user(0);
    $html = apply_filters('the_content', $edited);
    $check(str_contains($html, 'Introdução editada') && str_contains($html, 'Descrição alternativa editada'), 'Edited content not rendered.');
    $check(str_contains($html, '?editorial-042=1'), 'Edited media source not rendered.');
    $check(str_contains($html, 'loading="eager"') && str_contains($html, 'width="1672"'), 'Artwork loading/dimensions missing.');

    $login = 'gate042_' . strtolower(wp_generate_password(10, false));
    $password = wp_generate_password(28, true);
    $userId = wp_create_user($login, $password, $login . '@example.invalid');
    $check(is_int($userId), 'Cannot create disposable reset fixture.');
    $check(wp_authenticate($login, $password) instanceof WP_User, 'Username authentication failed.');
    $check(wp_authenticate($login . '@example.invalid', $password) instanceof WP_User, 'Email authentication failed.');
    $user = get_user_by('id', $userId);
    $key = get_password_reset_key($user);
    $check(is_string($key) && check_password_reset_key($key, $login) instanceof WP_User, 'Native reset key failed.');
    $replacementPassword = wp_generate_password(28, true);
    reset_password($user, $replacementPassword);
    $check(wp_authenticate($login, $replacementPassword) instanceof WP_User, 'Password reset authentication failed.');
    $check(is_wp_error(check_password_reset_key($key, $login)), 'Reset key remained reusable.');
    wp_set_current_user($userId);
    $logged = do_shortcode(do_blocks($edited));
    $check(!str_contains($logged, 'petshop-account-scene') && str_contains($logged, 'woocommerce-MyAccount-navigation'), 'Authenticated view regression.');
    echo "042 PHP: native blocks, media, persistence twice, authentication and password reset passed.\n";
} finally {
    wp_set_current_user($currentUser);
    $GLOBALS['wp_query'] = $query;
    wp_update_post(['ID' => $pageId, 'post_content' => wp_slash($original)]);
    update_post_meta($pageId, '_petshop_account_login_042', $version);
    if (is_int($userId) && $userId > 0) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }
}
