<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Admin;

use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Domain\PersonalizationStatus;
use Petshop\Core\Personalization\Http\DownloadController;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;

defined('ABSPATH') || exit;

/**
 * Production queue table for WooCommerce → Personalizações.
 */
final class PersonalizationsListTable extends \WP_List_Table
{
    private const PER_PAGE = 20;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'personalization',
            'plural' => 'personalizations',
            'ajax' => false,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function get_columns(): array
    {
        return [
            'preview' => __('Prévia', 'petshop-core'),
            'summary' => __('Arte', 'petshop-core'),
            'product' => __('Produto', 'petshop-core'),
            'order' => __('Pedido', 'petshop-core'),
            'customer' => __('Cliente', 'petshop-core'),
            'status' => __('Estado', 'petshop-core'),
            'updated' => __('Atualizado', 'petshop-core'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function get_views(): array
    {
        $counts = PersonalizationRepository::countByStatus();
        $current = isset($_GET['status']) ? sanitize_key((string) $_GET['status']) : '';
        $base = admin_url('admin.php?page=' . PersonalizationsPage::SLUG);

        $views = [
            'all' => sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url($base),
                $current === '' ? ' class="current"' : '',
                esc_html__('Todas', 'petshop-core'),
                array_sum($counts)
            ),
            'queue' => sprintf(
                '<a href="%s"%s>%s</a>',
                esc_url(add_query_arg('status', 'queue', $base)),
                $current === 'queue' ? ' class="current"' : '',
                esc_html__('Fila ativa', 'petshop-core')
            ),
        ];

        foreach (PersonalizationStatus::cases() as $status) {
            $views[$status->value] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', $status->value, $base)),
                $current === $status->value ? ' class="current"' : '',
                esc_html($status->label()),
                (int) ($counts[$status->value] ?? 0)
            );
        }

        return $views;
    }

    public function prepare_items(): void
    {
        $this->_column_headers = [$this->get_columns(), [], []];

        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $result = PersonalizationRepository::search([
            'status' => isset($_GET['status']) ? sanitize_key((string) $_GET['status']) : '',
            'order_id' => isset($_GET['order_id']) ? absint($_GET['order_id']) : 0,
            'product_id' => isset($_GET['product_id']) ? absint($_GET['product_id']) : 0,
            'search' => isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '',
            'per_page' => self::PER_PAGE,
            'paged' => $paged,
        ]);

        $this->items = $result['items'];
        $this->set_pagination_args([
            'total_items' => $result['total'],
            'per_page' => self::PER_PAGE,
            'total_pages' => (int) ceil($result['total'] / self::PER_PAGE),
        ]);
    }

    public function no_items(): void
    {
        esc_html_e('Nenhuma personalização encontrada com os filtros atuais.', 'petshop-core');
    }

    /**
     * @param Personalization $item
     */
    public function column_default($item, $column_name): string
    {
        return match ($column_name) {
            'preview' => $this->previewCell($item),
            'summary' => $this->summaryCell($item),
            'product' => $this->productCell($item),
            'order' => $this->orderCell($item),
            'customer' => $this->customerCell($item),
            'status' => esc_html($item->status->label()),
            'updated' => esc_html(get_date_from_gmt($item->updatedAt, 'd/m/Y H:i')),
            default => '',
        };
    }

    private function previewCell(Personalization $item): string
    {
        if (PersonalizationRepository::file((int) $item->id, PersonalizationRepository::FILE_PREVIEW) === null) {
            return '<span aria-hidden="true">—</span>';
        }

        return sprintf(
            '<img src="%s" alt="%s" width="64" height="64" style="width:64px;height:64px;object-fit:contain;background:#f6f7f7">',
            esc_url(DownloadController::previewUrl($item->publicId)),
            esc_attr__('Prévia da personalização', 'petshop-core')
        );
    }

    private function summaryCell(Personalization $item): string
    {
        $detailUrl = PersonalizationsPage::detailUrl($item->publicId);

        return sprintf(
            '<strong><a href="%s">%s</a></strong><div class="row-actions"><span><a href="%s">%s</a></span></div><code style="font-size:11px">%s</code>',
            esc_url($detailUrl),
            esc_html($item->textSummary !== '' ? $item->textSummary : __('(sem resumo)', 'petshop-core')),
            esc_url($detailUrl),
            esc_html__('Abrir detalhe', 'petshop-core'),
            esc_html($item->publicId)
        );
    }

    private function productCell(Personalization $item): string
    {
        $product = wc_get_product($item->productId);
        if (!$product instanceof \WC_Product) {
            return esc_html(sprintf('#%d', $item->productId));
        }

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url((string) get_edit_post_link($item->productId)),
            esc_html($product->get_name())
        );
    }

    private function orderCell(Personalization $item): string
    {
        if ($item->orderId === null || $item->orderId <= 0) {
            return '<span aria-hidden="true">—</span>';
        }

        $order = wc_get_order($item->orderId);
        if (!$order instanceof \WC_Order) {
            return esc_html(sprintf('#%d', $item->orderId));
        }

        return sprintf(
            '<a href="%s">#%s</a><br><small>%s</small>',
            esc_url($order->get_edit_order_url()),
            esc_html((string) $order->get_order_number()),
            esc_html(wc_get_order_status_name($order->get_status()))
        );
    }

    private function customerCell(Personalization $item): string
    {
        if ($item->orderId !== null && $item->orderId > 0) {
            $order = wc_get_order($item->orderId);
            if ($order instanceof \WC_Order) {
                $name = trim($order->get_formatted_billing_full_name());
                if ($name !== '') {
                    return esc_html($name);
                }
            }
        }

        if ($item->userId !== null && $item->userId > 0) {
            $user = get_userdata($item->userId);
            if ($user instanceof \WP_User) {
                return esc_html($user->display_name);
            }
        }

        return esc_html__('Visitante', 'petshop-core');
    }
}
