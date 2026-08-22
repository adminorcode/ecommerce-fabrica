<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Http;

use Petshop\Core\Personalization\Application\AttachToCart;
use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Infrastructure\Capabilities;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;
use Petshop\Core\Personalization\Infrastructure\PrivateStorage;

defined('ABSPATH') || exit;

/**
 * Streams private artifacts after authorization. No public URL ever points to
 * the real path on disk.
 */
final class DownloadController
{
    public const REST_NAMESPACE = 'petshop/v1';
    public const DOWNLOAD_NONCE = 'petshop_personalization_download';

    private const PUBLIC_ID_PATTERN = '[0-9a-f]{8}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{12}';

    public static function bootstrap(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/personalizations/(?P<public_id>' . self::PUBLIC_ID_PATTERN . ')/preview', [
            'methods' => \WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [self::class, 'servePreview'],
            'args' => [
                'order_key' => ['type' => 'string', 'required' => false],
            ],
        ]);

        register_rest_route(
            self::REST_NAMESPACE,
            '/personalizations/(?P<public_id>' . self::PUBLIC_ID_PATTERN . ')/files/(?P<type>original|production|preview)',
            [
                'methods' => \WP_REST_Server::READABLE,
                'permission_callback' => [self::class, 'canManage'],
                'callback' => [self::class, 'serveManagedFile'],
            ]
        );

        register_rest_route(self::REST_NAMESPACE, '/personalization-orders/(?P<order_id>\d+)/package', [
            'methods' => \WP_REST_Server::READABLE,
            'permission_callback' => [self::class, 'canManage'],
            'callback' => [self::class, 'serveOrderPackage'],
        ]);
    }

    public static function canManage(\WP_REST_Request $request): bool
    {
        $nonce = (string) $request->get_param('_petshop_nonce');

        return Capabilities::currentUserCanManage() && wp_verify_nonce($nonce, self::DOWNLOAD_NONCE) !== false;
    }

    public static function previewUrl(string $publicId, string $orderKey = ''): string
    {
        $url = self::withCookieNonce(rest_url(self::REST_NAMESPACE . '/personalizations/' . $publicId . '/preview'));

        return $orderKey !== '' ? add_query_arg('order_key', $orderKey, $url) : $url;
    }

    public static function managedFileUrl(string $publicId, string $type): string
    {
        return add_query_arg(
            '_petshop_nonce',
            wp_create_nonce(self::DOWNLOAD_NONCE),
            self::withCookieNonce(rest_url(self::REST_NAMESPACE . '/personalizations/' . $publicId . '/files/' . $type))
        );
    }

    public static function orderPackageUrl(int $orderId): string
    {
        return add_query_arg(
            '_petshop_nonce',
            wp_create_nonce(self::DOWNLOAD_NONCE),
            self::withCookieNonce(rest_url(self::REST_NAMESPACE . '/personalization-orders/' . $orderId . '/package'))
        );
    }

    /**
     * REST cookie authentication demotes the request to anonymous when no
     * `wp_rest` nonce travels with it, so every authenticated surface needs one.
     */
    private static function withCookieNonce(string $url): string
    {
        if (!is_user_logged_in()) {
            return $url;
        }

        return add_query_arg('_wpnonce', wp_create_nonce('wp_rest'), $url);
    }

    public static function servePreview(\WP_REST_Request $request): ?\WP_Error
    {
        $personalization = PersonalizationRepository::findByPublicId((string) $request->get_param('public_id'));
        if (!$personalization instanceof Personalization) {
            return new \WP_Error('petshop_personalization_not_found', __('Personalização não encontrada.', 'petshop-core'), ['status' => 404]);
        }

        if (!self::canViewPreview($personalization, (string) $request->get_param('order_key'))) {
            return new \WP_Error('petshop_personalization_forbidden', __('Acesso não autorizado.', 'petshop-core'), ['status' => 403]);
        }

        return self::streamFile($personalization, PersonalizationRepository::FILE_PREVIEW, false);
    }

    public static function serveManagedFile(\WP_REST_Request $request): ?\WP_Error
    {
        $personalization = PersonalizationRepository::findByPublicId((string) $request->get_param('public_id'));
        if (!$personalization instanceof Personalization) {
            return new \WP_Error('petshop_personalization_not_found', __('Personalização não encontrada.', 'petshop-core'), ['status' => 404]);
        }

        $type = (string) $request->get_param('type');

        return self::streamFile($personalization, $type, $type !== PersonalizationRepository::FILE_PREVIEW);
    }

    public static function serveOrderPackage(\WP_REST_Request $request): ?\WP_Error
    {
        $orderId = (int) $request->get_param('order_id');
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            return new \WP_Error('petshop_order_not_found', __('Pedido não encontrado.', 'petshop-core'), ['status' => 404]);
        }

        if (!class_exists('ZipArchive')) {
            return new \WP_Error('petshop_zip_unavailable', __('Extensão ZIP indisponível no servidor.', 'petshop-core'), ['status' => 501]);
        }

        $personalizations = PersonalizationRepository::findByOrder($orderId);
        if ($personalizations === []) {
            return new \WP_Error('petshop_personalization_empty', __('Pedido sem personalizações.', 'petshop-core'), ['status' => 404]);
        }

        $tmp = wp_tempnam('petshop-personalizations');
        if (!is_string($tmp) || $tmp === '') {
            return new \WP_Error('petshop_zip_failed', __('Não foi possível preparar o pacote.', 'petshop-core'), ['status' => 500]);
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);

            return new \WP_Error('petshop_zip_failed', __('Não foi possível preparar o pacote.', 'petshop-core'), ['status' => 500]);
        }

        foreach ($personalizations as $personalization) {
            foreach (PersonalizationRepository::files((int) $personalization->id) as $type => $file) {
                try {
                    $binary = PrivateStorage::readBinary((string) $file['relative_path']);
                } catch (\Throwable) {
                    continue;
                }
                $zip->addFromString(
                    sprintf('pedido-%d/%s/%s.%s', $orderId, $personalization->publicId, $type, (string) $file['extension']),
                    $binary
                );
            }
            $zip->addFromString(
                sprintf('pedido-%d/%s/resumo.txt', $orderId, $personalization->publicId),
                $personalization->textSummary . "\n" . $personalization->snapshotHash . "\n"
            );
        }
        $zip->close();

        $contents = file_get_contents($tmp);
        @unlink($tmp);
        if (!is_string($contents)) {
            return new \WP_Error('petshop_zip_failed', __('Não foi possível preparar o pacote.', 'petshop-core'), ['status' => 500]);
        }

        self::sendHeaders('application/zip', strlen($contents), sprintf('pedido-%d-personalizacoes.zip', $orderId), true);
        echo $contents;
        exit;
    }

    public static function canViewPreview(Personalization $personalization, string $orderKey = ''): bool
    {
        if (Capabilities::currentUserCanManage()) {
            return true;
        }

        if ($personalization->orderId !== null && $personalization->orderId > 0) {
            $order = wc_get_order($personalization->orderId);
            if (!$order instanceof \WC_Order) {
                return false;
            }

            $userId = get_current_user_id();
            if ($userId > 0 && (int) $order->get_customer_id() === $userId) {
                return true;
            }

            return $orderKey !== '' && hash_equals((string) $order->get_order_key(), $orderKey);
        }

        return AttachToCart::isOwnedByCurrentVisitor($personalization);
    }

    private static function streamFile(Personalization $personalization, string $type, bool $asAttachment): ?\WP_Error
    {
        $file = PersonalizationRepository::file((int) $personalization->id, $type);
        if ($file === null) {
            return new \WP_Error('petshop_file_not_found', __('Arquivo não disponível.', 'petshop-core'), ['status' => 404]);
        }

        try {
            $binary = PrivateStorage::readBinary((string) $file['relative_path']);
        } catch (\Throwable $error) {
            error_log('Petshop personalization download falhou: ' . $error->getMessage());

            return new \WP_Error('petshop_file_unreadable', __('Arquivo não disponível.', 'petshop-core'), ['status' => 404]);
        }

        self::sendHeaders(
            (string) $file['mime_type'],
            strlen($binary),
            sprintf('%s-%s.%s', $personalization->publicId, $type, (string) $file['extension']),
            $asAttachment
        );
        echo $binary;
        exit;
    }

    private static function sendHeaders(string $mime, int $length, string $filename, bool $asAttachment): void
    {
        if (headers_sent()) {
            return;
        }

        nocache_headers();
        status_header(200);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $length);
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');
        header(sprintf(
            'Content-Disposition: %s; filename="%s"',
            $asAttachment ? 'attachment' : 'inline',
            sanitize_file_name($filename)
        ));
    }
}
