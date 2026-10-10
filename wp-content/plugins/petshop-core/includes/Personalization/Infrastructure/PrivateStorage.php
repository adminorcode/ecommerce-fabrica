<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Infrastructure;

defined('ABSPATH') || exit;

/**
 * Private filesystem storage outside the WordPress document root.
 */
final class PrivateStorage
{
    public const HEALTH_OK = 'ok';
    public const HEALTH_MISSING = 'missing';
    public const HEALTH_NOT_WRITABLE = 'not_writable';
    public const HEALTH_UNDER_WEBROOT = 'under_webroot';

    public static function root(): string
    {
        if (defined('PETSHOP_PERSONALIZATION_STORAGE') && is_string(PETSHOP_PERSONALIZATION_STORAGE) && PETSHOP_PERSONALIZATION_STORAGE !== '') {
            return rtrim(PETSHOP_PERSONALIZATION_STORAGE, "/\\");
        }

        $filtered = apply_filters('petshop_personalization_storage_root', '/var/petshop-personalizations');
        return is_string($filtered) && $filtered !== '' ? rtrim($filtered, "/\\") : '/var/petshop-personalizations';
    }

    /**
     * @return array{status: string, path: string, writable: bool, under_webroot: bool, message: string}
     */
    public static function health(): array
    {
        $path = self::root();
        $underWebroot = self::isUnderWebroot($path);
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);

        if ($underWebroot) {
            return [
                'status' => self::HEALTH_UNDER_WEBROOT,
                'path' => $path,
                'writable' => $writable,
                'under_webroot' => true,
                'message' => 'Storage está sob o document root e não pode ser usado.',
            ];
        }

        if (!$exists) {
            return [
                'status' => self::HEALTH_MISSING,
                'path' => $path,
                'writable' => false,
                'under_webroot' => false,
                'message' => 'Diretório de storage ausente.',
            ];
        }

        if (!$writable) {
            return [
                'status' => self::HEALTH_NOT_WRITABLE,
                'path' => $path,
                'writable' => false,
                'under_webroot' => false,
                'message' => 'Diretório de storage não é gravável.',
            ];
        }

        return [
            'status' => self::HEALTH_OK,
            'path' => $path,
            'writable' => true,
            'under_webroot' => false,
            'message' => 'Storage saudável.',
        ];
    }

    public static function assertHealthy(): void
    {
        $health = self::health();
        if ($health['status'] !== self::HEALTH_OK) {
            throw new \RuntimeException($health['message']);
        }
    }

    public static function ensureReady(): void
    {
        $path = self::root();
        if (self::isUnderWebroot($path)) {
            throw new \RuntimeException('Storage sob document root recusado.');
        }

        if (!is_dir($path) && !wp_mkdir_p($path)) {
            throw new \RuntimeException('Não foi possível criar o diretório de storage.');
        }

        self::assertHealthy();
        self::writeDenyHtaccess($path);
    }

    public static function absolutePath(string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            throw new \InvalidArgumentException('Caminho relativo inválido.');
        }

        $root = self::root();
        $full = $root . '/' . $relative;
        $realRoot = realpath($root);
        if ($realRoot === false) {
            throw new \RuntimeException('Storage root inválido.');
        }

        $parent = dirname($full);
        if (!is_dir($parent)) {
            wp_mkdir_p($parent);
        }

        $resolvedParent = realpath($parent);
        if ($resolvedParent === false || !str_starts_with($resolvedParent, $realRoot)) {
            throw new \RuntimeException('Path traversal bloqueado.');
        }

        return $full;
    }

    public static function writeBinary(string $relative, string $contents): string
    {
        self::ensureReady();
        $full = self::absolutePath($relative);
        $written = file_put_contents($full, $contents, LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException('Falha ao gravar arquivo privado.');
        }

        return $relative;
    }

    public static function readBinary(string $relative): string
    {
        $full = self::absolutePath($relative);
        if (!is_file($full)) {
            throw new \RuntimeException('Arquivo privado não encontrado.');
        }

        $contents = file_get_contents($full);
        if ($contents === false) {
            throw new \RuntimeException('Falha ao ler arquivo privado.');
        }

        return $contents;
    }

    public static function delete(string $relative): void
    {
        $full = self::absolutePath($relative);
        if (is_file($full)) {
            unlink($full);
        }
    }

    public static function opaqueRelativePath(string $publicId, string $type, string $extension): string
    {
        $safeType = preg_replace('/[^a-z0-9_-]/i', '', $type) ?: 'file';
        $safeExt = preg_replace('/[^a-z0-9]/i', '', strtolower($extension)) ?: 'bin';
        $uuid = preg_replace('/[^a-f0-9-]/i', '', $publicId) ?: wp_generate_uuid4();
        $shard = substr(hash('sha256', $uuid), 0, 2);

        return sprintf('%s/%s/%s-%s.%s', $shard, $uuid, $safeType, wp_generate_password(12, false, false), $safeExt);
    }

    private static function isUnderWebroot(string $path): bool
    {
        $webroot = realpath(ABSPATH);
        if ($webroot === false) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);
        $web = str_replace('\\', '/', $webroot);

        if (str_starts_with($normalized, $web . '/') || $normalized === $web) {
            return true;
        }

        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['basedir'])) {
            $uploadBase = str_replace('\\', '/', (string) $uploads['basedir']);
            if (str_starts_with($normalized, $uploadBase . '/') || $normalized === $uploadBase) {
                return true;
            }
        }

        return false;
    }

    private static function writeDenyHtaccess(string $path): void
    {
        $htaccess = $path . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n", LOCK_EX);
        }

        $index = $path . '/index.html';
        if (!is_file($index)) {
            file_put_contents($index, '', LOCK_EX);
        }
    }
}
