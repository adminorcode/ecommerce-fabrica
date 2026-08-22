<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Infrastructure;

use Petshop\Core\Personalization\Domain\Personalization;

defined('ABSPATH') || exit;

/**
 * WordPress personal data exporter/eraser for personalizations.
 */
final class Privacy
{
    private const GROUP = 'petshop-personalizations';

    public static function bootstrap(): void
    {
        add_filter('wp_privacy_personal_data_exporters', [self::class, 'registerExporter']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'registerEraser']);
    }

    /**
     * @param array<string, array<string, mixed>> $exporters
     * @return array<string, array<string, mixed>>
     */
    public static function registerExporter(array $exporters): array
    {
        $exporters[self::GROUP] = [
            'exporter_friendly_name' => __('Personalizações do Petshop', 'petshop-core'),
            'callback' => [self::class, 'export'],
        ];

        return $exporters;
    }

    /**
     * @param array<string, array<string, mixed>> $erasers
     * @return array<string, array<string, mixed>>
     */
    public static function registerEraser(array $erasers): array
    {
        $erasers[self::GROUP] = [
            'eraser_friendly_name' => __('Personalizações do Petshop', 'petshop-core'),
            'callback' => [self::class, 'erase'],
        ];

        return $erasers;
    }

    /**
     * @return array{data: list<array<string, mixed>>, done: bool}
     */
    public static function export(string $email, int $page = 1): array
    {
        $data = [];
        foreach (self::personalizationsFor($email) as $personalization) {
            $product = wc_get_product($personalization->productId);
            $data[] = [
                'group_id' => self::GROUP,
                'group_label' => __('Personalizações', 'petshop-core'),
                'item_id' => 'personalization-' . $personalization->publicId,
                'data' => [
                    ['name' => __('Identificador', 'petshop-core'), 'value' => $personalization->publicId],
                    ['name' => __('Produto', 'petshop-core'), 'value' => $product instanceof \WC_Product ? $product->get_name() : (string) $personalization->productId],
                    ['name' => __('Resumo da arte', 'petshop-core'), 'value' => $personalization->textSummary],
                    ['name' => __('Estado', 'petshop-core'), 'value' => $personalization->status->label()],
                    ['name' => __('Pedido', 'petshop-core'), 'value' => $personalization->orderId !== null ? (string) $personalization->orderId : ''],
                    ['name' => __('Criado em', 'petshop-core'), 'value' => $personalization->createdAt],
                ],
            ];
        }

        unset($page);

        return ['data' => $data, 'done' => true];
    }

    /**
     * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
     */
    public static function erase(string $email, int $page = 1): array
    {
        unset($page);

        $removed = false;
        $retained = false;
        $messages = [];

        foreach (self::personalizationsFor($email) as $personalization) {
            if ($personalization->orderId === null || $personalization->orderId <= 0) {
                PersonalizationRepository::purge($personalization);
                $removed = true;
                continue;
            }

            $original = PersonalizationRepository::file((int) $personalization->id, PersonalizationRepository::FILE_ORIGINAL);
            if ($original !== null) {
                try {
                    PrivateStorage::delete((string) $original['relative_path']);
                    $removed = true;
                } catch (\Throwable $error) {
                    error_log('Petshop personalization erase falhou: ' . $error->getMessage());
                }
            }

            $retained = true;
        }

        if ($retained) {
            $messages[] = __('Personalizações vinculadas a pedidos foram preservadas conforme a política fiscal e de retenção da loja.', 'petshop-core');
        }

        return [
            'items_removed' => $removed,
            'items_retained' => $retained,
            'messages' => $messages,
            'done' => true,
        ];
    }

    /**
     * @return list<Personalization>
     */
    private static function personalizationsFor(string $email): array
    {
        $items = [];
        $user = get_user_by('email', $email);
        if ($user instanceof \WP_User) {
            $items = PersonalizationRepository::findByUser((int) $user->ID);
        }

        $orders = wc_get_orders([
            'billing_email' => $email,
            'limit' => 100,
            'return' => 'ids',
        ]);

        foreach (is_array($orders) ? $orders : [] as $orderId) {
            foreach (PersonalizationRepository::findByOrder((int) $orderId) as $personalization) {
                $items[] = $personalization;
            }
        }

        $unique = [];
        foreach ($items as $item) {
            $unique[$item->publicId] = $item;
        }

        return array_values($unique);
    }
}
