<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Infrastructure;

defined('ABSPATH') || exit;

/**
 * Short-lived registry that links an uploaded original to the session that sent
 * it, so the browser never handles server paths.
 */
final class UploadVault
{
    private const PREFIX = 'petshop_pz_upload_';
    private const TTL = 2 * HOUR_IN_SECONDS;

    /**
     * @param array{relative_path: string, mime: string, extension: string, width: int, height: int, hash: string, bytes: int} $file
     */
    public static function store(array $file, int $productId): string
    {
        $token = wp_generate_uuid4();
        $userId = get_current_user_id();
        set_transient(self::PREFIX . $token, [
            'file' => $file,
            'product_id' => $productId,
            'user_id' => $userId > 0 ? $userId : null,
            'session_hash' => SessionIdentity::hash(),
            // Legacy field kept for in-flight tokens created before this shape.
            'owner' => self::legacyOwner(),
        ], self::TTL);

        return $token;
    }

    /**
     * @return array{relative_path: string, mime: string, extension: string, width: int, height: int, hash: string, bytes: int}|null
     */
    public static function claim(string $token, int $productId): ?array
    {
        if (!PersonalizationRepository::isPublicId($token)) {
            return null;
        }

        $record = get_transient(self::PREFIX . $token);
        if (!is_array($record) || !isset($record['file']) || !is_array($record['file'])) {
            return null;
        }

        if ((int) ($record['product_id'] ?? 0) !== $productId) {
            return null;
        }

        if (!self::ownsRecord($record)) {
            return null;
        }

        delete_transient(self::PREFIX . $token);

        /** @var array{relative_path: string, mime: string, extension: string, width: int, height: int, hash: string, bytes: int} $file */
        $file = $record['file'];

        return $file;
    }

    public static function discard(string $token): void
    {
        if (PersonalizationRepository::isPublicId($token)) {
            delete_transient(self::PREFIX . $token);
        }
    }

    /**
     * @param array<string, mixed> $record
     */
    private static function ownsRecord(array $record): bool
    {
        $userId = get_current_user_id();
        $storedUser = isset($record['user_id']) ? (int) $record['user_id'] : 0;

        if ($userId > 0 && $storedUser > 0 && $userId === $storedUser) {
            return true;
        }

        $session = SessionIdentity::hash();
        $storedSession = isset($record['session_hash']) && is_string($record['session_hash'])
            ? $record['session_hash']
            : null;
        if ($session !== null && $storedSession !== null && hash_equals($storedSession, $session)) {
            return true;
        }

        // Guest started the upload, then logged in before confirming the draft.
        // Possession of the UUID token + same product is the binding; session hash
        // often rotates when WooCommerce merges the cart after login.
        if ($userId > 0 && $storedUser === 0 && ($storedSession !== null || isset($record['owner']))) {
            return true;
        }

        $legacyOwner = isset($record['owner']) && is_string($record['owner']) ? $record['owner'] : '';
        if ($legacyOwner !== '' && hash_equals($legacyOwner, self::legacyOwner())) {
            return true;
        }

        return false;
    }

    private static function legacyOwner(): string
    {
        $userId = get_current_user_id();
        if ($userId > 0) {
            return 'user:' . $userId;
        }

        $session = SessionIdentity::hash();

        return $session !== null ? 'session:' . $session : '';
    }
}
