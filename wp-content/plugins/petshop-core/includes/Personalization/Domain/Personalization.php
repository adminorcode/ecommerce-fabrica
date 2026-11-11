<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Domain;

defined('ABSPATH') || exit;

/**
 * Aggregate root for a personalization draft/order artifact.
 *
 * @phpstan-type PersonalizationRow array{
 *   id?: int,
 *   public_id: string,
 *   user_id: int|null,
 *   cart_hash: string|null,
 *   product_id: int,
 *   variation_id: int|null,
 *   order_id: int|null,
 *   order_item_id: int|null,
 *   status: string,
 *   status_version: int,
 *   design_schema_version: int,
 *   design_json: string,
 *   config_snapshot: string,
 *   text_summary: string,
 *   snapshot_hash: string,
 *   created_at: string,
 *   updated_at: string,
 *   expires_at: string|null,
 *   completed_at: string|null
 * }
 */
final class Personalization
{
    public const DESIGN_SCHEMA_VERSION = 1;

    public function __construct(
        public readonly ?int $id,
        public readonly string $publicId,
        public readonly ?int $userId,
        public readonly ?string $cartHash,
        public readonly int $productId,
        public readonly ?int $variationId,
        public readonly ?int $orderId,
        public readonly ?int $orderItemId,
        public readonly PersonalizationStatus $status,
        public readonly int $statusVersion,
        public readonly int $designSchemaVersion,
        public readonly string $designJson,
        public readonly string $configSnapshot,
        public readonly string $textSummary,
        public readonly string $snapshotHash,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $expiresAt,
        public readonly ?string $completedAt,
    ) {
    }

    /**
     * @param PersonalizationRow $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) $row['public_id'],
            isset($row['user_id']) && $row['user_id'] !== null && $row['user_id'] !== ''
                ? (int) $row['user_id']
                : null,
            isset($row['cart_hash']) && is_string($row['cart_hash']) && $row['cart_hash'] !== ''
                ? $row['cart_hash']
                : null,
            (int) $row['product_id'],
            isset($row['variation_id']) && $row['variation_id'] !== null && $row['variation_id'] !== ''
                ? (int) $row['variation_id']
                : null,
            isset($row['order_id']) && $row['order_id'] !== null && $row['order_id'] !== ''
                ? (int) $row['order_id']
                : null,
            isset($row['order_item_id']) && $row['order_item_id'] !== null && $row['order_item_id'] !== ''
                ? (int) $row['order_item_id']
                : null,
            PersonalizationStatus::from((string) $row['status']),
            (int) $row['status_version'],
            (int) $row['design_schema_version'],
            (string) $row['design_json'],
            (string) $row['config_snapshot'],
            (string) $row['text_summary'],
            (string) $row['snapshot_hash'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
            isset($row['expires_at']) && is_string($row['expires_at']) && $row['expires_at'] !== ''
                ? $row['expires_at']
                : null,
            isset($row['completed_at']) && is_string($row['completed_at']) && $row['completed_at'] !== ''
                ? $row['completed_at']
                : null,
        );
    }

    public function withStatus(PersonalizationStatus $target): self
    {
        if (!$this->status->canTransitionTo($target)) {
            throw InvalidStatusTransition::from($this->status, $target);
        }

        $now = gmdate('Y-m-d H:i:s');
        return new self(
            $this->id,
            $this->publicId,
            $this->userId,
            $this->cartHash,
            $this->productId,
            $this->variationId,
            $this->orderId,
            $this->orderItemId,
            $target,
            $this->statusVersion + 1,
            $this->designSchemaVersion,
            $this->designJson,
            $this->configSnapshot,
            $this->textSummary,
            $this->snapshotHash,
            $this->createdAt,
            $now,
            $this->expiresAt,
            $target === PersonalizationStatus::Completed ? $now : $this->completedAt,
        );
    }
}
