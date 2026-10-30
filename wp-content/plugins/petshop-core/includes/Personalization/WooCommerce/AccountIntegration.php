<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\WooCommerce;

use Petshop\Core\Personalization\Application\SnapshotOrderItem;
use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Http\DownloadController;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;

defined('ABSPATH') || exit;

/**
 * Buyer-facing preview and functional status on order details and e-mails.
 *
 * Only the preview is exposed: original and production files stay internal.
 */
final class AccountIntegration
{
    public static function bootstrap(): void
    {
        add_action('woocommerce_order_item_meta_end', [self::class, 'renderOrderItemDetails'], 10, 4);
    }

    public static function renderOrderItemDetails(mixed $itemId, mixed $item, mixed $order, bool $plainText = false): void
    {
        if (!$item instanceof \WC_Order_Item_Product || !$order instanceof \WC_Order) {
            return;
        }

        $publicId = (string) $item->get_meta(SnapshotOrderItem::META_PUBLIC_ID, true);
        if (!PersonalizationRepository::isPublicId($publicId)) {
            return;
        }

        $personalization = PersonalizationRepository::findByPublicId($publicId);
        if (!$personalization instanceof Personalization) {
            return;
        }

        $summary = $personalization->textSummary;
        $statusLabel = $personalization->status->label();

        if ($plainText) {
            echo "\n" . esc_html(sprintf(
                /* translators: 1: art summary, 2: production status */
                __('Personalização: %1$s — situação: %2$s', 'petshop-core'),
                $summary,
                $statusLabel
            )) . "\n";

            return;
        }

        $isEmail = did_action('woocommerce_email_header') > 0;
        $previewUrl = DownloadController::previewUrl(
            $personalization->publicId,
            $isEmail ? (string) $order->get_order_key() : ''
        );

        echo '<div class="petshop-personalization-order-item">';
        echo '<img src="' . esc_url($previewUrl) . '" alt="' . esc_attr__('Prévia da personalização', 'petshop-core')
            . '" width="120" style="max-width:120px;height:auto;display:block;margin:8px 0">';
        echo '<p style="margin:0"><strong>' . esc_html__('Personalização:', 'petshop-core') . '</strong> ' . esc_html($summary) . '</p>';
        echo '<p style="margin:0"><strong>' . esc_html__('Situação da produção:', 'petshop-core') . '</strong> ' . esc_html($statusLabel) . '</p>';
        echo '</div>';
    }
}
