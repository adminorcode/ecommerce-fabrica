<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Application;

use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Domain\ProductionSpecification;
use Petshop\Core\Personalization\Infrastructure\ImageProcessor;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;
use Petshop\Core\Personalization\Infrastructure\PrivateStorage;
use Petshop\Core\Personalization\Infrastructure\ProductSettings;
use Petshop\Core\Personalization\Infrastructure\RetentionPolicy;
use Petshop\Core\Personalization\Infrastructure\SessionIdentity;
use Petshop\Core\Personalization\Infrastructure\UploadVault;

defined('ABSPATH') || exit;

/**
 * Turns a confirmed canvas into a draft with preview, production PNG and JSON.
 */
final class CreateDraft
{
    private const MAX_OBJECTS = 40;
    private const MAX_DESIGN_BYTES = 65536;
    private const MAX_TEXT_LENGTH = 120;
    private const MAX_SUMMARY_LENGTH = 300;

    /**
     * @param array{
     *   product_id: int,
     *   variation_id?: int,
     *   design: array<string, mixed>,
     *   preview_png: string,
     *   production_png: string,
     *   upload_token?: string
     * } $input
     */
    public static function handle(array $input): Personalization
    {
        $productId = (int) ($input['product_id'] ?? 0);
        $product = wc_get_product($productId);
        if (!$product instanceof \WC_Product || $product->get_status() !== 'publish' || !$product->is_purchasable()) {
            throw new \RuntimeException(__('Produto indisponível para personalização.', 'petshop-core'));
        }

        $settings = ProductSettings::forProduct($productId);
        $specification = $settings->specification();
        if (!$settings->isUsable() || !$specification instanceof ProductionSpecification) {
            throw new \RuntimeException(__('Personalização não está habilitada para este produto.', 'petshop-core'));
        }

        PrivateStorage::ensureReady();

        $design = self::sanitizeDesign(is_array($input['design'] ?? null) ? $input['design'] : [], $settings);
        $designJson = wp_json_encode($design, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($designJson) || strlen($designJson) > self::MAX_DESIGN_BYTES) {
            throw new \RuntimeException(__('Arte muito complexa para ser salva.', 'petshop-core'));
        }

        $snapshotJson = wp_json_encode($settings->toSnapshot(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($snapshotJson)) {
            throw new \RuntimeException(__('Falha ao congelar a configuração do produto.', 'petshop-core'));
        }

        $preview = ImageProcessor::sanitizePng(ImageProcessor::decodePngDataUrl((string) ($input['preview_png'] ?? '')));
        $production = ImageProcessor::sanitizePng(
            ImageProcessor::decodePngDataUrl((string) ($input['production_png'] ?? '')),
            $specification->widthPx(),
            $specification->heightPx()
        );

        $upload = null;
        $uploadToken = isset($input['upload_token']) ? (string) $input['upload_token'] : '';
        if ($uploadToken !== '') {
            $upload = UploadVault::claim($uploadToken, $productId);
            if ($upload === null) {
                throw new \RuntimeException(__('A imagem enviada expirou. Envie novamente.', 'petshop-core'));
            }
        }

        if (!$settings->allowImage && $upload !== null) {
            throw new \RuntimeException(__('Este produto não aceita imagem enviada pelo cliente.', 'petshop-core'));
        }

        $userId = get_current_user_id();
        $personalization = PersonalizationRepository::createDraft([
            'product_id' => $productId,
            'variation_id' => (int) ($input['variation_id'] ?? 0),
            'user_id' => $userId > 0 ? $userId : null,
            'cart_hash' => SessionIdentity::hash(),
            'design_json' => $designJson,
            'config_snapshot' => $snapshotJson,
            'text_summary' => self::summarize($design, $upload !== null),
            'expires_at' => RetentionPolicy::draftExpiresAt(),
        ]);

        try {
            self::storeArtifacts($personalization, $preview, $production, $upload, $specification->dpi);
        } catch (\Throwable $error) {
            PersonalizationRepository::purge($personalization);
            throw $error;
        }

        return $personalization;
    }

    /**
     * @param array{binary: string, width: int, height: int, hash: string, bytes: int} $preview
     * @param array{binary: string, width: int, height: int, hash: string, bytes: int} $production
     * @param array{relative_path: string, mime: string, extension: string, width: int, height: int, hash: string, bytes: int}|null $upload
     */
    private static function storeArtifacts(
        Personalization $personalization,
        array $preview,
        array $production,
        ?array $upload,
        int $dpi
    ): void {
        $previewPath = PrivateStorage::writeBinary(
            PrivateStorage::opaqueRelativePath($personalization->publicId, PersonalizationRepository::FILE_PREVIEW, 'png'),
            $preview['binary']
        );
        PersonalizationRepository::putFile($personalization->id ?? 0, PersonalizationRepository::FILE_PREVIEW, [
            'relative_path' => $previewPath,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'byte_size' => $preview['bytes'],
            'width_px' => $preview['width'],
            'height_px' => $preview['height'],
            'dpi_target' => null,
            'content_hash' => $preview['hash'],
        ]);

        $productionPath = PrivateStorage::writeBinary(
            PrivateStorage::opaqueRelativePath($personalization->publicId, PersonalizationRepository::FILE_PRODUCTION, 'png'),
            $production['binary']
        );
        PersonalizationRepository::putFile($personalization->id ?? 0, PersonalizationRepository::FILE_PRODUCTION, [
            'relative_path' => $productionPath,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'byte_size' => $production['bytes'],
            'width_px' => $production['width'],
            'height_px' => $production['height'],
            'dpi_target' => $dpi,
            'content_hash' => $production['hash'],
        ]);

        if ($upload === null) {
            return;
        }

        $binary = PrivateStorage::readBinary($upload['relative_path']);
        $originalPath = PrivateStorage::writeBinary(
            PrivateStorage::opaqueRelativePath($personalization->publicId, PersonalizationRepository::FILE_ORIGINAL, $upload['extension']),
            $binary
        );
        PersonalizationRepository::putFile($personalization->id ?? 0, PersonalizationRepository::FILE_ORIGINAL, [
            'relative_path' => $originalPath,
            'mime_type' => $upload['mime'],
            'extension' => $upload['extension'],
            'byte_size' => $upload['bytes'],
            'width_px' => $upload['width'],
            'height_px' => $upload['height'],
            'dpi_target' => null,
            'content_hash' => $upload['hash'],
        ]);

        PrivateStorage::delete($upload['relative_path']);
    }

    /**
     * Rebuilds the canvas description from scratch; nothing sent by the browser
     * is trusted beyond bounded numbers and allow-listed fonts and colors.
     *
     * @param array<string, mixed> $design
     * @return array{schema: int, objects: list<array<string, mixed>>}
     */
    public static function sanitizeDesign(array $design, ProductSettings $settings): array
    {
        $rawObjects = isset($design['objects']) && is_array($design['objects']) ? $design['objects'] : [];
        $objects = [];
        $textBoxes = 0;
        $images = 0;

        foreach ($rawObjects as $rawObject) {
            if (!is_array($rawObject) || count($objects) >= self::MAX_OBJECTS) {
                continue;
            }

            $type = sanitize_key((string) ($rawObject['type'] ?? ''));
            if ($type === 'text' && $settings->allowText) {
                if ($textBoxes >= $settings->maxTextBoxes) {
                    continue;
                }
                $text = sanitize_text_field((string) ($rawObject['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $font = sanitize_text_field((string) ($rawObject['font'] ?? ''));
                $color = sanitize_hex_color((string) ($rawObject['color'] ?? ''));
                $objects[] = [
                    'type' => 'text',
                    'text' => mb_substr($text, 0, self::MAX_TEXT_LENGTH),
                    'font' => $settings->isFontAllowed($font) ? $font : ($settings->fonts[0] ?? 'Arial'),
                    'color' => is_string($color) && $settings->isColorAllowed($color) ? $color : ($settings->colors[0] ?? '#111111'),
                    'left' => self::ratio($rawObject['left'] ?? 0.5),
                    'top' => self::ratio($rawObject['top'] ?? 0.5),
                    'fontSize' => self::ratio($rawObject['fontSize'] ?? 0.1),
                    'angle' => self::angle($rawObject['angle'] ?? 0),
                    'align' => in_array(($rawObject['align'] ?? ''), ['left', 'center', 'right'], true)
                        ? (string) $rawObject['align']
                        : 'center',
                ];
                $textBoxes++;
                continue;
            }

            if ($type === 'image' && $settings->allowImage && $images < 1) {
                $objects[] = [
                    'type' => 'image',
                    'left' => self::ratio($rawObject['left'] ?? 0.5),
                    'top' => self::ratio($rawObject['top'] ?? 0.5),
                    'scaleX' => self::scale($rawObject['scaleX'] ?? 1),
                    'scaleY' => self::scale($rawObject['scaleY'] ?? 1),
                    'angle' => self::angle($rawObject['angle'] ?? 0),
                ];
                $images++;
            }
        }

        if ($objects === []) {
            throw new \RuntimeException(__('Adicione texto ou imagem antes de confirmar a arte.', 'petshop-core'));
        }

        return ['schema' => Personalization::DESIGN_SCHEMA_VERSION, 'objects' => $objects];
    }

    /**
     * @param array{schema: int, objects: list<array<string, mixed>>} $design
     */
    private static function summarize(array $design, bool $hasUpload): string
    {
        $texts = [];
        foreach ($design['objects'] as $object) {
            if (($object['type'] ?? '') === 'text' && isset($object['text'])) {
                $texts[] = (string) $object['text'];
            }
        }

        $parts = [];
        if ($texts !== []) {
            $parts[] = sprintf(__('Texto: %s', 'petshop-core'), implode(' / ', $texts));
        }
        if ($hasUpload) {
            $parts[] = __('Imagem enviada pelo cliente', 'petshop-core');
        }
        if ($parts === []) {
            $parts[] = __('Arte personalizada', 'petshop-core');
        }

        return mb_substr(implode(' • ', $parts), 0, self::MAX_SUMMARY_LENGTH);
    }

    private static function ratio(mixed $value): float
    {
        $number = is_numeric($value) ? (float) $value : 0.5;

        return round(max(-1.0, min(2.0, $number)), 5);
    }

    private static function scale(mixed $value): float
    {
        $number = is_numeric($value) ? (float) $value : 1.0;

        return round(max(0.01, min(20.0, $number)), 5);
    }

    private static function angle(mixed $value): float
    {
        $number = is_numeric($value) ? (float) $value : 0.0;

        return round(fmod($number, 360.0), 3);
    }
}
