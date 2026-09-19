<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

defined('ABSPATH') || exit;

final class AccountPrivacy
{
    private const META_FIELDS = [
        'petshop_person_type',
        'petshop_document',
        'billing_number',
        'billing_neighborhood',
        'billing_cpf',
        'billing_cnpj',
    ];

    public static function bootstrap(): void
    {
        add_filter(
            'woocommerce_privacy_export_customer_personal_data',
            [self::class, 'exportCustomerData'],
            10,
            2
        );

        add_filter(
            'woocommerce_privacy_erase_personal_data_customer',
            [self::class, 'eraseCustomerData'],
            10,
            2
        );
    }

    public static function exportCustomerData(array $personalData, \WC_Customer $customer): array
    {
        $customerId = $customer->get_id();

        if ($customerId <= 0) {
            return $personalData;
        }

        $fields = [
            'petshop_person_type' => __('Tipo de pessoa', 'petshop-core'),
            'petshop_document' => __('CPF ou CNPJ', 'petshop-core'),
            'billing_number' => __('Número do endereço de cobrança', 'petshop-core'),
            'billing_neighborhood' => __('Bairro do endereço de cobrança', 'petshop-core'),
        'billing_cpf' => __('CPF de cobrança', 'petshop-core'),
        'billing_cnpj' => __('CNPJ de cobrança', 'petshop-core'),
        ];

        foreach ($fields as $metaKey => $label) {
            $value = get_user_meta($customerId, $metaKey, true);

            if ($value === '') {
                continue;
            }

            $personalData[] = [
                'name' => $label,
                'value' => $value,
            ];
        }

        return $personalData;
    }

    public static function eraseCustomerData(array $response, \WC_Customer $customer): array
    {
        $customerId = $customer->get_id();

        if ($customerId <= 0) {
            return $response;
        }

        $removed = false;

        foreach (self::META_FIELDS as $metaKey) {
            if (!metadata_exists('user', $customerId, $metaKey)) {
                continue;
            }

            if (delete_user_meta($customerId, $metaKey)) {
                $removed = true;
            }
        }

        if ($removed) {
            $response['items_removed'] = true;
            $response['messages'][] = __(
                'Dados adicionais de cadastro do cliente foram removidos.',
                'petshop-core'
            );
        }

        return $response;
    }
}
