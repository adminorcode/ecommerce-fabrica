<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/../../../../');

final class WP_Query
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function is_main_query(): bool
    {
        return (bool) ($this->values['main_query'] ?? true);
    }

    public function is_post_type_archive(string $postType): bool
    {
        return ($this->values['post_type_archive'] ?? '') === $postType;
    }

    public function is_search(): bool
    {
        return (bool) ($this->values['search'] ?? false);
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }
}

final class PetshopTestWpdb
{
    public string $posts = 'wp_posts';

    public function prepare(string $query, int $productId): string
    {
        return str_replace('%d', (string) $productId, $query);
    }
}

final class WooCommerce
{
    public mixed $session = null;
}

class WC_Order
{
    public function __construct(
        private readonly int $id = 0,
        private readonly string $paymentMethod = '',
        private readonly bool $needsPayment = false,
        private readonly string $receivedUrl = '',
        private readonly string $paymentUrl = ''
    ) {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_payment_method(): string
    {
        return $this->paymentMethod;
    }

    public function needs_payment(): bool
    {
        return $this->needsPayment;
    }

    public function get_checkout_order_received_url(): string
    {
        return $this->receivedUrl !== '' ? $this->receivedUrl : 'https://store.test/checkout/order-received/' . $this->id . '/?key=wc_order_' . $this->id;
    }

    public function get_checkout_payment_url(): string
    {
        return $this->paymentUrl !== '' ? $this->paymentUrl : 'https://store.test/checkout/order-pay/' . $this->id . '/?pay_for_order=true&key=wc_order_' . $this->id;
    }
}

final class PetshopTestProduct
{
    public function __construct(private readonly bool $variation = false, private readonly int $parentId = 0)
    {
    }

    public function is_type(string $type): bool
    {
        return $type === 'variation' && $this->variation;
    }

    public function get_parent_id(): int
    {
        return $this->parentId;
    }
}

function is_admin(): bool
{
    return false;
}

function wp_unslash(mixed $value): mixed
{
    return $value;
}

function sanitize_title(string $value): string
{
    $value = strtr($value, [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        'Á' => 'a', 'À' => 'a', 'Ã' => 'a', 'Â' => 'a', 'Ç' => 'c',
    ]);
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

function sanitize_key(string $value): string
{
    return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $value));
}

function wc_format_decimal(string $value): string
{
    return is_numeric(str_replace(',', '.', $value)) ? str_replace(',', '.', $value) : '';
}

function taxonomy_exists(string $taxonomy): bool
{
    return in_array($taxonomy, ['product_cat', 'product_tag', 'pa_color', 'pa_size'], true);
}

/** @return array<string, string> */
function wc_get_catalog_ordering_options(): array
{
    return ['menu_order' => 'Padrão', 'price' => 'Preço', 'price-desc' => 'Preço decrescente'];
}

function absint(mixed $value): int
{
    return abs((int) $value);
}

function __(string $text, string $domain = 'default'): string
{
    return $text;
}

function esc_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize_text_field(string $value): string
{
    return trim(strip_tags($value));
}

function wp_strip_all_tags(string $text): string
{
    return trim(strip_tags($text));
}

function get_theme_mod(string $name, mixed $default = false): mixed
{
    $mods = $GLOBALS['petshop_test_theme_mods'] ?? [];
    if (is_array($mods) && array_key_exists($name, $mods)) {
        return $mods[$name];
    }

    return $default;
}

function add_filter(string $hook, mixed $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
    $GLOBALS['petshop_test_filters'][$hook][] = [
        'callback' => $callback,
        'priority' => $priority,
        'accepted_args' => $acceptedArgs,
    ];

    return true;
}

function add_action(string $hook, mixed $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
    return add_filter($hook, $callback, $priority, $acceptedArgs);
}

function home_url(string $path = ''): string
{
    $home = rtrim((string) ($GLOBALS['petshop_test_home_url'] ?? 'https://store.test'), '/');
    if ($path === '') {
        return $home;
    }

    return $home . '/' . ltrim($path, '/');
}

function add_query_arg(string $key, string $value, string $url): string
{
    $separator = str_contains($url, '?') ? '&' : '?';

    return $url . $separator . rawurlencode($key) . '=' . rawurlencode($value);
}

/** @return array<string, mixed>|int|string|null|false */
function wp_parse_url(string $url, int $component = -1): mixed
{
    return $component === -1 ? parse_url($url) : parse_url($url, $component);
}

function get_query_var(string $key, mixed $default = ''): mixed
{
    return $GLOBALS['petshop_test_query_vars'][$key] ?? $default;
}

function wc_get_order(int $orderId): ?WC_Order
{
    return $GLOBALS['petshop_test_orders'][$orderId] ?? null;
}

function wc_get_account_endpoint_url(string $endpoint): string
{
    return 'https://store.test/minha-conta/' . trim($endpoint, '/') . '/';
}

function is_user_logged_in(): bool
{
    return (bool) ($GLOBALS['petshop_test_logged_in'] ?? false);
}

function WC(): WooCommerce
{
    if (!isset($GLOBALS['petshop_test_wc']) || !$GLOBALS['petshop_test_wc'] instanceof WooCommerce) {
        $GLOBALS['petshop_test_wc'] = new WooCommerce();
    }

    return $GLOBALS['petshop_test_wc'];
}

/** @param array<string, mixed> $pairs @param array<string, mixed> $attributes */
function shortcode_atts(array $pairs, array $attributes): array
{
    return array_merge($pairs, array_intersect_key($attributes, $pairs));
}

/** @return array<int, object> */
function get_comments(array $arguments = []): array
{
    return [];
}

function wc_get_product_id_by_sku(string $sku): int
{
    return (int) ($GLOBALS['petshop_test_skus'][$sku] ?? 0);
}

function wc_get_product(int $productId): ?PetshopTestProduct
{
    return $GLOBALS['petshop_test_products'][$productId] ?? null;
}

$GLOBALS['wpdb'] = new PetshopTestWpdb();
$GLOBALS['petshop_test_skus'] = [];
$GLOBALS['petshop_test_products'] = [];
$GLOBALS['petshop_test_orders'] = [];
$GLOBALS['petshop_test_logged_in'] = false;
$GLOBALS['petshop_test_query_vars'] = [];
$GLOBALS['petshop_test_home_url'] = 'https://store.test';
$GLOBALS['petshop_test_wc'] = new WooCommerce();

require_once __DIR__ . '/../vendor/autoload.php';
