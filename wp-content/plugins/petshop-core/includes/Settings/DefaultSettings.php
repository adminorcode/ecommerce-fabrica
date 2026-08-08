<?php

declare(strict_types=1);

namespace Petshop\Core\Settings;

defined('ABSPATH') || exit;

final class DefaultSettings
{
    /** @return array<string, array{label: string, default: mixed, type: string, sanitize: callable-string}> */
    public static function definitions(): array
    {
        return [
            'petshop_benefit_text' => [
                'label' => __('Mensagem da barra superior', 'petshop-core'),
                'default' => 'Acabamento cuidadoso para tutores e profissionais',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_benefit_url' => [
                'label' => __('Link da barra superior', 'petshop-core'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
            ],
            'petshop_support_label' => [
                'label' => __('Rótulo do atendimento no cabeçalho', 'petshop-core'),
                'default' => 'Atendimento',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_support_page' => [
                'label' => __('Página de atendimento do cabeçalho', 'petshop-core'),
                'default' => 0,
                'type' => 'dropdown-pages',
                'sanitize' => 'absint',
            ],
            'petshop_account_label' => [
                'label' => __('Rótulo da conta no cabeçalho', 'petshop-core'),
                'default' => 'Minha conta',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_wishlist_label' => [
                'label' => __('Rótulo da lista de desejos no cabeçalho', 'petshop-core'),
                'default' => 'Lista de desejos',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_wishlist_page' => [
                'label' => __('Página da lista de desejos', 'petshop-core'),
                'default' => 0,
                'type' => 'dropdown-pages',
                'sanitize' => 'absint',
            ],
            'petshop_featured_section_title' => [
                'label' => __('Título da seção de destaques (sem vendas reais)', 'petshop-core'),
                'default' => 'Destaques da loja',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_product_assurance_title' => [
                'label' => __('Título do aviso de produto', 'petshop-core'),
                'default' => 'Antes de adicionar ao carrinho',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_product_assurance_text' => [
                'label' => __('Texto do aviso de produto', 'petshop-core'),
                'default' => 'Confira o conteúdo do pacote, material, aplicação e cuidados descritos nesta página.',
                'type' => 'textarea',
                'sanitize' => 'sanitize_textarea_field',
            ],
            'petshop_shop_description' => [
                'label' => __('Descrição resumida da loja para buscadores', 'petshop-core'),
                'default' => 'Acessórios pet com acabamento cuidadoso para tutores e profissionais.',
                'type' => 'textarea',
                'sanitize' => 'sanitize_textarea_field',
            ],
            'petshop_footer_description' => [
                'label' => __('Descrição curta no rodapé', 'petshop-core'),
                'default' => '',
                'type' => 'textarea',
                'sanitize' => 'sanitize_textarea_field',
            ],
            'petshop_footer_whatsapp' => [
                'label' => __('URL do WhatsApp', 'petshop-core'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
            ],
            'petshop_footer_hours' => [
                'label' => __('Horário de atendimento', 'petshop-core'),
                'default' => '',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_footer_cnpj' => [
                'label' => __('CNPJ', 'petshop-core'),
                'default' => '',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
            'petshop_footer_address' => [
                'label' => __('Endereço', 'petshop-core'),
                'default' => '',
                'type' => 'textarea',
                'sanitize' => 'sanitize_textarea_field',
            ],
            'petshop_footer_instagram' => [
                'label' => __('URL do Instagram', 'petshop-core'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
            ],
            'petshop_footer_facebook' => [
                'label' => __('URL do Facebook', 'petshop-core'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
            ],
            'petshop_footer_payment_text' => [
                'label' => __('Formas de pagamento (texto)', 'petshop-core'),
                'default' => '',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
            ],
        ];
    }

    public static function get(string $id): mixed
    {
        return self::definitions()[$id]['default'] ?? null;
    }
}
