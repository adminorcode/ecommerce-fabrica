<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

/**
 * Galeria de ícones outline e ícone personalizado (Biblioteca de mídia) para categorias.
 *
 * Prioridade na Home: attachment personalizado → galeria (`META_KEY`) → default por slug.
 * Attachment personalizado renderiza como <img> (preserva cores do arquivo).
 * Galeria/default continua via CSS mask na cor do tema.
 */
final class CategoryIcons
{
    public const META_KEY = 'petshop_category_icon';

    public const ATTACHMENT_META_KEY = 'petshop_category_icon_attachment_id';

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

    public static function customAttachmentId(\WP_Term $term): int
    {
        $attachmentId = absint(get_term_meta($term->term_id, self::ATTACHMENT_META_KEY, true));
        if ($attachmentId <= 0 || !self::isUsableIconAttachment($attachmentId)) {
            return 0;
        }

        return $attachmentId;
    }

    public static function isUsableIconAttachment(int $attachmentId): bool
    {
        if ($attachmentId <= 0) {
            return false;
        }

        $post = get_post($attachmentId);
        if (!$post instanceof \WP_Post || $post->post_type !== 'attachment') {
            return false;
        }

        if ($post->post_status === 'trash') {
            return false;
        }

        $mime = (string) get_post_mime_type($attachmentId);
        if ($mime === '' || !str_starts_with($mime, 'image/')) {
            return false;
        }

        $url = wp_get_attachment_url($attachmentId);

        return is_string($url) && $url !== '';
    }

    public static function attachmentUrl(int $attachmentId): string
    {
        if (!self::isUsableIconAttachment($attachmentId)) {
            return '';
        }

        $url = wp_get_attachment_url($attachmentId);

        return is_string($url) ? $url : '';
    }

    /**
     * @return array{source: 'attachment'|'gallery', url: string, attachment_id: int, gallery_slug: string}
     */
    public static function resolveDisplayForTerm(\WP_Term $term): array
    {
        $attachmentId = self::customAttachmentId($term);
        if ($attachmentId > 0) {
            $url = wp_get_attachment_url($attachmentId);
            if (is_string($url) && $url !== '') {
                return [
                    'source' => 'attachment',
                    'url' => $url,
                    'attachment_id' => $attachmentId,
                    'gallery_slug' => '',
                ];
            }
        }

        $gallerySlug = self::resolveForTerm($term);

        return [
            'source' => 'gallery',
            'url' => self::url($gallerySlug),
            'attachment_id' => 0,
            'gallery_slug' => $gallerySlug,
        ];
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

        $cssPath = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/css/category-icon-picker.css';
        wp_enqueue_style(
            'petshop-category-icon-picker',
            plugins_url('assets/css/category-icon-picker.css', PETSHOP_CORE_FILE),
            [],
            is_file($cssPath) ? (string) filemtime($cssPath) : '1.0.0'
        );

        wp_enqueue_media();
        $jsPath = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/category-icon-media.js';
        wp_enqueue_script(
            'petshop-category-icon-media',
            plugins_url('assets/js/category-icon-media.js', PETSHOP_CORE_FILE),
            ['jquery'],
            is_file($jsPath) ? (string) filemtime($jsPath) : '1.0.0',
            true
        );
        wp_localize_script('petshop-category-icon-media', 'petshopCategoryIconMedia', [
            'title' => __('Selecionar ícone personalizado da vitrine', 'petshop-core'),
            'button' => __('Usar este ícone', 'petshop-core'),
            'labelSelect' => __('Selecionar ícone personalizado', 'petshop-core'),
            'labelChange' => __('Trocar ícone personalizado', 'petshop-core'),
        ]);
    }

    public static function renderCustomAttachmentField(int $attachmentId = 0): void
    {
        $attachmentId = self::isUsableIconAttachment($attachmentId) ? $attachmentId : 0;
        $previewUrl = $attachmentId > 0 ? self::attachmentUrl($attachmentId) : '';
        ?>
        <div class="petshop-category-custom-icon" data-petshop-category-custom-icon>
            <input
                type="hidden"
                name="<?php echo esc_attr(self::ATTACHMENT_META_KEY); ?>"
                id="<?php echo esc_attr(self::ATTACHMENT_META_KEY); ?>"
                value="<?php echo esc_attr((string) $attachmentId); ?>"
                data-petshop-icon-attachment-input
            >
            <div class="petshop-category-custom-icon__preview" data-petshop-icon-attachment-preview<?php echo $previewUrl === '' ? ' hidden' : ''; ?>>
                <?php if ($previewUrl !== '') : ?>
                    <img src="<?php echo esc_url($previewUrl); ?>" alt="" width="64" height="64">
                <?php endif; ?>
            </div>
            <p class="petshop-category-custom-icon__actions">
                <button type="button" class="button" data-petshop-icon-attachment-select>
                    <?php echo $attachmentId > 0
                        ? esc_html__('Trocar ícone personalizado', 'petshop-core')
                        : esc_html__('Selecionar ícone personalizado', 'petshop-core'); ?>
                </button>
                <button
                    type="button"
                    class="button"
                    data-petshop-icon-attachment-remove
                    <?php disabled($attachmentId <= 0); ?>
                >
                    <?php esc_html_e('Remover', 'petshop-core'); ?>
                </button>
            </p>
            <p class="description">
                <?php esc_html_e('Prioridade máxima na grade “Compre por categoria”. Preferir SVG ou PNG/WebP com fundo transparente, proporção 1:1. Se o ambiente bloquear SVG, use PNG transparente. A miniatura WooCommerce não é usada aqui.', 'petshop-core'); ?>
            </p>
        </div>
        <?php
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
