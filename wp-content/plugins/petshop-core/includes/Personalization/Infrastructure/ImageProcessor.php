<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Infrastructure;

defined('ABSPATH') || exit;

/**
 * Defensive image validation and re-encoding.
 *
 * Every byte that reaches private storage is inspected with the real image
 * signature and, when GD is available, re-encoded to drop EXIF and any
 * polyglot payload appended to the file.
 */
final class ImageProcessor
{
    /**
     * @var array<string, string>
     */
    private const ALLOWED_UPLOADS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function hasGd(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagepng');
    }

    /**
     * @return array{binary: string, mime: string, extension: string, width: int, height: int, hash: string, bytes: int}
     */
    public static function sanitizeUpload(string $binary): array
    {
        $maxBytes = RetentionPolicy::maxUploadBytes();
        if ($binary === '') {
            throw new \RuntimeException(__('Arquivo vazio.', 'petshop-core'));
        }
        if (strlen($binary) > $maxBytes) {
            throw new \RuntimeException(__('Arquivo maior que o limite configurado.', 'petshop-core'));
        }

        $info = @getimagesizefromstring($binary);
        if (!is_array($info) || !isset($info[0], $info[1], $info['mime'])) {
            throw new \RuntimeException(__('Arquivo não é uma imagem válida.', 'petshop-core'));
        }

        $mime = strtolower((string) $info['mime']);
        if (!isset(self::ALLOWED_UPLOADS[$mime])) {
            throw new \RuntimeException(__('Formato não permitido. Envie JPEG, PNG ou WebP.', 'petshop-core'));
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        self::assertPixelBudget($width, $height);

        $sanitized = self::reencode($binary, $mime);

        return [
            'binary' => $sanitized['binary'],
            'mime' => $sanitized['mime'],
            'extension' => self::ALLOWED_UPLOADS[$sanitized['mime']] ?? 'png',
            'width' => $sanitized['width'],
            'height' => $sanitized['height'],
            'hash' => hash('sha256', $sanitized['binary']),
            'bytes' => strlen($sanitized['binary']),
        ];
    }

    /**
     * Accepts a `data:image/png;base64,...` payload produced by the canvas.
     */
    public static function decodePngDataUrl(string $dataUrl): string
    {
        if (!preg_match('#^data:image/png;base64,#i', $dataUrl)) {
            throw new \RuntimeException(__('Prévia inválida.', 'petshop-core'));
        }

        $encoded = substr($dataUrl, (int) strpos($dataUrl, ',') + 1);
        $binary = base64_decode(strtr($encoded, ' ', '+'), true);
        if (!is_string($binary) || $binary === '') {
            throw new \RuntimeException(__('Prévia inválida.', 'petshop-core'));
        }

        return $binary;
    }

    /**
     * @return array{binary: string, width: int, height: int, hash: string, bytes: int, mime: string, extension: string}
     */
    public static function sanitizePng(string $binary, ?int $expectedWidth = null, ?int $expectedHeight = null): array
    {
        $maxBytes = RetentionPolicy::maxUploadBytes() * 2;
        if (strlen($binary) > $maxBytes) {
            throw new \RuntimeException(__('Imagem gerada excede o limite de bytes.', 'petshop-core'));
        }

        $info = @getimagesizefromstring($binary);
        if (!is_array($info) || ($info['mime'] ?? '') !== 'image/png') {
            throw new \RuntimeException(__('A imagem gerada precisa ser PNG.', 'petshop-core'));
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        self::assertPixelBudget($width, $height);

        if ($expectedWidth !== null && $expectedHeight !== null && ($width !== $expectedWidth || $height !== $expectedHeight)) {
            throw new \RuntimeException(
                sprintf(
                    /* translators: 1: expected width, 2: expected height, 3: received width, 4: received height */
                    __('Dimensões do arquivo de produção divergem do produto (esperado %1$d×%2$d, recebido %3$d×%4$d).', 'petshop-core'),
                    $expectedWidth,
                    $expectedHeight,
                    $width,
                    $height
                )
            );
        }

        $sanitized = self::reencode($binary, 'image/png');

        return [
            'binary' => $sanitized['binary'],
            'width' => $sanitized['width'],
            'height' => $sanitized['height'],
            'hash' => hash('sha256', $sanitized['binary']),
            'bytes' => strlen($sanitized['binary']),
            'mime' => 'image/png',
            'extension' => 'png',
        ];
    }

    /**
     * Returns a downscaled PNG data URL so the editor can display an upload
     * that is never exposed through a public URL.
     */
    public static function previewDataUrl(string $binary, int $maxEdge = 1200): string
    {
        if (!self::hasGd()) {
            return '';
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return '';
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1.0, $maxEdge / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($image);

        ob_start();
        imagepng($resized, null, 6);
        $encoded = (string) ob_get_clean();
        imagedestroy($resized);

        return 'data:image/png;base64,' . base64_encode($encoded);
    }

    private static function assertPixelBudget(int $width, int $height): void
    {
        if ($width < 1 || $height < 1) {
            throw new \RuntimeException(__('Imagem com dimensões inválidas.', 'petshop-core'));
        }

        $megapixels = ($width * $height) / 1_000_000;
        if ($megapixels > RetentionPolicy::maxMegapixels()) {
            throw new \RuntimeException(__('Imagem excede o limite de megapixels permitido.', 'petshop-core'));
        }
    }

    /**
     * @return array{binary: string, mime: string, width: int, height: int}
     */
    private static function reencode(string $binary, string $mime): array
    {
        $info = @getimagesizefromstring($binary);
        $width = is_array($info) ? (int) $info[0] : 0;
        $height = is_array($info) ? (int) $info[1] : 0;

        if (!self::hasGd()) {
            error_log('Petshop personalization: GD ausente; arquivo mantido sem reprocessamento.');

            return ['binary' => $binary, 'mime' => $mime, 'width' => $width, 'height' => $height];
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            throw new \RuntimeException(__('Não foi possível processar a imagem enviada.', 'petshop-core'));
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        if ($mime === 'image/jpeg') {
            imagejpeg($image, null, 90);
        } else {
            imagepng($image, null, 6);
        }
        $encoded = (string) ob_get_clean();

        $result = [
            'binary' => $encoded,
            'mime' => $mime === 'image/jpeg' ? 'image/jpeg' : 'image/png',
            'width' => imagesx($image),
            'height' => imagesy($image),
        ];
        imagedestroy($image);

        if ($result['binary'] === '') {
            throw new \RuntimeException(__('Falha ao normalizar a imagem.', 'petshop-core'));
        }

        return $result;
    }
}
