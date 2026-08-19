<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Application;

use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Domain\PersonalizationStatus;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;

defined('ABSPATH') || exit;

/**
 * Freezes the personalization into the order item.
 *
 * Runs after the order and its items were persisted, so it is safe to call
 * repeatedly for the same order (retries, webhooks, admin recalculations).
 */
final class SnapshotOrderItem
{
    public const META_PUBLIC_ID = '_petshop_personalization_public_id';
    public const META_SUMMARY = '_petshop_personalization_summary';
    public const META_HASH = '_petshop_personalization_hash';
    public const META_SCHEMA = '_petshop_personalization_schema';

    public static function forOrder(\WC_Order $order): void
    {
        foreach ($order->get_items() as $itemId => $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $publicId = (string) $item->get_meta(self::META_PUBLIC_ID, true);
            if (!PersonalizationRepository::isPublicId($publicId)) {
                continue;
            }

            $personalization = PersonalizationRepository::findByPublicId($publicId);
            if (!$personalization instanceof Personalization) {
                continue;
            }

            self::attach($personalization, $order, (int) $itemId, $item);
        }
    }

    private static function attach(
        Personalization $personalization,
        \WC_Order $order,
        int $itemId,
        \WC_Order_Item_Product $item
    ): void {
        if ($personalization->orderId !== null && $personalization->orderId !== $order->get_id()) {
            error_log(sprintf(
                'Petshop personalization %s já vinculada ao pedido #%d; vínculo com #%d ignorado.',
                $personalization->publicId,
                $personalization->orderId,
                $order->get_id()
            ));

            return;
        }

        $customerId = (int) $order->get_customer_id();
        $updated = PersonalizationRepository::attachToOrder(
            $personalization,
            $order->get_id(),
            $itemId,
            $customerId > 0 ? $customerId : null
        );

        $item->update_meta_data(self::META_SUMMARY, $updated->textSummary);
        $item->update_meta_data(self::META_HASH, $updated->snapshotHash);
        $item->update_meta_data(self::META_SCHEMA, (string) $updated->designSchemaVersion);
        $item->save();

        $actorId = $customerId > 0 ? $customerId : null;
        if ($updated->status === PersonalizationStatus::Draft) {
            $updated = TransitionStatus::applyIfPossible($updated, PersonalizationStatus::Cart, $actorId, 'Item enviado ao pedido.') ?? $updated;
        }

        TransitionStatus::applyIfPossible(
            $updated,
            PersonalizationStatus::AwaitingPayment,
            $actorId,
            sprintf('Pedido #%d criado.', $order->get_id())
        );
    }
}
