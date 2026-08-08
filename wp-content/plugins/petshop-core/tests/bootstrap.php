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

require_once __DIR__ . '/../vendor/autoload.php';
