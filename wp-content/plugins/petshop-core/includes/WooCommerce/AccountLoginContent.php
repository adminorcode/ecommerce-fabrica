<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

/** Editorial blocks surround, but never replace, WooCommerce authentication. */
final class AccountLoginContent
{
    private const VERSION = 1;
    private const META = '_petshop_account_login_042';

    public static function bootstrap(): void
    {
        add_action('admin_init', [self::class, 'maybeProvision'], 45);
        add_filter('render_block_data', [self::class, 'filterComposition'], 10, 1);
        add_filter('wp_get_attachment_image_attributes', [self::class, 'imageAttributes'], 10, 2);
        add_filter('render_block_core/image', [self::class, 'renderImage'], 10, 2);
        add_filter('gettext', [self::class, 'loginLabel'], 10, 3);
    }

    public static function maybeProvision(): void
    {
        if (current_user_can('manage_options') || (defined('WP_CLI') && WP_CLI)) {
            self::provision();
        }
    }

    public static function provision(): void
    {
        if (!function_exists('wc_get_page_id')) {
            return;
        }
        $pageId = wc_get_page_id('myaccount');
        $page = get_post($pageId);
        if (!$page instanceof \WP_Post || (int) get_post_meta($pageId, self::META, true) >= self::VERSION) {
            return;
        }
        // Never rebuild a merchant-edited composition, even if its version meta is lost.
        if (str_contains($page->post_content, 'petshop-account-scene')) {
            update_post_meta($pageId, self::META, self::VERSION);
            return;
        }
        $shortcode = '<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->';
        $position = strpos($page->post_content, $shortcode);
        if ($position === false && trim($page->post_content) !== '[woocommerce_my_account]') {
            // A different page composition needs deliberate migration, never a destructive reset.
            return;
        }
        $imageId = self::importImage();
        if ($imageId <= 0) {
            return;
        }
        $scene = self::initialContent($imageId);
        $content = $position === false ? $scene : substr_replace($page->post_content, $scene, $position, strlen($shortcode));
        $result = wp_update_post(['ID' => $pageId, 'post_content' => wp_slash($content)], true);
        if (is_wp_error($result)) {
            error_log('Petshop login 042: ' . $result->get_error_message());
            return;
        }
        update_post_meta($pageId, self::META, self::VERSION);
    }

    private static function importImage(): int
    {
        $existing = get_posts([
            'post_type' => 'attachment', 'post_status' => 'inherit',
            'meta_key' => '_petshop_login_art_042', 'meta_value' => '1',
            'fields' => 'ids', 'posts_per_page' => 1,
        ]);
        if ($existing !== []) {
            return (int) $existing[0];
        }
        $source = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/images/account-login.webp';
        if (!is_readable($source)) {
            return 0;
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $temp = wp_tempnam('account-login.webp');
        if (!$temp || !copy($source, $temp)) {
            if ($temp) {
                wp_delete_file($temp);
            }
            return 0;
        }
        $id = media_handle_sideload(['name' => 'autelie-login-pets.webp', 'tmp_name' => $temp], 0, 'Pets Auteliê — Minha conta');
        if (is_wp_error($id)) {
            wp_delete_file($temp);
            error_log('Petshop login 042: ' . $id->get_error_message());
            return 0;
        }
        update_post_meta($id, '_petshop_login_art_042', 1);
        update_post_meta($id, '_wp_attachment_image_alt', 'Cachorro e gato usando acessórios em tons de verde e laranja.');
        return $id;
    }

    public static function initialContent(int $imageId): string
    {
        $url = esc_url((string) wp_get_attachment_image_url($imageId, 'full'));
        $alt = esc_attr((string) get_post_meta($imageId, '_wp_attachment_image_alt', true));
        $benefits = '';
        foreach ([
            'orders' => 'Acompanhe seus pedidos',
            'favorites' => 'Salve seus produtos favoritos',
            'details' => 'Gerencie seus dados com facilidade',
            'offers' => 'Receba ofertas exclusivas',
        ] as $icon => $text) {
            $benefits .= '<!-- wp:paragraph {"className":"petshop-account-benefit petshop-account-benefit--' . $icon . '"} -->'
                . '<p class="petshop-account-benefit petshop-account-benefit--' . $icon . '">' . esc_html($text) . '</p><!-- /wp:paragraph -->';
        }
        return <<<HTML
<!-- wp:group {"className":"petshop-account-scene"} -->
<div class="wp-block-group petshop-account-scene"><!-- wp:group {"className":"petshop-account-card"} -->
<div class="wp-block-group petshop-account-card"><!-- wp:heading {"level":1,"className":"petshop-account-title"} -->
<h1 class="wp-block-heading petshop-account-title">Entrar</h1>
<!-- /wp:heading --><!-- wp:paragraph {"className":"petshop-account-intro"} -->
<p class="petshop-account-intro">Acesse sua conta para acompanhar pedidos e favoritos.</p>
<!-- /wp:paragraph --><!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode --></div>
<!-- /wp:group --><!-- wp:group {"className":"petshop-account-editorial"} -->
<div class="wp-block-group petshop-account-editorial"><!-- wp:group {"className":"petshop-account-benefits"} -->
<div class="wp-block-group petshop-account-benefits"><!-- wp:heading -->
<h2 class="wp-block-heading">Uma conta<br><em>cheia de vantagens</em></h2>
<!-- /wp:heading -->{$benefits}<!-- wp:paragraph {"className":"petshop-account-affection"} -->
<p class="petshop-account-affection">Mais estilo para quem você ama</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --><!-- wp:image {"id":{$imageId},"sizeSlug":"full","linkDestination":"none","className":"petshop-account-art"} -->
<figure class="wp-block-image size-full petshop-account-art"><img src="{$url}" alt="{$alt}" class="wp-image-{$imageId}"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
HTML;
    }

    public static function filterComposition(array $block): array
    {
        if (!function_exists('is_account_page') || !is_account_page() || is_admin()) {
            return $block;
        }
        $classes = explode(' ', (string) ($block['attrs']['className'] ?? ''));
        if (in_array('petshop-account-scene', $classes, true) && (is_user_logged_in() || is_wc_endpoint_url())) {
            // Strip editorial blocks before rendering children: execute authentication only once.
            return parse_blocks('<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->')[0];
        }
        return $block;
    }

    public static function imageAttributes(array $attributes, \WP_Post $attachment): array
    {
        if (function_exists('is_account_page') && get_post_meta($attachment->ID, '_petshop_login_art_042', true)
            && is_account_page() && !is_user_logged_in()) {
            $attributes['loading'] = 'eager';
            $attributes['fetchpriority'] = 'high';
        }
        return $attributes;
    }

    public static function renderImage(string $html, array $block): string
    {
        if (function_exists('is_account_page') && !is_admin() && is_account_page() && !is_user_logged_in()
            && in_array('petshop-account-art', explode(' ', (string) ($block['attrs']['className'] ?? '')), true)) {
            $tags = new \WP_HTML_Tag_Processor($html);
            if ($tags->next_tag('img')) {
                $tags->set_attribute('loading', 'eager');
                $tags->set_attribute('fetchpriority', 'high');
                $html = $tags->get_updated_html();
            }
        }
        return $html;
    }

    public static function loginLabel(string $translated, string $text, string $domain): string
    {
        if ($domain === 'woocommerce' && $text === 'Log in' && function_exists('is_account_page') && is_account_page() && !is_user_logged_in()) {
            return __('Acessar', 'petshop-core');
        }
        return $translated;
    }
}
