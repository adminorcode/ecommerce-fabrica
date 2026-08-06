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
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
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

require_once __DIR__ . '/../includes/class-storefront-experience.php';
