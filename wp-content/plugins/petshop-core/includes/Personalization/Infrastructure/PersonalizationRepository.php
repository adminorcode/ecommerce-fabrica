<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Infrastructure;

use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Domain\PersonalizationStatus;

defined('ABSPATH') || exit;

/**
 * Persistence for personalizations, their private files and status history.
 */
final class PersonalizationRepository
{
    public const FILE_ORIGINAL = 'original';
    public const FILE_PREVIEW = 'preview';
    public const FILE_PRODUCTION = 'production';

    /**
     * @var list<string>
     */
    public const FILE_TYPES = [self::FILE_ORIGINAL, self::FILE_PREVIEW, self::FILE_PRODUCTION];

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'petshop_personalizations';
    }

    public static function filesTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'petshop_personalization_files';
    }

    public static function historyTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'petshop_personalization_status_history';
    }

    public static function tablesExist(): bool
    {
        global $wpdb;

        foreach ([self::table(), self::filesTable(), self::historyTable()] as $table) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($found !== $table) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{
     *   product_id: int,
     *   variation_id?: int|null,
     *   user_id?: int|null,
     *   cart_hash?: string|null,
     *   design_json: string,
     *   config_snapshot: string,
     *   text_summary: string,
     *   expires_at?: string|null
     * } $data
     */
    public static function createDraft(array $data): Personalization
    {
        global $wpdb;

        $now = gmdate('Y-m-d H:i:s');
        $publicId = wp_generate_uuid4();
        $designJson = (string) $data['design_json'];
        $configSnapshot = (string) $data['config_snapshot'];

        $row = [
            'public_id' => $publicId,
            'user_id' => isset($data['user_id']) && (int) $data['user_id'] > 0 ? (int) $data['user_id'] : null,
            'cart_hash' => $data['cart_hash'] ?? null,
            'product_id' => (int) $data['product_id'],
            'variation_id' => isset($data['variation_id']) && (int) $data['variation_id'] > 0 ? (int) $data['variation_id'] : null,
            'order_id' => null,
            'order_item_id' => null,
            'status' => PersonalizationStatus::Draft->value,
            'status_version' => 1,
            'design_schema_version' => Personalization::DESIGN_SCHEMA_VERSION,
            'design_json' => $designJson,
            'config_snapshot' => $configSnapshot,
            'text_summary' => (string) $data['text_summary'],
            'snapshot_hash' => self::snapshotHash($designJson, $configSnapshot),
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => $data['expires_at'] ?? null,
            'completed_at' => null,
        ];

        $inserted = $wpdb->insert(self::table(), $row);
        if ($inserted === false) {
            throw new \RuntimeException('Não foi possível criar a personalização.');
        }

        $row['id'] = (int) $wpdb->insert_id;
        self::recordHistory($row['id'], null, PersonalizationStatus::Draft, null, 'Rascunho criado.');

        return Personalization::fromRow($row);
    }

    public static function snapshotHash(string $designJson, string $configSnapshot): string
    {
        return hash('sha256', $designJson . '|' . $configSnapshot);
    }

    public static function findByPublicId(string $publicId): ?Personalization
    {
        global $wpdb;

        if (!self::isPublicId($publicId)) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE public_id = %s LIMIT 1', $publicId),
            ARRAY_A
        );

        return is_array($row) ? Personalization::fromRow($row) : null;
    }

    public static function findById(int $id): ?Personalization
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1', $id),
            ARRAY_A
        );

        return is_array($row) ? Personalization::fromRow($row) : null;
    }

    public static function findByOrderItem(int $orderItemId): ?Personalization
    {
        global $wpdb;

        if ($orderItemId <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE order_item_id = %d LIMIT 1', $orderItemId),
            ARRAY_A
        );

        return is_array($row) ? Personalization::fromRow($row) : null;
    }

    /**
     * @return list<Personalization>
     */
    public static function findByOrder(int $orderId): array
    {
        global $wpdb;

        if ($orderId <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE order_id = %d ORDER BY id ASC',
                $orderId
            ),
            ARRAY_A
        );

        return self::mapRows(is_array($rows) ? $rows : []);
    }

    /**
     * @return list<Personalization>
     */
    public static function findByUser(int $userId): array
    {
        global $wpdb;

        if ($userId <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id ASC',
                $userId
            ),
            ARRAY_A
        );

        return self::mapRows(is_array($rows) ? $rows : []);
    }

    public static function attachToCart(Personalization $personalization, ?string $cartHash, ?int $userId): Personalization
    {
        global $wpdb;

        $now = gmdate('Y-m-d H:i:s');
        $wpdb->update(
            self::table(),
            [
                'cart_hash' => $cartHash,
                'user_id' => $userId !== null && $userId > 0 ? $userId : $personalization->userId,
                'updated_at' => $now,
            ],
            ['id' => (int) $personalization->id]
        );

        return self::findById((int) $personalization->id) ?? $personalization;
    }

    public static function attachToOrder(Personalization $personalization, int $orderId, int $orderItemId, ?int $userId): Personalization
    {
        global $wpdb;

        $wpdb->update(
            self::table(),
            [
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'user_id' => $userId !== null && $userId > 0 ? $userId : $personalization->userId,
                'expires_at' => null,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => (int) $personalization->id]
        );

        return self::findById((int) $personalization->id) ?? $personalization;
    }

    /**
     * Persists a transition already validated by the domain aggregate.
     */
    public static function persistStatus(Personalization $personalization, ?PersonalizationStatus $from, ?int $actorUserId, string $note): void
    {
        global $wpdb;

        $wpdb->update(
            self::table(),
            [
                'status' => $personalization->status->value,
                'status_version' => $personalization->statusVersion,
                'updated_at' => $personalization->updatedAt,
                'completed_at' => $personalization->completedAt,
            ],
            ['id' => (int) $personalization->id]
        );

        self::recordHistory((int) $personalization->id, $from, $personalization->status, $actorUserId, $note);
    }

    public static function recordHistory(int $personalizationId, ?PersonalizationStatus $from, PersonalizationStatus $to, ?int $actorUserId, string $note): void
    {
        global $wpdb;

        $wpdb->insert(self::historyTable(), [
            'personalization_id' => $personalizationId,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            'note' => $note !== '' ? $note : null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array{from_status: string|null, to_status: string, actor_user_id: int|null, note: string, created_at: string}>
     */
    public static function history(int $personalizationId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT from_status, to_status, actor_user_id, note, created_at FROM ' . self::historyTable()
                . ' WHERE personalization_id = %d ORDER BY id ASC',
                $personalizationId
            ),
            ARRAY_A
        );

        $history = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $history[] = [
                'from_status' => isset($row['from_status']) && $row['from_status'] !== null ? (string) $row['from_status'] : null,
                'to_status' => (string) $row['to_status'],
                'actor_user_id' => isset($row['actor_user_id']) && $row['actor_user_id'] !== null ? (int) $row['actor_user_id'] : null,
                'note' => (string) ($row['note'] ?? ''),
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $history;
    }

    /**
     * @param array{
     *   relative_path: string,
     *   mime_type: string,
     *   extension: string,
     *   byte_size: int,
     *   width_px?: int|null,
     *   height_px?: int|null,
     *   dpi_target?: int|null,
     *   content_hash: string
     * } $file
     */
    public static function putFile(int $personalizationId, string $type, array $file): void
    {
        global $wpdb;

        if (!in_array($type, self::FILE_TYPES, true)) {
            throw new \InvalidArgumentException('Tipo de arquivo não suportado.');
        }

        $existing = self::file($personalizationId, $type);
        $row = [
            'personalization_id' => $personalizationId,
            'file_type' => $type,
            'relative_path' => $file['relative_path'],
            'mime_type' => $file['mime_type'],
            'extension' => $file['extension'],
            'byte_size' => (int) $file['byte_size'],
            'width_px' => isset($file['width_px']) ? (int) $file['width_px'] : null,
            'height_px' => isset($file['height_px']) ? (int) $file['height_px'] : null,
            'dpi_target' => isset($file['dpi_target']) ? (int) $file['dpi_target'] : null,
            'content_hash' => $file['content_hash'],
            'deleted_at' => null,
        ];

        if ($existing !== null) {
            $wpdb->update(self::filesTable(), $row, ['id' => (int) $existing['id']]);
            if ($existing['relative_path'] !== $file['relative_path']) {
                PrivateStorage::delete((string) $existing['relative_path']);
            }

            return;
        }

        $row['created_at'] = gmdate('Y-m-d H:i:s');
        $wpdb->insert(self::filesTable(), $row);
    }

    /**
     * @return array{id: int, file_type: string, relative_path: string, mime_type: string, extension: string, byte_size: int, width_px: int|null, height_px: int|null, dpi_target: int|null, content_hash: string, created_at: string}|null
     */
    public static function file(int $personalizationId, string $type): ?array
    {
        global $wpdb;

        if (!in_array($type, self::FILE_TYPES, true) || $personalizationId <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::filesTable()
                . ' WHERE personalization_id = %d AND file_type = %s AND deleted_at IS NULL LIMIT 1',
                $personalizationId,
                $type
            ),
            ARRAY_A
        );

        return is_array($row) ? self::normalizeFileRow($row) : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function files(int $personalizationId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::filesTable() . ' WHERE personalization_id = %d AND deleted_at IS NULL',
                $personalizationId
            ),
            ARRAY_A
        );

        $files = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $normalized = self::normalizeFileRow($row);
            $files[$normalized['file_type']] = $normalized;
        }

        return $files;
    }

    /**
     * @param array{status?: string, order_id?: int, product_id?: int, search?: string, per_page?: int, paged?: int} $args
     * @return array{items: list<Personalization>, total: int}
     */
    public static function search(array $args): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        $status = isset($args['status']) ? (string) $args['status'] : '';
        if ($status === 'queue') {
            $where[] = 'status IN (%s, %s, %s)';
            $params[] = PersonalizationStatus::Review->value;
            $params[] = PersonalizationStatus::Approved->value;
            $params[] = PersonalizationStatus::InProduction->value;
        } elseif ($status !== '' && PersonalizationStatus::tryFrom($status) instanceof PersonalizationStatus) {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        if (!empty($args['order_id'])) {
            $where[] = 'order_id = %d';
            $params[] = (int) $args['order_id'];
        }

        if (!empty($args['product_id'])) {
            $where[] = 'product_id = %d';
            $params[] = (int) $args['product_id'];
        }

        $search = isset($args['search']) ? trim((string) $args['search']) : '';
        if ($search !== '') {
            $where[] = '(public_id LIKE %s OR text_summary LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $perPage = max(1, min(100, (int) ($args['per_page'] ?? 20)));
        $paged = max(1, (int) ($args['paged'] ?? 1));
        $offset = ($paged - 1) * $perPage;
        $clause = implode(' AND ', $where);

        $countSql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . $clause;
        $total = (int) $wpdb->get_var($params === [] ? $countSql : $wpdb->prepare($countSql, ...$params));

        $listSql = 'SELECT * FROM ' . self::table() . ' WHERE ' . $clause . ' ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d';
        $listParams = array_merge($params, [$perPage, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($listSql, ...$listParams), ARRAY_A);

        return [
            'items' => self::mapRows(is_array($rows) ? $rows : []),
            'total' => $total,
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function countByStatus(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results('SELECT status, COUNT(*) AS total FROM ' . self::table() . ' GROUP BY status', ARRAY_A);
        $counts = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Abandoned drafts/carts without an order (hard purge candidates).
     *
     * @return list<Personalization>
     */
    public static function expiredByStatus(PersonalizationStatus $status, string $olderThanGmt, int $limit = 200): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table()
                . ' WHERE status = %s AND order_id IS NULL AND updated_at < %s ORDER BY updated_at ASC LIMIT %d',
                $status->value,
                $olderThanGmt,
                max(1, min(1000, $limit))
            ),
            ARRAY_A
        );

        return self::mapRows(is_array($rows) ? $rows : []);
    }

    /**
     * Cancelled/completed personalizations past retention that still have active files.
     *
     * @return list<Personalization>
     */
    public static function withActiveFilesPastRetention(PersonalizationStatus $status, string $olderThanGmt, int $limit = 200): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT DISTINCT p.* FROM ' . self::table() . ' p'
                . ' INNER JOIN ' . self::filesTable() . ' f ON f.personalization_id = p.id AND f.deleted_at IS NULL'
                . ' WHERE p.status = %s AND p.updated_at < %s'
                . ' ORDER BY p.updated_at ASC LIMIT %d',
                $status->value,
                $olderThanGmt,
                max(1, min(1000, $limit))
            ),
            ARRAY_A
        );

        return self::mapRows(is_array($rows) ? $rows : []);
    }

    /**
     * Deletes private files from disk and soft-marks file rows. Keeps the
     * personalization/order snapshot for audit history.
     */
    public static function purgeFiles(Personalization $personalization): int
    {
        global $wpdb;

        $id = (int) $personalization->id;
        if ($id <= 0) {
            return 0;
        }

        $removed = 0;
        foreach (self::files($id) as $file) {
            try {
                PrivateStorage::delete((string) $file['relative_path']);
            } catch (\Throwable $error) {
                error_log(sprintf(
                    'Petshop personalization retention: falha ao remover arquivo #%d (%s)',
                    (int) $file['id'],
                    $error->getMessage()
                ));
            }

            $wpdb->update(
                self::filesTable(),
                ['deleted_at' => gmdate('Y-m-d H:i:s')],
                ['id' => (int) $file['id']],
                ['%s'],
                ['%d']
            );
            $removed++;
        }

        return $removed;
    }

    /**
     * Removes the row, its history and every private file on disk.
     */
    public static function purge(Personalization $personalization): void
    {
        global $wpdb;

        $id = (int) $personalization->id;
        if ($id <= 0) {
            return;
        }

        self::purgeFiles($personalization);

        $wpdb->delete(self::filesTable(), ['personalization_id' => $id]);
        $wpdb->delete(self::historyTable(), ['personalization_id' => $id]);
        $wpdb->delete(self::table(), ['id' => $id]);
    }

    /**
     * @return array{checked: int, missing: list<string>, mismatched: list<string>, orphans: int}
     */
    public static function integrityReport(int $limit = 500): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT f.id, f.relative_path, f.content_hash, f.file_type, p.public_id'
                . ' FROM ' . self::filesTable() . ' f'
                . ' LEFT JOIN ' . self::table() . ' p ON p.id = f.personalization_id'
                . ' WHERE f.deleted_at IS NULL ORDER BY f.id DESC LIMIT %d',
                max(1, min(5000, $limit))
            ),
            ARRAY_A
        );

        $missing = [];
        $mismatched = [];
        $orphans = 0;
        $checked = 0;

        foreach (is_array($rows) ? $rows : [] as $row) {
            $checked++;
            $reference = sprintf('%s/%s', (string) ($row['public_id'] ?? 'orphan'), (string) $row['file_type']);
            if (($row['public_id'] ?? null) === null) {
                $orphans++;
                continue;
            }

            try {
                $contents = PrivateStorage::readBinary((string) $row['relative_path']);
            } catch (\Throwable) {
                $missing[] = $reference;
                continue;
            }

            if (hash('sha256', $contents) !== (string) $row['content_hash']) {
                $mismatched[] = $reference;
            }
        }

        return [
            'checked' => $checked,
            'missing' => $missing,
            'mismatched' => $mismatched,
            'orphans' => $orphans,
        ];
    }

    public static function isPublicId(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<Personalization>
     */
    private static function mapRows(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = Personalization::fromRow($row);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, file_type: string, relative_path: string, mime_type: string, extension: string, byte_size: int, width_px: int|null, height_px: int|null, dpi_target: int|null, content_hash: string, created_at: string}
     */
    private static function normalizeFileRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'file_type' => (string) $row['file_type'],
            'relative_path' => (string) $row['relative_path'],
            'mime_type' => (string) $row['mime_type'],
            'extension' => (string) $row['extension'],
            'byte_size' => (int) $row['byte_size'],
            'width_px' => isset($row['width_px']) && $row['width_px'] !== null ? (int) $row['width_px'] : null,
            'height_px' => isset($row['height_px']) && $row['height_px'] !== null ? (int) $row['height_px'] : null,
            'dpi_target' => isset($row['dpi_target']) && $row['dpi_target'] !== null ? (int) $row['dpi_target'] : null,
            'content_hash' => (string) $row['content_hash'],
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
