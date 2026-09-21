<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Http;

use Petshop\Core\Personalization\Domain\ProductionSpecification;
use Petshop\Core\Personalization\Infrastructure\ImageProcessor;
use Petshop\Core\Personalization\Infrastructure\PrivateStorage;
use Petshop\Core\Personalization\Infrastructure\ProductSettings;
use Petshop\Core\Personalization\Infrastructure\RetentionPolicy;
use Petshop\Core\Personalization\Infrastructure\SessionIdentity;
use Petshop\Core\Personalization\Infrastructure\UploadVault;

defined('ABSPATH') || exit;

/**
 * Receives the single buyer image, validates it and keeps it in private storage.
 */
final class UploadController
{
    public const ACTION = 'petshop_personalization_upload';
    public const NONCE = 'petshop_personalization';

    private const RATE_LIMIT = 20;
    private const RATE_WINDOW = HOUR_IN_SECONDS;

    public static function bootstrap(): void
    {
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle']);
    }

    public static function handle(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');

        if (!self::allowRequest()) {
            wp_send_json_error(['message' => __('Muitos envios em pouco tempo. Tente novamente mais tarde.', 'petshop-core')], 429);
        }

        $productId = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $settings = ProductSettings::forProduct($productId);
        if (!$settings->isUsable() || !$settings->allowImage) {
            wp_send_json_error(['message' => __('Este produto não aceita envio de imagem.', 'petshop-core')], 400);
        }

        $binary = self::readUploadedFile();
        if ($binary === null) {
            wp_send_json_error(['message' => __('Nenhum arquivo válido foi recebido.', 'petshop-core')], 400);
        }

        try {
            PrivateStorage::ensureReady();
            $image = ImageProcessor::sanitizeUpload($binary);
            $relative = PrivateStorage::writeBinary(
                PrivateStorage::opaqueRelativePath(wp_generate_uuid4(), 'upload', $image['extension']),
                $image['binary']
            );
        } catch (\Throwable $error) {
            error_log('Petshop personalization upload recusado: ' . $error->getMessage());
            wp_send_json_error([
                'message' => __('Não foi possível processar a imagem. Envie JPEG, PNG ou WebP dentro do limite permitido.', 'petshop-core'),
            ], 400);

            return;
        }

        $token = UploadVault::store([
            'relative_path' => $relative,
            'mime' => $image['mime'],
            'extension' => $image['extension'],
            'width' => $image['width'],
            'height' => $image['height'],
            'hash' => $image['hash'],
            'bytes' => $image['bytes'],
        ], $productId);

        $specification = $settings->specification();
        $lowResolution = $specification instanceof ProductionSpecification
            && ($image['width'] < $specification->widthPx() * 0.6 || $image['height'] < $specification->heightPx() * 0.6);

        wp_send_json_success([
            'token' => $token,
            'width' => $image['width'],
            'height' => $image['height'],
            'dataUrl' => ImageProcessor::previewDataUrl($image['binary']),
            'lowResolution' => $lowResolution,
            'recommended' => $specification instanceof ProductionSpecification
                ? sprintf('%d × %d px', $specification->widthPx(), $specification->heightPx())
                : '',
        ]);
    }

    private static function readUploadedFile(): ?string
    {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            return null;
        }

        $file = $_FILES['file'];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpName = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return null;
        }

        if ((int) ($file['size'] ?? 0) > RetentionPolicy::maxUploadBytes()) {
            return null;
        }

        $contents = file_get_contents($tmpName);

        return is_string($contents) && $contents !== '' ? $contents : null;
    }

    private static function allowRequest(): bool
    {
        $userId = get_current_user_id();
        $identity = $userId > 0 ? 'user:' . $userId : 'session:' . (SessionIdentity::hash() ?? 'anonymous');
        $key = 'petshop_pz_rate_' . substr(hash('sha256', $identity), 0, 32);
        $count = (int) get_transient($key);
        if ($count >= self::RATE_LIMIT) {
            return false;
        }

        set_transient($key, $count + 1, self::RATE_WINDOW);

        return true;
    }
}
