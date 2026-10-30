<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Http;

use Petshop\Core\Personalization\Application\CreateDraft;
use Petshop\Core\Personalization\Infrastructure\RetentionPolicy;

defined('ABSPATH') || exit;

/**
 * Confirms the canvas and returns the public id used by the add-to-cart form.
 */
final class DraftController
{
    public const ACTION = 'petshop_personalization_draft';

    private const MAX_JSON_BYTES = 65536;
    private const MAX_JSON_DEPTH = 8;

    public static function bootstrap(): void
    {
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle']);
    }

    public static function handle(): void
    {
        check_ajax_referer(UploadController::NONCE, 'nonce');

        $rawDesign = isset($_POST['design']) && is_string($_POST['design']) ? wp_unslash($_POST['design']) : '';
        if ($rawDesign === '' || strlen($rawDesign) > self::MAX_JSON_BYTES) {
            wp_send_json_error(['message' => __('Arte inválida ou muito grande.', 'petshop-core')], 400);
        }

        $maxPngChars = (int) (RetentionPolicy::maxUploadBytes() * 2 * 1.4);
        $preview = isset($_POST['preview']) && is_string($_POST['preview']) ? wp_unslash($_POST['preview']) : '';
        $production = isset($_POST['production']) && is_string($_POST['production']) ? wp_unslash($_POST['production']) : '';
        if (strlen($preview) > $maxPngChars || strlen($production) > $maxPngChars) {
            wp_send_json_error(['message' => __('A prévia enviada é grande demais.', 'petshop-core')], 400);
        }

        $design = json_decode($rawDesign, true, self::MAX_JSON_DEPTH);
        if (!is_array($design)) {
            wp_send_json_error(['message' => __('Não foi possível interpretar a arte enviada.', 'petshop-core')], 400);
        }

        try {
            $personalization = CreateDraft::handle([
                'product_id' => isset($_POST['product_id']) ? absint($_POST['product_id']) : 0,
                'variation_id' => isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0,
                'design' => $design,
                'preview_png' => $preview,
                'production_png' => $production,
                'upload_token' => isset($_POST['upload_token']) && is_string($_POST['upload_token'])
                    ? sanitize_text_field(wp_unslash($_POST['upload_token']))
                    : '',
            ]);
        } catch (\Throwable $error) {
            error_log('Petshop personalization draft recusado: ' . $error->getMessage());
            wp_send_json_error([
                'message' => __('Não foi possível confirmar a personalização. Revise a arte e tente novamente.', 'petshop-core'),
            ], 400);

            return;
        }

        wp_send_json_success([
            'publicId' => $personalization->publicId,
            'summary' => $personalization->textSummary,
            'previewUrl' => DownloadController::previewUrl($personalization->publicId),
        ]);
    }
}
