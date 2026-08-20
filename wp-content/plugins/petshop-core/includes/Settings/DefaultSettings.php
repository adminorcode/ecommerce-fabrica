<?php

declare(strict_types=1);

namespace Petshop\Core\Settings;

defined('ABSPATH') || exit;

final class DefaultSettings
{
    /**
     * @return array<string, array{
     *   label: string,
     *   default: mixed,
     *   type: string,
     *   sanitize: callable-string,
     *   section?: string,
     *   description?: string
     * }>
     */
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
            'petshop_checkout_assurance_text' => [
                'label' => __('Mensagem de segurança no checkout', 'petshop-core'),
                'default' => 'Compra segura',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
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
            'petshop_order_next_steps' => [
                'label' => __('Próximos passos após a compra', 'petshop-core'),
                'default' => 'Você receberá as atualizações do pedido pelos canais informados na compra.',
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
                'default' => 'Acessórios que valorizam cada detalhe do seu pet.',
                'type' => 'textarea',
                'sanitize' => 'sanitize_textarea_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_instagram' => [
                'label' => __('URL do Instagram', 'petshop-core'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_facebook' => [
                'label' => __('URL do Facebook', 'petshop-core'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_tiktok' => [
                'label' => __('URL do TikTok', 'petshop-core'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_social_whatsapp' => [
                'label' => __('URL do WhatsApp (redes sociais)', 'petshop-core'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
                'section' => 'petshop_footer',
                'description' => __('Separado do WhatsApp de atendimento. Vazio oculta o link nas redes.', 'petshop-core'),
            ],
            'petshop_footer_whatsapp' => [
                'label' => __('URL do WhatsApp (atendimento)', 'petshop-core'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
                'section' => 'petshop_footer',
                'description' => __('Usado no rodapé e também como link de atendimento do cabeçalho quando preenchido.', 'petshop-core'),
            ],
            'petshop_footer_whatsapp_label' => [
                'label' => __('Texto auxiliar do WhatsApp', 'petshop-core'),
                'default' => 'Fale conosco',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
                'description' => __('Ex.: Fale conosco. Aparece abaixo do título WhatsApp. Vazio oculta o subtítulo.', 'petshop-core'),
            ],
            'petshop_footer_email' => [
                'label' => __('E-mail de atendimento', 'petshop-core'),
                'default' => '',
                'type' => 'email',
                'sanitize' => 'sanitize_email',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_support_text' => [
                'label' => __('Texto auxiliar do atendimento', 'petshop-core'),
                'default' => 'Tire suas dúvidas',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
                'description' => __('Aparece abaixo de “Atendimento ao cliente”. A URL vem da página de atendimento do cabeçalho. Vazio oculta o subtítulo.', 'petshop-core'),
            ],
            'petshop_footer_hours' => [
                'label' => __('Horário de atendimento', 'petshop-core'),
                'default' => "Segunda a Sexta: 9h às 18h\nSábados: 9h às 13h",
                'type' => 'textarea',
                'sanitize' => 'sanitize_textarea_field',
                'section' => 'petshop_footer',
                'description' => __('Uma linha por período. Vazio oculta o item.', 'petshop-core'),
            ],
            'petshop_footer_faq_url' => [
                'label' => __('URL do FAQ / perguntas frequentes', 'petshop-core'),
                'default' => '',
                'type' => 'url',
                'sanitize' => 'esc_url_raw',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_faq_text' => [
                'label' => __('Texto auxiliar do FAQ', 'petshop-core'),
                'default' => 'Encontre respostas rápidas',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
                'description' => __('Aparece abaixo de “Perguntas frequentes”. Sem URL o item fica oculto.', 'petshop-core'),
            ],
            'petshop_footer_payment_text' => [
                'label' => __('Formas de pagamento (texto)', 'petshop-core'),
                'default' => '',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_trust_1_title' => [
                'label' => __('Selo 1 — título', 'petshop-core'),
                'default' => 'Compra segura',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_trust_1_text' => [
                'label' => __('Selo 1 — descrição', 'petshop-core'),
                'default' => 'Seus dados protegidos',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_trust_2_title' => [
                'label' => __('Selo 2 — título', 'petshop-core'),
                'default' => 'Qualidade e cuidado',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_trust_2_text' => [
                'label' => __('Selo 2 — descrição', 'petshop-core'),
                'default' => 'Produtos feitos com carinho',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_trust_3_title' => [
                'label' => __('Selo 3 — título', 'petshop-core'),
                'default' => 'Ambiente protegido',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_trust_3_text' => [
                'label' => __('Selo 3 — descrição', 'petshop-core'),
                'default' => 'Navegação e pagamento seguros',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_trust_4_title' => [
                'label' => __('Selo 4 — título', 'petshop-core'),
                'default' => 'Envio para todo Brasil',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_trust_4_text' => [
                'label' => __('Selo 4 — descrição', 'petshop-core'),
                'default' => 'Com rastreamento',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_copyright' => [
                'label' => __('Copyright / nome fantasia', 'petshop-core'),
                'default' => '© 2026 Auteliê Moda Pet',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_legal_name' => [
                'label' => __('Razão social / MEI', 'petshop-core'),
                'default' => '',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_cnpj' => [
                'label' => __('CNPJ', 'petshop-core'),
                'default' => '',
                'type' => 'text',
                'sanitize' => 'sanitize_text_field',
                'section' => 'petshop_footer',
            ],
            'petshop_footer_address' => [
                'label' => __('Endereço', 'petshop-core'),
                'default' => '',
                'type' => 'textarea',
                'sanitize' => 'sanitize_textarea_field',
                'section' => 'petshop_footer',
            ],
        ];
    }

    public static function get(string $id): mixed
    {
        return self::definitions()[$id]['default'] ?? null;
    }
}
