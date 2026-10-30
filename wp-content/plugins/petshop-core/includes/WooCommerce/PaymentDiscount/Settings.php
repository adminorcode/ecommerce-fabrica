<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce\PaymentDiscount;

defined('ABSPATH') || exit;

final class Settings
{
    public const OPTION_GATEWAYS = 'petshop_payment_discount_gateways';
    public const OPTION_LABEL = 'petshop_payment_discount_label';
    public const DEFAULT_GATEWAY = 'woo-mercado-pago-pix';
    public const DEFAULT_LABEL = 'no Pix';
    public const SECTION = 'petshop_payment_discount';

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'provision']);
        add_filter('woocommerce_settings_tabs_array', [self::class, 'registerTab'], 30);
        add_action('woocommerce_settings_tabs_' . self::SECTION, [self::class, 'renderTab']);
        add_action('woocommerce_update_options_' . self::SECTION, [self::class, 'saveTab']);
        add_filter('woocommerce_admin_settings_sanitize_option_' . self::OPTION_GATEWAYS, [self::class, 'sanitizeGateways']);
        add_filter('woocommerce_admin_settings_sanitize_option_' . self::OPTION_LABEL, [self::class, 'sanitizeLabel']);
    }

    public static function provision(): void
    {
        add_option(self::OPTION_GATEWAYS, [self::DEFAULT_GATEWAY], '', false);
        add_option(self::OPTION_LABEL, self::DEFAULT_LABEL, '', false);
    }

    /**
     * @return list<string>
     */
    public static function gateways(): array
    {
        $stored = get_option(self::OPTION_GATEWAYS, null);
        if ($stored === null || $stored === false) {
            return [self::DEFAULT_GATEWAY];
        }
        if (!is_array($stored)) {
            return [];
        }

        $clean = [];
        foreach ($stored as $id) {
            if (!is_scalar($id)) {
                continue;
            }
            $key = sanitize_key((string) $id);
            if ($key !== '') {
                $clean[] = $key;
            }
        }

        return array_values(array_unique($clean));
    }

    public static function label(): string
    {
        $stored = get_option(self::OPTION_LABEL, null);
        if (!is_string($stored)) {
            return self::DEFAULT_LABEL;
        }

        return self::sanitizeLabel($stored);
    }

    public static function hasGateways(): bool
    {
        return self::gateways() !== [];
    }

    public static function allows(string $method): bool
    {
        $method = sanitize_key($method);

        return $method !== '' && in_array($method, self::gateways(), true);
    }

    public static function sanitizeLabel(mixed $value): string
    {
        $text = is_scalar($value) ? sanitize_text_field(wp_unslash((string) $value)) : '';

        return $text === '' ? self::DEFAULT_LABEL : $text;
    }

    /**
     * @return list<string>
     */
    public static function sanitizeGateways(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $enabled = array_keys(self::enabledGatewayChoices());
        if ($enabled === []) {
            return [];
        }

        $clean = [];
        foreach ($value as $id) {
            if (!is_scalar($id)) {
                continue;
            }
            $key = sanitize_key((string) $id);
            if ($key === '' || !in_array($key, $enabled, true)) {
                continue;
            }
            $clean[] = $key;
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param array<string, string> $tabs
     * @return array<string, string>
     */
    public static function registerTab(array $tabs): array
    {
        $label = __('Desconto por meio', 'petshop-core');
        $updated = [];
        foreach ($tabs as $key => $title) {
            $updated[$key] = $title;
            if ($key === 'checkout') {
                $updated[self::SECTION] = $label;
            }
        }
        if (!isset($updated[self::SECTION])) {
            $updated[self::SECTION] = $label;
        }

        return $updated;
    }

    public static function renderTab(): void
    {
        woocommerce_admin_fields(self::registerSettings());
    }

    public static function saveTab(): void
    {
        woocommerce_update_options(self::registerSettings());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function registerSettings(): array
    {
        $choices = self::enabledGatewayChoices();
        $pixReady = isset($choices[self::DEFAULT_GATEWAY]);

        return [
            [
                'title' => __('Desconto por meio', 'petshop-core'),
                'type' => 'title',
                'desc' => $pixReady
                    ? __('A porcentagem de cada produto vale para qualquer meio marcado aqui. O valor inicial é o Pix do Mercado Pago.', 'petshop-core')
                    : __('Habilite o gateway Pix do Mercado Pago (woo-mercado-pago-pix) para o comprador escolher esse meio no checkout. Cartão no Checkout Pro não herda o desconto.', 'petshop-core'),
                'id' => self::SECTION . '_options',
            ],
            [
                'title' => __('Meios que aplicam o desconto', 'petshop-core'),
                'id' => self::OPTION_GATEWAYS,
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'options' => $choices,
                'default' => [self::DEFAULT_GATEWAY],
                'desc' => __('Lista vazia tira o segundo preço da vitrine e o desconto do checkout.', 'petshop-core'),
                'desc_tip' => true,
            ],
            [
                'title' => __('Rótulo do preço na vitrine', 'petshop-core'),
                'id' => self::OPTION_LABEL,
                'type' => 'text',
                'default' => self::DEFAULT_LABEL,
                'desc' => __('Vazio volta para “no Pix”.', 'petshop-core'),
                'desc_tip' => true,
            ],
            [
                'type' => 'sectionend',
                'id' => self::SECTION . '_options',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function enabledGatewayChoices(): array
    {
        if (!function_exists('WC') || !WC()->payment_gateways()) {
            return [];
        }

        $choices = [];
        foreach (WC()->payment_gateways()->payment_gateways() as $gateway) {
            if (!is_object($gateway) || !isset($gateway->id, $gateway->enabled)) {
                continue;
            }
            if ($gateway->enabled !== 'yes') {
                continue;
            }
            $title = isset($gateway->title) && is_string($gateway->title) && $gateway->title !== ''
                ? $gateway->title
                : (string) $gateway->id;
            $choices[(string) $gateway->id] = $title;
        }

        return $choices;
    }
}
