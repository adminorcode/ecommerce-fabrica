<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

/**
 * Galeria de ícones outline para categorias de produto (grade da Home).
 */
final class CategoryIcons
{
    public const META_KEY = 'petshop_category_icon';

    /** @var array<string, string> slug do ícone => rótulo */
    private const ICONS = [
        'sticker' => 'Adesivo',
        'bib' => 'Babador',
        'bandana' => 'Bandana',
        'collar' => 'Colarinho',
        'layers' => 'Conjunto',
        'trophy' => 'Troféu',
        'party' => 'Festa',
        'necklace' => 'Gargantilha',
        'necktie' => 'Gravata',
        'snowflake' => 'Inverno',
        'bow' => 'Laço',
        'bow-sticker' => 'Laço adesivo',
        'scissors' => 'Penteado',
        'gift' => 'Presente',
        'tag' => 'Promoção',
        'paw' => 'Patinha',
        'sparkles' => 'Destaque',
    ];

    /** @var array<string, string> slug da categoria => slug do ícone */
    private const DEFAULTS_BY_CATEGORY = [
        'adesivos' => 'sticker',
        'babador' => 'bib',
        'bandanas' => 'bandana',
        'colarinhos' => 'collar',
        'conjuntos' => 'layers',
        'copa' => 'trophy',
        'festa-junina' => 'party',
        'gargantilhas' => 'necklace',
        'gravatas' => 'necktie',
        'inverno' => 'snowflake',
        'lacos' => 'bow',
        'lacos-adesivos' => 'bow-sticker',
        'penteados' => 'scissors',
        'dia-dos-pais' => 'gift',
        'promocoes' => 'tag',
        'outros' => 'paw',
    ];

    public static function bootstrap(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminAssets']);
    }

    /**
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return self::ICONS;
    }

    public static function isValid(string $icon): bool
    {
        return isset(self::ICONS[$icon]);
    }

    public static function defaultForSlug(string $categorySlug): string
    {
        return self::DEFAULTS_BY_CATEGORY[$categorySlug] ?? 'paw';
    }

    public static function resolveForTerm(\WP_Term $term): string
    {
        $stored = (string) get_term_meta($term->term_id, self::META_KEY, true);
        if ($stored !== '' && self::isValid($stored)) {
            return $stored;
        }

        return self::defaultForSlug($term->slug);
    }

    public static function url(string $icon): string
    {
        if (!self::isValid($icon)) {
            $icon = 'paw';
        }

        return plugins_url(
            'assets/icons/categories/' . $icon . '.svg',
            PETSHOP_CORE_FILE
        );
    }

    public static function enqueueAdminAssets(string $hook): void
    {
        if (!in_array($hook, ['edit-tags.php', 'term.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen === null || $screen->taxonomy !== 'product_cat') {
            return;
        }

        $path = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/css/category-icon-picker.css';
        wp_enqueue_style(
            'petshop-category-icon-picker',
            plugins_url('assets/css/category-icon-picker.css', PETSHOP_CORE_FILE),
            [],
            is_file($path) ? (string) filemtime($path) : '1.0.0'
        );
    }

    public static function renderPicker(?string $selected = null): void
    {
        $selected = $selected !== null && self::isValid($selected) ? $selected : '';
        ?>
        <fieldset class="petshop-icon-picker">
            <legend class="screen-reader-text"><?php esc_html_e('Ícone da categoria', 'petshop-core'); ?></legend>
            <label class="petshop-icon-picker__option">
                <input
                    type="radio"
                    name="<?php echo esc_attr(self::META_KEY); ?>"
                    value=""
                    <?php checked($selected, ''); ?>
                >
                <span class="petshop-icon-picker__preview petshop-icon-picker__preview--auto" aria-hidden="true"></span>
                <span class="petshop-icon-picker__label"><?php esc_html_e('Automático', 'petshop-core'); ?></span>
            </label>
            <?php foreach (self::ICONS as $slug => $label) : ?>
                <label class="petshop-icon-picker__option">
                    <input
                        type="radio"
                        name="<?php echo esc_attr(self::META_KEY); ?>"
                        value="<?php echo esc_attr($slug); ?>"
                        <?php checked($selected, $slug); ?>
                    >
                    <span
                        class="petshop-icon-picker__preview"
                        style="--petshop-icon: url('<?php echo esc_url(self::url($slug)); ?>')"
                        aria-hidden="true"
                    ></span>
                    <span class="petshop-icon-picker__label"><?php echo esc_html($label); ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">
            <?php esc_html_e('Usado na grade “Compre por categoria” da Home. A miniatura WooCommerce continua disponível na página da categoria.', 'petshop-core'); ?>
        </p>
        <?php
    }

    public static function ensureDefaults(): void
    {
        foreach (self::DEFAULTS_BY_CATEGORY as $slug => $icon) {
            $term = get_term_by('slug', $slug, 'product_cat');
            if (!$term instanceof \WP_Term) {
                continue;
            }

            if (metadata_exists('term', $term->term_id, self::META_KEY)) {
                continue;
            }

            update_term_meta($term->term_id, self::META_KEY, $icon);
        }
    }
}
