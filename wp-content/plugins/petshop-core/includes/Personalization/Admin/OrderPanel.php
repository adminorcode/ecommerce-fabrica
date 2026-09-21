<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Admin;

use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Http\DownloadController;
use Petshop\Core\Personalization\Infrastructure\Capabilities;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;

defined('ABSPATH') || exit;

/**
 * "Personalizações" card inside the HPOS order screen.
 */
final class OrderPanel
{
    public static function bootstrap(): void
    {
        add_action('add_meta_boxes', [self::class, 'registerMetaBox'], 40, 2);
    }

    public static function registerMetaBox(string $screenId, mixed $context): void
    {
        unset($context);

        if (!Capabilities::currentUserCanManage() || !in_array($screenId, self::orderScreenIds(), true)) {
            return;
        }

        add_meta_box(
            'petshop-personalizations',
            __('Personalizações', 'petshop-core'),
            [self::class, 'render'],
            $screenId,
            'normal',
            'default'
        );
    }

    public static function render(mixed $postOrOrder): void
    {
        $order = $postOrOrder instanceof \WP_Post ? wc_get_order($postOrOrder->ID) : $postOrOrder;
        if (!$order instanceof \WC_Order) {
            return;
        }

        $personalizations = PersonalizationRepository::findByOrder($order->get_id());
        if ($personalizations === []) {
            echo '<p>' . esc_html__('Nenhuma personalização vinculada a este pedido.', 'petshop-core') . '</p>';

            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th style="width:80px">' . esc_html__('Prévia', 'petshop-core') . '</th>';
        echo '<th>' . esc_html__('Arte', 'petshop-core') . '</th>';
        echo '<th>' . esc_html__('Estado', 'petshop-core') . '</th>';
        echo '<th>' . esc_html__('Ações', 'petshop-core') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($personalizations as $personalization) {
            echo '<tr>';
            echo '<td>' . self::previewCell($personalization) . '</td>';
            echo '<td><strong>' . esc_html($personalization->textSummary) . '</strong><br><code style="font-size:11px">'
                . esc_html($personalization->publicId) . '</code></td>';
            echo '<td>' . esc_html($personalization->status->label()) . '</td>';
            echo '<td><a href="' . esc_url(PersonalizationsPage::detailUrl($personalization->publicId)) . '">'
                . esc_html__('Abrir na fila', 'petshop-core') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p><a class="button" href="' . esc_url(DownloadController::orderPackageUrl($order->get_id())) . '">'
            . esc_html__('Baixar pacote do pedido (ZIP)', 'petshop-core') . '</a></p>';
    }

    private static function previewCell(Personalization $personalization): string
    {
        if (PersonalizationRepository::file((int) $personalization->id, PersonalizationRepository::FILE_PREVIEW) === null) {
            return '<span aria-hidden="true">—</span>';
        }

        return sprintf(
            '<img src="%s" alt="%s" style="width:64px;height:64px;object-fit:contain;background:#f6f7f7">',
            esc_url(DownloadController::previewUrl($personalization->publicId)),
            esc_attr__('Prévia da personalização', 'petshop-core')
        );
    }

    /**
     * @return list<string>
     */
    private static function orderScreenIds(): array
    {
        $screens = ['shop_order', 'woocommerce_page_wc-orders'];
        if (function_exists('wc_get_page_screen_id')) {
            $screens[] = (string) wc_get_page_screen_id('shop-order');
        }

        return array_values(array_unique(array_filter($screens)));
    }
}
