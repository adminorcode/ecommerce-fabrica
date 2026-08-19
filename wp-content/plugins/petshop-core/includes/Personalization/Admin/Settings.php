<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Admin;

use Petshop\Core\Personalization\Infrastructure\PrivateStorage;
use Petshop\Core\Personalization\Infrastructure\RetentionPolicy;

defined('ABSPATH') || exit;

/**
 * Retention and upload limits under WooCommerce → Configurações → Produtos.
 */
final class Settings
{
    public const SECTION = 'petshop_personalization';

    public static function bootstrap(): void
    {
        add_filter('woocommerce_get_sections_products', [self::class, 'registerSection']);
        add_filter('woocommerce_get_settings_products', [self::class, 'registerSettings'], 10, 2);
    }

    /**
     * @param array<string, string> $sections
     * @return array<string, string>
     */
    public static function registerSection(array $sections): array
    {
        $sections[self::SECTION] = __('Personalizações', 'petshop-core');

        return $sections;
    }

    /**
     * @param array<int, array<string, mixed>> $settings
     * @return array<int, array<string, mixed>>
     */
    public static function registerSettings(array $settings, string $currentSection): array
    {
        if ($currentSection !== self::SECTION) {
            return $settings;
        }

        $health = PrivateStorage::health();

        return [
            [
                'title' => __('Personalizações', 'petshop-core'),
                'type' => 'title',
                'desc' => sprintf(
                    /* translators: 1: storage status, 2: storage message */
                    __('Storage privado: <strong>%1$s</strong> — %2$s', 'petshop-core'),
                    esc_html($health['status']),
                    esc_html($health['message'])
                ),
                'id' => self::SECTION . '_options',
            ],
            [
                'title' => __('Retenção de rascunhos (dias)', 'petshop-core'),
                'desc' => __('Rascunhos sem uso são apagados pelo cron após este prazo.', 'petshop-core'),
                'id' => RetentionPolicy::OPTION_DRAFT_DAYS,
                'type' => 'number',
                'default' => (string) RetentionPolicy::DEFAULT_DRAFT_DAYS,
                'custom_attributes' => ['min' => '1', 'max' => '365', 'step' => '1'],
                'desc_tip' => true,
            ],
            [
                'title' => __('Retenção de carrinhos abandonados (dias)', 'petshop-core'),
                'desc' => __('Itens de carrinho parados são cancelados após este prazo; os arquivos são preservados.', 'petshop-core'),
                'id' => RetentionPolicy::OPTION_CART_DAYS,
                'type' => 'number',
                'default' => (string) RetentionPolicy::DEFAULT_CART_DAYS,
                'custom_attributes' => ['min' => '1', 'max' => '365', 'step' => '1'],
                'desc_tip' => true,
            ],
            [
                'title' => __('Retenção de pedidos cancelados (dias)', 'petshop-core'),
                'desc' => __('Prazo documentado para arquivos de pedidos cancelados ou reembolsados.', 'petshop-core'),
                'id' => RetentionPolicy::OPTION_CANCELLED_DAYS,
                'type' => 'number',
                'default' => (string) RetentionPolicy::DEFAULT_CANCELLED_DAYS,
                'custom_attributes' => ['min' => '1', 'max' => '3650', 'step' => '1'],
                'desc_tip' => true,
            ],
            [
                'title' => __('Retenção de pedidos concluídos (dias)', 'petshop-core'),
                'desc' => __('Prazo de guarda dos originais e PNG de produção após a conclusão.', 'petshop-core'),
                'id' => RetentionPolicy::OPTION_COMPLETED_DAYS,
                'type' => 'number',
                'default' => (string) RetentionPolicy::DEFAULT_COMPLETED_DAYS,
                'custom_attributes' => ['min' => '1', 'max' => '3650', 'step' => '1'],
                'desc_tip' => true,
            ],
            [
                'title' => __('Tamanho máximo de upload (MB)', 'petshop-core'),
                'id' => RetentionPolicy::OPTION_MAX_UPLOAD_MB,
                'type' => 'number',
                'default' => (string) RetentionPolicy::DEFAULT_MAX_UPLOAD_MB,
                'custom_attributes' => ['min' => '1', 'max' => '32', 'step' => '1'],
            ],
            [
                'title' => __('Máximo de megapixels por imagem', 'petshop-core'),
                'id' => RetentionPolicy::OPTION_MAX_MEGAPIXELS,
                'type' => 'number',
                'default' => (string) RetentionPolicy::DEFAULT_MAX_MEGAPIXELS,
                'custom_attributes' => ['min' => '4', 'max' => '200', 'step' => '1'],
            ],
            [
                'type' => 'sectionend',
                'id' => self::SECTION . '_options',
            ],
        ];
    }
}
