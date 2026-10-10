<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

final class StorefrontCatalog
{
    private const VERSION = '1.3.0';
    private const OPTION = 'petshop_catalog_taxonomy_version';
    private const LOCK_OPTION = 'petshop_catalog_taxonomy_lock';
    private const ERROR_OPTION = 'petshop_catalog_taxonomy_error';

    /** @var array<string, array{label: string, seasonal: bool, parent?: string, visible?: bool}> */
    private const CATEGORIES = [
        'promocoes' => ['label' => 'Promoções', 'seasonal' => false],
        'adesivos' => ['label' => 'Adesivos', 'seasonal' => false],
        'babador' => ['label' => 'Babador', 'seasonal' => false],
        'bandanas' => ['label' => 'Bandanas', 'seasonal' => false],
        'colarinhos' => ['label' => 'Colarinhos', 'seasonal' => false],
        'conjuntos' => ['label' => 'Conjuntos', 'seasonal' => false],
        'copa' => ['label' => 'Copa', 'seasonal' => true],
        'festa-junina' => ['label' => 'Festa Junina', 'seasonal' => true],
        'gargantilhas' => ['label' => 'Gargantilhas', 'seasonal' => false],
        'gravatas' => ['label' => 'Gravatas', 'seasonal' => false],
        'inverno' => ['label' => 'Inverno', 'seasonal' => true],
        'lacos' => ['label' => 'Laços', 'seasonal' => false],
        'lacos-adesivos' => ['label' => 'Laços Adesivos', 'seasonal' => false, 'parent' => 'lacos'],
        'penteados' => ['label' => 'Penteados', 'seasonal' => false],
        'dia-dos-pais' => ['label' => 'Dia dos Pais', 'seasonal' => true, 'visible' => true],
    ];

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerTermMeta']);
        add_action('admin_init', [self::class, 'maybeEnsureCategories'], 20);
        add_action('admin_notices', [self::class, 'renderMigrationNotice']);
    }

    public static function maybeEnsureCategories(): void
    {
        $isCli = defined('WP_CLI') && WP_CLI;
        if (
            (!$isCli && !current_user_can('manage_woocommerce') && !current_user_can('manage_options'))
            || get_option(self::OPTION) === self::VERSION
        ) {
            return;
        }

        $lock = (int) get_option(self::LOCK_OPTION);
        if ($lock > 0 && $lock > time() - 300) {
            return;
        }
        if ($lock > 0) {
            delete_option(self::LOCK_OPTION);
        }
        if (!add_option(self::LOCK_OPTION, time(), '', false)) {
            return;
        }

        try {
            self::ensureCategories();
            if (get_option(self::OPTION) === self::VERSION) {
                delete_option(self::ERROR_OPTION);
            }
        } catch (\Throwable $error) {
            update_option(self::ERROR_OPTION, $error->getMessage(), false);
            error_log('Petshop catalog migration failed: ' . $error->getMessage());
        } finally {
            delete_option(self::LOCK_OPTION);
        }
    }

    public static function renderMigrationNotice(): void
    {
        $message = (string) get_option(self::ERROR_OPTION, '');
        if ($message === '' || !current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html(sprintf(__('Não foi possível atualizar a taxonomia da loja: %s', 'petshop-core'), $message));
        echo '</p></div>';
    }

    public static function registerTermMeta(): void
    {
        register_term_meta('product_cat', 'petshop_menu_order', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => static fn (): bool => current_user_can('manage_woocommerce'),
        ]);
        register_term_meta('product_cat', 'petshop_seasonal', [
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
            'auth_callback' => static fn (): bool => current_user_can('manage_woocommerce'),
        ]);
        register_term_meta('product_cat', 'petshop_visible_in_menu', [
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
            'auth_callback' => static fn (): bool => current_user_can('manage_woocommerce'),
        ]);
        register_term_meta('product_cat', CategoryIcons::META_KEY, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => static function ($value): string {
                $value = sanitize_key((string) $value);
                return CategoryIcons::isValid($value) ? $value : '';
            },
            'auth_callback' => static fn (): bool => current_user_can('manage_woocommerce'),
        ]);
        register_term_meta('product_cat', CategoryIcons::ATTACHMENT_META_KEY, [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => static function ($value): int {
                $attachmentId = absint($value);
                return CategoryIcons::isUsableIconAttachment($attachmentId) ? $attachmentId : 0;
            },
            'auth_callback' => static fn (): bool => current_user_can('manage_woocommerce'),
        ]);
    }

    public static function ensureCategories(): void
    {
        if (!taxonomy_exists('product_cat') || get_option(self::OPTION) === self::VERSION) {
            return;
        }

        $termIds = [];
        $allCategoriesReady = true;
        foreach (self::CATEGORIES as $slug => $category) {
            $existing = get_term_by('slug', $slug, 'product_cat');
            $parentId = isset($category['parent']) ? ($termIds[$category['parent']] ?? 0) : 0;
            $wasCreated = false;

            if ($existing instanceof \WP_Term) {
                $termId = (int) $existing->term_id;
                if ($parentId !== 0 && (int) $existing->parent !== $parentId) {
                    $allCategoriesReady = false;
                    self::logConflict($slug, $termId);
                }
            } else {
                $created = wp_insert_term($category['label'], 'product_cat', [
                    'slug' => $slug,
                    'parent' => $parentId,
                ]);
                if (is_wp_error($created)) {
                    $allCategoriesReady = false;
                    continue;
                }
                $termId = (int) $created['term_id'];
                $wasCreated = true;
            }

            $termIds[$slug] = $termId;
            if ($wasCreated || !metadata_exists('term', $termId, 'petshop_menu_order')) {
                update_term_meta($termId, 'petshop_menu_order', array_search($slug, array_keys(self::CATEGORIES), true));
            }
            if ($wasCreated || !metadata_exists('term', $termId, 'petshop_seasonal')) {
                update_term_meta($termId, 'petshop_seasonal', $category['seasonal']);
            }
            if ($wasCreated || !metadata_exists('term', $termId, 'petshop_visible_in_menu')) {
                update_term_meta(
                    $termId,
                    'petshop_visible_in_menu',
                    $category['visible'] ?? !$category['seasonal']
                );
            }
            if ($wasCreated || !metadata_exists('term', $termId, CategoryIcons::META_KEY)) {
                update_term_meta(
                    $termId,
                    CategoryIcons::META_KEY,
                    CategoryIcons::defaultForSlug($slug)
                );
            }
        }

        CategoryIcons::ensureDefaults();

        if ($allCategoriesReady) {
            update_option(self::OPTION, self::VERSION, false);
        }
    }

    private static function logConflict(string $slug, int $termId): void
    {
        $message = sprintf(
            'Petshop Core did not change product category "%s" (term ID %d) because its existing parent conflicts with the canonical catalog.',
            $slug,
            $termId
        );

        error_log($message);
    }
}
