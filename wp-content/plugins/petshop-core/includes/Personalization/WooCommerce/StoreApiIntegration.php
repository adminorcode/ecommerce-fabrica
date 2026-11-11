<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\WooCommerce;

use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Http\DownloadController;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;

defined('ABSPATH') || exit;

/**
 * Extends the Store API cart item schema so Cart and Checkout Blocks keep the
 * personalization across reloads.
 */
final class StoreApiIntegration
{
    public const EXTENSION_NAMESPACE = 'petshop-personalization';

    public static function bootstrap(): void
    {
        add_action('woocommerce_blocks_loaded', [self::class, 'registerEndpointData']);
    }

    public static function registerEndpointData(): void
    {
        if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }

        if (!class_exists(\Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::class)) {
            return;
        }

        woocommerce_store_api_register_endpoint_data([
            'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
            'namespace' => self::EXTENSION_NAMESPACE,
            'data_callback' => [self::class, 'itemData'],
            'schema_callback' => [self::class, 'itemSchema'],
            'schema_type' => ARRAY_A,
        ]);
    }

    /**
     * @param array<string, mixed> $cartItem
     * @return array{public_id: string, summary: string, hash: string, preview_url: string, status: string, status_label: string}
     */
    public static function itemData(array $cartItem): array
    {
        $empty = [
            'public_id' => '',
            'summary' => '',
            'hash' => '',
            'preview_url' => '',
            'status' => '',
            'status_label' => '',
        ];

        $meta = CartIntegration::cartItemMeta($cartItem);
        if ($meta === null) {
            return $empty;
        }

        $personalization = PersonalizationRepository::findByPublicId($meta['public_id']);
        if (!$personalization instanceof Personalization) {
            return $empty;
        }

        return [
            'public_id' => $personalization->publicId,
            'summary' => $personalization->textSummary,
            'hash' => $personalization->snapshotHash,
            'preview_url' => DownloadController::previewUrl($personalization->publicId),
            'status' => $personalization->status->value,
            'status_label' => $personalization->status->label(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function itemSchema(): array
    {
        return [
            'public_id' => [
                'description' => __('Identificador público da personalização.', 'petshop-core'),
                'type' => 'string',
                'context' => ['view', 'edit'],
                'readonly' => true,
            ],
            'summary' => [
                'description' => __('Resumo textual imutável da arte.', 'petshop-core'),
                'type' => 'string',
                'context' => ['view', 'edit'],
                'readonly' => true,
            ],
            'hash' => [
                'description' => __('Hash SHA-256 do snapshot.', 'petshop-core'),
                'type' => 'string',
                'context' => ['view', 'edit'],
                'readonly' => true,
            ],
            'preview_url' => [
                'description' => __('URL autorizada da prévia.', 'petshop-core'),
                'type' => 'string',
                'context' => ['view', 'edit'],
                'readonly' => true,
            ],
            'status' => [
                'description' => __('Estado técnico da personalização.', 'petshop-core'),
                'type' => 'string',
                'context' => ['view', 'edit'],
                'readonly' => true,
            ],
            'status_label' => [
                'description' => __('Estado funcional exibido ao comprador.', 'petshop-core'),
                'type' => 'string',
                'context' => ['view', 'edit'],
                'readonly' => true,
            ],
        ];
    }
}
