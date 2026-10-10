<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\WooCommerce;

use Petshop\Core\Personalization\Application\SnapshotOrderItem;
use Petshop\Core\Personalization\Application\TransitionStatus;
use Petshop\Core\Personalization\Domain\PersonalizationStatus;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;

defined('ABSPATH') || exit;

/**
 * Keeps the production queue in sync with WooCommerce order events (HPOS-safe,
 * idempotent for repeated webhooks).
 */
final class OrderIntegration
{
    public static function bootstrap(): void
    {
        add_action('woocommerce_checkout_order_created', [self::class, 'snapshot']);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'snapshot']);
        add_action('woocommerce_payment_complete', [self::class, 'onPaymentComplete']);
        add_action('woocommerce_order_status_processing', [self::class, 'onPaid']);
        add_action('woocommerce_order_status_completed', [self::class, 'onPaid']);
        add_action('woocommerce_order_status_cancelled', [self::class, 'onClosed']);
        add_action('woocommerce_order_status_failed', [self::class, 'onClosed']);
        add_action('woocommerce_order_status_refunded', [self::class, 'onClosed']);
        add_filter('woocommerce_order_item_display_meta_key', [self::class, 'labelOrderItemMeta'], 10, 3);
    }

    public static function snapshot(mixed $order): void
    {
        $order = self::resolveOrder($order);
        if (!$order instanceof \WC_Order) {
            return;
        }

        try {
            SnapshotOrderItem::forOrder($order);
        } catch (\Throwable $error) {
            error_log(sprintf('Petshop personalization snapshot falhou no pedido #%d: %s', $order->get_id(), $error->getMessage()));
        }
    }

    public static function onPaymentComplete(mixed $orderId): void
    {
        self::onPaid($orderId);
    }

    public static function onPaid(mixed $orderId): void
    {
        $order = self::resolveOrder($orderId);
        if (!$order instanceof \WC_Order) {
            return;
        }

        SnapshotOrderItem::forOrder($order);

        foreach (PersonalizationRepository::findByOrder($order->get_id()) as $personalization) {
            TransitionStatus::applyIfPossible(
                $personalization,
                PersonalizationStatus::Review,
                null,
                sprintf('Pagamento confirmado no pedido #%d.', $order->get_id())
            );
        }
    }

    public static function onClosed(mixed $orderId): void
    {
        $order = self::resolveOrder($orderId);
        if (!$order instanceof \WC_Order) {
            return;
        }

        foreach (PersonalizationRepository::findByOrder($order->get_id()) as $personalization) {
            TransitionStatus::applyIfPossible(
                $personalization,
                PersonalizationStatus::Cancelled,
                null,
                sprintf('Pedido #%d encerrado como %s.', $order->get_id(), $order->get_status())
            );
        }
    }

    /**
     * @param \WC_Order_Item $item
     */
    public static function labelOrderItemMeta(string $displayKey, mixed $meta, mixed $item): string
    {
        unset($item);

        $key = is_object($meta) && isset($meta->key) ? (string) $meta->key : '';

        return match ($key) {
            SnapshotOrderItem::META_PUBLIC_ID => __('ID da personalização', 'petshop-core'),
            SnapshotOrderItem::META_SUMMARY => __('Personalização', 'petshop-core'),
            SnapshotOrderItem::META_HASH => __('Hash do snapshot', 'petshop-core'),
            SnapshotOrderItem::META_SCHEMA => __('Versão do schema', 'petshop-core'),
            default => $displayKey,
        };
    }

    private static function resolveOrder(mixed $candidate): ?\WC_Order
    {
        if ($candidate instanceof \WC_Order) {
            return $candidate;
        }

        $order = is_numeric($candidate) ? wc_get_order((int) $candidate) : null;

        return $order instanceof \WC_Order ? $order : null;
    }
}
