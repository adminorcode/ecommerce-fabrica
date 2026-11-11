<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Admin;

use Petshop\Core\Personalization\Application\TransitionStatus;
use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Domain\PersonalizationStatus;
use Petshop\Core\Personalization\Http\DownloadController;
use Petshop\Core\Personalization\Infrastructure\Capabilities;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;

defined('ABSPATH') || exit;

/**
 * WooCommerce → Personalizações: queue, detail view and manual transitions.
 */
final class PersonalizationsPage
{
    public const SLUG = 'petshop-personalizations';
    public const TRANSITION_ACTION = 'petshop_personalization_transition';
    public const TRANSITION_NONCE = 'petshop_personalization_transition_nonce';

    public static function bootstrap(): void
    {
        add_action('admin_menu', [self::class, 'registerMenu'], 60);
        add_action('admin_post_' . self::TRANSITION_ACTION, [self::class, 'handleTransition']);
    }

    public static function registerMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Personalizações', 'petshop-core'),
            __('Personalizações', 'petshop-core'),
            Capabilities::MANAGE,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function detailUrl(string $publicId): string
    {
        return add_query_arg(
            ['page' => self::SLUG, 'view' => $publicId],
            admin_url('admin.php')
        );
    }

    public static function listUrl(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => self::SLUG], $args), admin_url('admin.php'));
    }

    public static function render(): void
    {
        if (!Capabilities::currentUserCanManage()) {
            wp_die(esc_html__('Você não tem permissão para acessar as personalizações.', 'petshop-core'), 403);
        }

        $view = isset($_GET['view']) ? sanitize_text_field((string) $_GET['view']) : '';
        if ($view !== '' && PersonalizationRepository::isPublicId($view)) {
            self::renderDetail($view);

            return;
        }

        self::renderQueue();
    }

    private static function renderQueue(): void
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

        $table = new PersonalizationsListTable();
        $table->prepare_items();

        echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__('Personalizações', 'petshop-core') . '</h1>';
        self::renderNotice();
        echo '<p>' . esc_html__('Fila de produção das artes personalizadas. Somente pedidos pagos entram automaticamente em “Para revisar”.', 'petshop-core') . '</p>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        if (isset($_GET['status'])) {
            echo '<input type="hidden" name="status" value="' . esc_attr(sanitize_key((string) $_GET['status'])) . '">';
        }
        $table->views();
        $table->search_box(__('Buscar arte', 'petshop-core'), 'petshop-personalization');
        $table->display();
        echo '</form></div>';
    }

    private static function renderDetail(string $publicId): void
    {
        $personalization = PersonalizationRepository::findByPublicId($publicId);
        if (!$personalization instanceof Personalization) {
            echo '<div class="wrap"><h1>' . esc_html__('Personalizações', 'petshop-core') . '</h1>';
            echo '<div class="notice notice-error"><p>' . esc_html__('Personalização não encontrada.', 'petshop-core') . '</p></div></div>';

            return;
        }

        $files = PersonalizationRepository::files((int) $personalization->id);
        $snapshot = json_decode($personalization->configSnapshot, true);
        $specification = is_array($snapshot) && isset($snapshot['specification']) && is_array($snapshot['specification'])
            ? $snapshot['specification']
            : [];

        echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__('Personalização', 'petshop-core') . '</h1>';
        echo ' <a class="page-title-action" href="' . esc_url(self::listUrl()) . '">' . esc_html__('Voltar à fila', 'petshop-core') . '</a>';
        self::renderNotice();

        echo '<div style="display:flex;gap:24px;flex-wrap:wrap;margin-top:16px">';

        echo '<div style="flex:0 0 320px">';
        if (isset($files[PersonalizationRepository::FILE_PREVIEW])) {
            echo '<img src="' . esc_url(DownloadController::previewUrl($personalization->publicId)) . '" alt="'
                . esc_attr__('Prévia da personalização', 'petshop-core')
                . '" style="max-width:100%;height:auto;border:1px solid #dcdcde;background:#fff">';
        }
        echo '<p><strong>' . esc_html__('Arquivos', 'petshop-core') . '</strong></p><ul>';
        foreach (PersonalizationRepository::FILE_TYPES as $type) {
            if (!isset($files[$type])) {
                continue;
            }
            $file = $files[$type];
            echo '<li><a href="' . esc_url(DownloadController::managedFileUrl($personalization->publicId, $type)) . '">'
                . esc_html(self::fileLabel($type)) . '</a> — '
                . esc_html(size_format((int) $file['byte_size']));
            if (!empty($file['width_px']) && !empty($file['height_px'])) {
                echo ' · ' . esc_html(sprintf('%d × %d px', (int) $file['width_px'], (int) $file['height_px']));
            }
            echo '</li>';
        }
        echo '</ul>';
        if ($personalization->orderId !== null && $personalization->orderId > 0) {
            echo '<p><a class="button" href="' . esc_url(DownloadController::orderPackageUrl($personalization->orderId)) . '">'
                . esc_html__('Baixar pacote do pedido (ZIP)', 'petshop-core') . '</a></p>';
        }
        echo '</div>';

        echo '<div style="flex:1 1 420px">';
        echo '<table class="widefat striped"><tbody>';
        self::detailRow(__('ID público', 'petshop-core'), $personalization->publicId);
        self::detailRow(__('Estado', 'petshop-core'), $personalization->status->label());
        self::detailRow(__('Resumo', 'petshop-core'), $personalization->textSummary);
        self::detailRow(__('Produto', 'petshop-core'), self::productLabel($personalization->productId));
        self::detailRow(__('Pedido', 'petshop-core'), self::orderLabel($personalization));
        self::detailRow(
            __('Área de impressão', 'petshop-core'),
            $specification === []
                ? '—'
                : sprintf(
                    '%s × %s mm · %s DPI · %d × %d px',
                    (string) ($specification['width_mm'] ?? '?'),
                    (string) ($specification['height_mm'] ?? '?'),
                    (string) ($specification['dpi'] ?? '?'),
                    (int) ($specification['width_px'] ?? 0),
                    (int) ($specification['height_px'] ?? 0)
                )
        );
        self::detailRow(__('Hash do snapshot', 'petshop-core'), $personalization->snapshotHash);
        self::detailRow(__('Criado em', 'petshop-core'), get_date_from_gmt($personalization->createdAt, 'd/m/Y H:i'));
        self::detailRow(__('Atualizado em', 'petshop-core'), get_date_from_gmt($personalization->updatedAt, 'd/m/Y H:i'));
        echo '</tbody></table>';

        self::renderTransitions($personalization);
        self::renderHistory($personalization);
        echo '</div></div></div>';
    }

    private static function renderTransitions(Personalization $personalization): void
    {
        $targets = $personalization->status->allowedTargets();
        echo '<h2>' . esc_html__('Mover estado', 'petshop-core') . '</h2>';
        if ($targets === []) {
            echo '<p>' . esc_html__('Estado final: nenhuma transição disponível.', 'petshop-core') . '</p>';

            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::TRANSITION_ACTION, self::TRANSITION_NONCE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::TRANSITION_ACTION) . '">';
        echo '<input type="hidden" name="public_id" value="' . esc_attr($personalization->publicId) . '">';
        echo '<p><label for="petshop-personalization-target">' . esc_html__('Novo estado', 'petshop-core') . '</label> ';
        echo '<select id="petshop-personalization-target" name="target">';
        foreach ($targets as $target) {
            echo '<option value="' . esc_attr($target->value) . '">' . esc_html($target->label()) . '</option>';
        }
        echo '</select> ';
        echo '<label for="petshop-personalization-note" class="screen-reader-text">' . esc_html__('Observação', 'petshop-core') . '</label>';
        echo '<input type="text" id="petshop-personalization-note" name="note" class="regular-text" placeholder="'
            . esc_attr__('Observação interna (opcional)', 'petshop-core') . '"> ';
        submit_button(__('Aplicar', 'petshop-core'), 'primary', 'submit', false);
        echo '</p></form>';
    }

    private static function renderHistory(Personalization $personalization): void
    {
        $history = PersonalizationRepository::history((int) $personalization->id);
        echo '<h2>' . esc_html__('Histórico', 'petshop-core') . '</h2>';
        if ($history === []) {
            echo '<p>' . esc_html__('Sem registros.', 'petshop-core') . '</p>';

            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Quando', 'petshop-core') . '</th>';
        echo '<th>' . esc_html__('De', 'petshop-core') . '</th>';
        echo '<th>' . esc_html__('Para', 'petshop-core') . '</th>';
        echo '<th>' . esc_html__('Responsável', 'petshop-core') . '</th>';
        echo '<th>' . esc_html__('Observação', 'petshop-core') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($history as $entry) {
            $actor = __('Sistema', 'petshop-core');
            if ($entry['actor_user_id'] !== null) {
                $user = get_userdata($entry['actor_user_id']);
                $actor = $user instanceof \WP_User ? $user->display_name : (string) $entry['actor_user_id'];
            }
            echo '<tr>';
            echo '<td>' . esc_html(get_date_from_gmt($entry['created_at'], 'd/m/Y H:i')) . '</td>';
            echo '<td>' . esc_html(self::statusLabel($entry['from_status'])) . '</td>';
            echo '<td>' . esc_html(self::statusLabel($entry['to_status'])) . '</td>';
            echo '<td>' . esc_html($actor) . '</td>';
            echo '<td>' . esc_html($entry['note']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public static function handleTransition(): void
    {
        if (!Capabilities::currentUserCanManage()) {
            wp_die(esc_html__('Você não tem permissão para alterar personalizações.', 'petshop-core'), 403);
        }

        check_admin_referer(self::TRANSITION_ACTION, self::TRANSITION_NONCE);

        $publicId = isset($_POST['public_id']) ? sanitize_text_field(wp_unslash((string) $_POST['public_id'])) : '';
        $target = isset($_POST['target']) ? sanitize_key((string) $_POST['target']) : '';
        $note = isset($_POST['note']) ? sanitize_text_field(wp_unslash((string) $_POST['note'])) : '';

        $personalization = PersonalizationRepository::findByPublicId($publicId);
        $targetStatus = PersonalizationStatus::tryFrom($target);

        if (!$personalization instanceof Personalization || !$targetStatus instanceof PersonalizationStatus) {
            wp_safe_redirect(add_query_arg('petshop_message', 'invalid', self::listUrl()));
            exit;
        }

        try {
            TransitionStatus::apply($personalization, $targetStatus, get_current_user_id(), $note);
            $message = 'updated';
        } catch (\Throwable $error) {
            error_log('Petshop personalization transition recusada: ' . $error->getMessage());
            $message = 'invalid_transition';
        }

        wp_safe_redirect(add_query_arg('petshop_message', $message, self::detailUrl($personalization->publicId)));
        exit;
    }

    private static function renderNotice(): void
    {
        $message = isset($_GET['petshop_message']) ? sanitize_key((string) $_GET['petshop_message']) : '';
        if ($message === '') {
            return;
        }

        $notices = [
            'updated' => ['notice-success', __('Estado atualizado.', 'petshop-core')],
            'invalid' => ['notice-error', __('Personalização ou estado inválido.', 'petshop-core')],
            'invalid_transition' => ['notice-error', __('Transição não permitida para o estado atual.', 'petshop-core')],
        ];

        if (!isset($notices[$message])) {
            return;
        }

        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr($notices[$message][0]),
            esc_html($notices[$message][1])
        );
    }

    private static function detailRow(string $label, string $value): void
    {
        echo '<tr><th scope="row" style="width:200px">' . esc_html($label) . '</th><td>' . wp_kses_post($value) . '</td></tr>';
    }

    private static function productLabel(int $productId): string
    {
        $product = wc_get_product($productId);
        if (!$product instanceof \WC_Product) {
            return sprintf('#%d', $productId);
        }

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url((string) get_edit_post_link($productId)),
            esc_html($product->get_name())
        );
    }

    private static function orderLabel(Personalization $personalization): string
    {
        if ($personalization->orderId === null || $personalization->orderId <= 0) {
            return esc_html__('Sem pedido (rascunho ou carrinho)', 'petshop-core');
        }

        $order = wc_get_order($personalization->orderId);
        if (!$order instanceof \WC_Order) {
            return sprintf('#%d', $personalization->orderId);
        }

        return sprintf(
            '<a href="%s">#%s</a> — %s',
            esc_url($order->get_edit_order_url()),
            esc_html((string) $order->get_order_number()),
            esc_html(wc_get_order_status_name($order->get_status()))
        );
    }

    private static function statusLabel(?string $status): string
    {
        if ($status === null) {
            return '—';
        }

        $case = PersonalizationStatus::tryFrom($status);

        return $case instanceof PersonalizationStatus ? $case->label() : $status;
    }

    private static function fileLabel(string $type): string
    {
        return match ($type) {
            PersonalizationRepository::FILE_ORIGINAL => __('Imagem original do cliente', 'petshop-core'),
            PersonalizationRepository::FILE_PREVIEW => __('Prévia', 'petshop-core'),
            PersonalizationRepository::FILE_PRODUCTION => __('PNG de produção', 'petshop-core'),
            default => $type,
        };
    }
}
