<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\WooCommerce;

use Petshop\Core\Personalization\Http\UploadController;
use Petshop\Core\Personalization\Infrastructure\ProductSettings;

defined('ABSPATH') || exit;

/**
 * Loads the bundled Fabric.js editor on enabled product pages only.
 */
final class EditorSurface
{
    public const FIELD_NAME = 'petshop_personalization';

    public static function bootstrap(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets'], 20);
        add_action('wp_footer', [self::class, 'renderDialog']);
    }

    public static function enqueueAssets(): void
    {
        $settings = self::currentProductSettings();
        if (!$settings instanceof ProductSettings) {
            return;
        }

        $base = plugin_dir_path(PETSHOP_CORE_FILE);
        $fabricRelative = 'assets/personalizer/vendor/fabric.min.js';
        if (!is_file($base . $fabricRelative)) {
            error_log('Petshop personalization: fabric.min.js ausente; editor não carregado.');

            return;
        }

        wp_enqueue_script(
            'petshop-fabric',
            plugins_url($fabricRelative, PETSHOP_CORE_FILE),
            [],
            (string) filemtime($base . $fabricRelative),
            true
        );

        $editorRelative = 'assets/personalizer/editor.js';
        wp_enqueue_script(
            'petshop-personalizer',
            plugins_url($editorRelative, PETSHOP_CORE_FILE),
            ['petshop-fabric'],
            is_file($base . $editorRelative) ? (string) filemtime($base . $editorRelative) : '1.0.0',
            true
        );

        $styleRelative = 'assets/personalizer/editor.css';
        wp_enqueue_style(
            'petshop-personalizer',
            plugins_url($styleRelative, PETSHOP_CORE_FILE),
            [],
            is_file($base . $styleRelative) ? (string) filemtime($base . $styleRelative) : '1.0.0'
        );

        wp_localize_script('petshop-personalizer', 'petshopPersonalizerConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php', 'relative'),
            'nonce' => wp_create_nonce(UploadController::NONCE),
            'uploadAction' => UploadController::ACTION,
            'draftAction' => \Petshop\Core\Personalization\Http\DraftController::ACTION,
            'fieldName' => self::FIELD_NAME,
            'autoOpen' => isset($_GET[ProductConfiguration::QUERY_FLAG]) && $_GET[ProductConfiguration::QUERY_FLAG] === '1',
            'product' => $settings->toEditorPayload(),
            'i18n' => [
                'title' => __('Personalizar produto', 'petshop-core'),
                'close' => __('Fechar editor', 'petshop-core'),
                'addText' => __('Adicionar texto', 'petshop-core'),
                'addImage' => __('Enviar imagem', 'petshop-core'),
                'remove' => __('Remover selecionado', 'petshop-core'),
                'undo' => __('Desfazer', 'petshop-core'),
                'redo' => __('Refazer', 'petshop-core'),
                'reset' => __('Recomeçar', 'petshop-core'),
                'confirm' => __('Confirmar arte', 'petshop-core'),
                'confirming' => __('Gerando arquivos…', 'petshop-core'),
                'confirmed' => __('Arte confirmada. Adicione o produto ao carrinho.', 'petshop-core'),
                'font' => __('Fonte', 'petshop-core'),
                'color' => __('Cor', 'petshop-core'),
                'textPlaceholder' => __('Digite o texto', 'petshop-core'),
                'lowResolution' => __('A imagem enviada tem resolução abaixo do recomendado para impressão.', 'petshop-core'),
                'emptyCanvas' => __('Adicione texto ou imagem antes de confirmar.', 'petshop-core'),
                'uploadError' => __('Não foi possível enviar a imagem.', 'petshop-core'),
                'genericError' => __('Não foi possível salvar a arte agora. Tente novamente.', 'petshop-core'),
                'maxTextReached' => __('Limite de caixas de texto atingido para este produto.', 'petshop-core'),
                'imageAlreadyAdded' => __('Este produto aceita apenas uma imagem.', 'petshop-core'),
                'required' => __('Personalize o produto antes de adicionar ao carrinho.', 'petshop-core'),
            ],
        ]);
    }

    public static function renderDialog(): void
    {
        if (!self::currentProductSettings() instanceof ProductSettings) {
            return;
        }

        echo '<div class="petshop-personalizer" data-petshop-personalizer hidden>'
            . '<div class="petshop-personalizer__backdrop" data-petshop-personalizer-close></div>'
            . '<div class="petshop-personalizer__dialog" role="dialog" aria-modal="true"'
            . ' aria-labelledby="petshop-personalizer-title" tabindex="-1" data-petshop-personalizer-dialog>'
            . '<h2 id="petshop-personalizer-title" class="petshop-personalizer__title"></h2>'
            . '<div class="petshop-personalizer__body" data-petshop-personalizer-body></div>'
            . '</div></div>';
    }

    private static function currentProductSettings(): ?ProductSettings
    {
        if (!function_exists('is_product') || !is_product()) {
            return null;
        }

        $product = wc_get_product(get_queried_object_id());
        if (!$product instanceof \WC_Product || !$product->is_purchasable()) {
            return null;
        }

        $settings = ProductSettings::forProduct($product->get_id());

        return $settings->isUsable() ? $settings : null;
    }
}
