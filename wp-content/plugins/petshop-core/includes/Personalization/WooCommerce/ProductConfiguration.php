<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\WooCommerce;

use Petshop\Core\Personalization\Domain\ProductionSpecification;
use Petshop\Core\Personalization\Infrastructure\ProductSettings;

defined('ABSPATH') || exit;

/**
 * "Personalização" product data tab and the storefront call to action.
 */
final class ProductConfiguration
{
    public const QUERY_FLAG = 'petshop_personalize';

    public static function bootstrap(): void
    {
        add_filter('woocommerce_product_data_tabs', [self::class, 'registerTab']);
        add_action('woocommerce_product_data_panels', [self::class, 'renderPanel']);
        add_action('woocommerce_process_product_meta', [self::class, 'save']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminAssets']);
        add_action('petshop_product_personalization_slot', [self::class, 'renderCallToAction']);
    }

    /**
     * @param array<string, array<string, mixed>> $tabs
     * @return array<string, array<string, mixed>>
     */
    public static function registerTab(array $tabs): array
    {
        $tabs['petshop_personalization'] = [
            'label' => __('Personalização', 'petshop-core'),
            'target' => 'petshop_personalization_data',
            'class' => ['show_if_simple', 'show_if_variable'],
            'priority' => 65,
        ];

        return $tabs;
    }

    public static function renderPanel(): void
    {
        global $post;
        $productId = $post instanceof \WP_Post ? (int) $post->ID : 0;
        $settings = ProductSettings::forProduct($productId);

        echo '<div id="petshop_personalization_data" class="panel woocommerce_options_panel hidden">';
        echo '<div class="options_group">';

        woocommerce_wp_checkbox([
            'id' => ProductSettings::META_ENABLED,
            'label' => __('Habilitar personalização', 'petshop-core'),
            'description' => __('Exibe o botão “Personalizar produto” na página do produto.', 'petshop-core'),
            'value' => $settings->enabled ? 'yes' : 'no',
        ]);

        woocommerce_wp_textarea_input([
            'id' => ProductSettings::META_INSTRUCTION,
            'label' => __('Instrução para o cliente', 'petshop-core'),
            'description' => __('Texto administrável exibido acima do editor.', 'petshop-core'),
            'desc_tip' => true,
            'rows' => 3,
            'value' => $settings->instruction,
        ]);

        echo '</div><div class="options_group">';

        self::renderMediaField(
            ProductSettings::META_MOCKUP_ID,
            __('Mockup base', 'petshop-core'),
            __('Foto do produto usada como fundo do editor.', 'petshop-core'),
            $settings->mockupId
        );
        self::renderMediaField(
            ProductSettings::META_MASK_ID,
            __('Máscara de recorte (PNG com transparência)', 'petshop-core'),
            __('Define a área imprimível irregular. Opcional.', 'petshop-core'),
            $settings->maskId
        );

        echo '</div><div class="options_group">';

        woocommerce_wp_text_input([
            'id' => ProductSettings::META_WIDTH_MM,
            'label' => __('Largura imprimível (mm)', 'petshop-core'),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.1', 'min' => '1'],
            'value' => $settings->widthMm > 0 ? (string) $settings->widthMm : '',
        ]);
        woocommerce_wp_text_input([
            'id' => ProductSettings::META_HEIGHT_MM,
            'label' => __('Altura imprimível (mm)', 'petshop-core'),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.1', 'min' => '1'],
            'value' => $settings->heightMm > 0 ? (string) $settings->heightMm : '',
        ]);
        woocommerce_wp_text_input([
            'id' => ProductSettings::META_DPI,
            'label' => __('DPI alvo', 'petshop-core'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '72', 'max' => '600'],
            'description' => __('Entre 72 e 600. O arquivo final usa mm ÷ 25,4 × DPI.', 'petshop-core'),
            'desc_tip' => true,
            'value' => (string) $settings->dpi,
        ]);

        $specification = $settings->specification();
        if ($specification instanceof ProductionSpecification) {
            echo '<p class="form-field"><span class="description">';
            echo esc_html(sprintf(
                /* translators: 1: width in pixels, 2: height in pixels, 3: megapixels */
                __('Arquivo de produção: %1$d × %2$d px (%3$s MP).', 'petshop-core'),
                $specification->widthPx(),
                $specification->heightPx(),
                number_format_i18n($specification->megapixels(), 2)
            ));
            echo '</span></p>';
        }

        echo '</div><div class="options_group">';

        woocommerce_wp_checkbox([
            'id' => ProductSettings::META_ALLOW_TEXT,
            'label' => __('Permitir texto', 'petshop-core'),
            'value' => $settings->allowText ? 'yes' : 'no',
        ]);
        woocommerce_wp_checkbox([
            'id' => ProductSettings::META_ALLOW_IMAGE,
            'label' => __('Permitir imagem do cliente', 'petshop-core'),
            'value' => $settings->allowImage ? 'yes' : 'no',
        ]);
        woocommerce_wp_text_input([
            'id' => ProductSettings::META_MAX_TEXT_BOXES,
            'label' => __('Máximo de caixas de texto', 'petshop-core'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1', 'max' => (string) ProductSettings::MAX_TEXT_BOXES],
            'value' => (string) $settings->maxTextBoxes,
        ]);
        woocommerce_wp_text_input([
            'id' => ProductSettings::META_FONTS,
            'label' => __('Fontes permitidas', 'petshop-core'),
            'description' => __('Lista separada por vírgula.', 'petshop-core'),
            'desc_tip' => true,
            'value' => implode(', ', $settings->fonts),
        ]);
        woocommerce_wp_text_input([
            'id' => ProductSettings::META_COLORS,
            'label' => __('Cores permitidas', 'petshop-core'),
            'description' => __('Lista de cores hexadecimais separada por vírgula.', 'petshop-core'),
            'desc_tip' => true,
            'value' => implode(', ', $settings->colors),
        ]);

        echo '</div></div>';
    }

    public static function save(int $productId): void
    {
        if (!current_user_can('edit_post', $productId)) {
            return;
        }

        self::saveFlag($productId, ProductSettings::META_ENABLED);
        self::saveFlag($productId, ProductSettings::META_ALLOW_IMAGE);
        self::saveFlag($productId, ProductSettings::META_ALLOW_TEXT);

        $instruction = isset($_POST[ProductSettings::META_INSTRUCTION]) && is_scalar($_POST[ProductSettings::META_INSTRUCTION])
            ? sanitize_textarea_field(wp_unslash((string) $_POST[ProductSettings::META_INSTRUCTION]))
            : '';
        self::saveOrDelete($productId, ProductSettings::META_INSTRUCTION, $instruction);

        foreach ([ProductSettings::META_MOCKUP_ID, ProductSettings::META_MASK_ID] as $metaKey) {
            $attachmentId = isset($_POST[$metaKey]) ? absint($_POST[$metaKey]) : 0;
            if ($attachmentId > 0 && get_post_type($attachmentId) !== 'attachment') {
                $attachmentId = 0;
            }
            self::saveOrDelete($productId, $metaKey, $attachmentId > 0 ? (string) $attachmentId : '');
        }

        foreach ([ProductSettings::META_WIDTH_MM, ProductSettings::META_HEIGHT_MM] as $metaKey) {
            $raw = isset($_POST[$metaKey]) && is_scalar($_POST[$metaKey]) ? wp_unslash((string) $_POST[$metaKey]) : '';
            $value = (float) str_replace(',', '.', $raw);
            self::saveOrDelete($productId, $metaKey, $value > 0 ? (string) round($value, 2) : '');
        }

        $dpi = isset($_POST[ProductSettings::META_DPI]) ? absint($_POST[ProductSettings::META_DPI]) : 0;
        self::saveOrDelete($productId, ProductSettings::META_DPI, (string) ProductSettings::normalizeDpi($dpi));

        $maxTextBoxes = isset($_POST[ProductSettings::META_MAX_TEXT_BOXES]) ? absint($_POST[ProductSettings::META_MAX_TEXT_BOXES]) : 0;
        self::saveOrDelete($productId, ProductSettings::META_MAX_TEXT_BOXES, (string) ProductSettings::normalizeTextBoxes($maxTextBoxes));

        $fonts = isset($_POST[ProductSettings::META_FONTS]) && is_scalar($_POST[ProductSettings::META_FONTS])
            ? ProductSettings::listFromCsv(wp_unslash((string) $_POST[ProductSettings::META_FONTS]))
            : [];
        self::saveOrDelete($productId, ProductSettings::META_FONTS, implode(', ', $fonts));

        $colors = isset($_POST[ProductSettings::META_COLORS]) && is_scalar($_POST[ProductSettings::META_COLORS])
            ? ProductSettings::colorsFromCsv(wp_unslash((string) $_POST[ProductSettings::META_COLORS]))
            : [];
        self::saveOrDelete($productId, ProductSettings::META_COLORS, implode(', ', $colors));
    }

    public static function enqueueAdminAssets(string $hookSuffix): void
    {
        if (!in_array($hookSuffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen instanceof \WP_Screen || $screen->post_type !== 'product') {
            return;
        }

        wp_enqueue_media();
        $relative = 'assets/js/personalization-admin.js';
        $path = plugin_dir_path(PETSHOP_CORE_FILE) . $relative;
        wp_enqueue_script(
            'petshop-personalization-admin',
            plugins_url($relative, PETSHOP_CORE_FILE),
            ['jquery'],
            is_file($path) ? (string) filemtime($path) : '1.0.0',
            true
        );
        wp_localize_script('petshop-personalization-admin', 'petshopPersonalizationAdmin', [
            'title' => __('Selecionar imagem', 'petshop-core'),
            'button' => __('Usar esta imagem', 'petshop-core'),
        ]);
    }

    public static function renderCallToAction(\WC_Product $product): void
    {
        $settings = ProductSettings::forProduct($product->get_id());
        if (!$settings->isUsable() || !$product->is_purchasable()) {
            return;
        }

        $editorUrl = add_query_arg(self::QUERY_FLAG, '1', (string) get_permalink($product->get_id()));

        echo '<div class="petshop-personalization-cta">';
        if (trim($settings->instruction) !== '') {
            echo '<p class="petshop-personalization-cta__instruction">' . esc_html($settings->instruction) . '</p>';
        }
        echo '<a class="button petshop-personalization-cta__button" href="' . esc_url($editorUrl) . '"'
            . ' data-petshop-personalize-open="' . esc_attr((string) $product->get_id()) . '">'
            . esc_html__('Personalizar produto', 'petshop-core')
            . '</a>';
        echo '</div>';
    }

    private static function renderMediaField(string $metaKey, string $label, string $description, int $attachmentId): void
    {
        $imageUrl = $attachmentId > 0 ? (string) wp_get_attachment_image_url($attachmentId, 'thumbnail') : '';
        $fieldId = esc_attr($metaKey);

        echo '<p class="form-field ' . $fieldId . '_field" data-petshop-media-field>';
        echo '<label for="' . $fieldId . '">' . esc_html($label) . '</label>';
        echo '<input type="hidden" id="' . $fieldId . '" name="' . $fieldId . '" value="' . esc_attr((string) ($attachmentId > 0 ? $attachmentId : '')) . '" data-petshop-media-input>';
        echo '<span class="petshop-media-preview" data-petshop-media-preview>';
        if ($imageUrl !== '') {
            echo '<img src="' . esc_url($imageUrl) . '" alt="" style="max-width:80px;height:auto;vertical-align:middle">';
        }
        echo '</span> ';
        echo '<button type="button" class="button" data-petshop-media-select>' . esc_html__('Selecionar imagem', 'petshop-core') . '</button> ';
        echo '<button type="button" class="button" data-petshop-media-clear>' . esc_html__('Remover', 'petshop-core') . '</button>';
        echo '<span class="description" style="display:block">' . esc_html($description) . '</span>';
        echo '</p>';
    }

    private static function saveFlag(int $productId, string $metaKey): void
    {
        $value = isset($_POST[$metaKey]) && $_POST[$metaKey] === 'yes' ? 'yes' : 'no';
        update_post_meta($productId, $metaKey, $value);
    }

    private static function saveOrDelete(int $productId, string $metaKey, string $value): void
    {
        if ($value === '') {
            delete_post_meta($productId, $metaKey);

            return;
        }

        update_post_meta($productId, $metaKey, $value);
    }
}
