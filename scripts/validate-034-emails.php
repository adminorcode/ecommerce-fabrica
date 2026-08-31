<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce.');
}

use Petshop\Core\WooCommerce\TransactionalEmails;

$failures = [];
$emailIds = [
    'customer_processing_order',
    'customer_completed_order',
    'customer_on_hold_order',
    'customer_invoice',
    'customer_cancelled_order',
    'customer_refunded_order',
    'customer_failed_order',
    'customer_note',
];
$originalMods = [];
foreach (['petshop_email_info_title', 'petshop_email_info_text', 'petshop_email_help_text', 'petshop_footer_whatsapp', 'petshop_footer_email', 'petshop_footer_description'] as $mod) {
    $originalMods[$mod] = get_theme_mod($mod, null);
}
$originalDefaultsVersion = get_option('petshop_email_layout_defaults_version', false);
$originalProcessingSettings = get_option('woocommerce_customer_processing_order_settings', false);
$order = null;
$productId = 0;

try {
    foreach (wc_get_orders(['limit' => 20, 'meta_key' => '_petshop_gate_034', 'meta_value' => '1', 'return' => 'ids']) as $oldId) {
        $old = wc_get_order((int) $oldId);
        if ($old instanceof WC_Order) {
            $old->delete(true);
        }
    }

    remove_theme_mod('petshop_email_info_title');
    remove_theme_mod('petshop_email_info_text');
    set_theme_mod('petshop_email_help_text', 'Ajuda persistente 034');
    set_theme_mod('petshop_footer_whatsapp', 'https://wa.me/5500000000000');
    set_theme_mod('petshop_footer_email', 'atendimento034@example.test');
    set_theme_mod('petshop_footer_description', 'Tagline persistente 034');

    $product = new WC_Product_Simple();
    $product->set_name('Produto gate 034');
    $product->set_regular_price('49.90');
    $product->set_sku('GATE-034-' . wp_generate_password(8, false));
    $productId = (int) $product->save();
    $product = wc_get_product($productId);
    if (!$product instanceof WC_Product) {
        WP_CLI::error('Produto de teste indisponivel para o gate 034.');
    }

    $order = wc_create_order(['customer_id' => 0, 'status' => 'processing']);
    if (!$order instanceof WC_Order) {
        WP_CLI::error('Nao foi possivel criar pedido de teste 034.');
    }
    $order->set_billing_first_name('Ana');
    $order->set_billing_last_name('Cliente');
    $order->set_billing_email('gate-034@example.test');
    $order->set_payment_method_title('Pix');
    $order->update_meta_data('_petshop_gate_034', '1');
    $order->update_meta_data('_petshop_tracking_carrier', 'Correios');
    $order->update_meta_data('_petshop_tracking_code', 'BR034TEST');
    $order->update_meta_data('_petshop_tracking_url', 'https://rastreamento.example.test/BR034TEST');
    $order->add_product($product, 2);
    $order->calculate_totals();
    $order->save();

    TransactionalEmails::ensureEmailDefaults();
    $emails = WC()->mailer()->get_emails();

    foreach ($emails as $email) {
        if (!is_object($email) || !isset($email->id) || !is_string($email->id)) {
            continue;
        }
        ob_start();
        do_action('woocommerce_email_header', 'Gate global 034', $email);
        do_action('woocommerce_email_footer', $email);
        $shell = (string) ob_get_clean();
        if (!str_contains($shell, 'petshop-email-shell') || !str_contains($shell, '#004F50')) {
            $failures[] = "Email {$email->id} nao recebeu o casco global de marca.";
        }
    }

    foreach ($emailIds as $emailId) {
        $email = petshop_gate_034_email($emails, $emailId);
        if (!is_object($email)) {
            $failures[] = "Email {$emailId} nao encontrado.";
            continue;
        }

        petshop_gate_034_prime_email($email, $order);
        $html = (string) $email->get_content_html();
        foreach (['#126E70', '#C94B0B', '#004F50', 'Olá, Ana!', 'Pedido #' . $order->get_order_number(), 'Resumo do pedido', 'Pix', 'Tagline persistente 034', 'Precisa de ajuda?', 'Rastreamento da entrega', 'BR034TEST'] as $needle) {
            if (!str_contains($html, $needle)) {
                $failures[] = "Email {$emailId} nao contem {$needle}.";
            }
        }
        foreach (['#5bc1c3', '#f37d35', '#e6e7e9', 'Layout de referência para desenvolvimento'] as $forbidden) {
            if (str_contains(strtolower($html), strtolower($forbidden))) {
                $failures[] = "Email {$emailId} contem valor proibido {$forbidden}.";
            }
        }

        $expectsPayment = in_array($emailId, ['customer_invoice', 'customer_on_hold_order'], true);
        if ($expectsPayment && !str_contains($html, 'Pagar agora')) {
            $failures[] = "Email {$emailId} nao usa CTA Pagar agora.";
        }
        if (!$expectsPayment && !str_contains($html, 'Acompanhar meu pedido')) {
            $failures[] = "Email {$emailId} nao usa CTA Acompanhar meu pedido.";
        }
        if (!$expectsPayment && (!str_contains($html, 'order-received') || !str_contains($html, 'key='))) {
            $failures[] = "Email {$emailId} nao usa URL de acompanhamento com chave para visitante.";
        }
        if ($emailId === 'customer_processing_order' && !str_contains($html, 'Pagamento confirmado')) {
            $failures[] = 'Processing nao mostra tracker.';
        }
        if ($emailId === 'customer_completed_order' && !str_contains($html, 'Pedido concluído')) {
            $failures[] = 'Completed nao mostra quatro passos preenchidos.';
        }
        if (in_array($emailId, ['customer_on_hold_order', 'customer_invoice'], true) && str_contains($html, 'Separação / produção')) {
            $failures[] = "Email {$emailId} exibiu tracker fora do escopo.";
        }
    }

    set_theme_mod('petshop_email_info_title', '');
    set_theme_mod('petshop_email_info_text', '');
    $processing = petshop_gate_034_email($emails, 'customer_processing_order');
    if (is_object($processing)) {
        petshop_gate_034_prime_email($processing, $order);
        $html = (string) $processing->get_content_html();
        if (str_contains($html, 'Informação importante')) {
            $failures[] = 'Caixa Informacao importante nao foi ocultada vazia.';
        }
    }

    $sentinel = 'Heading persistente 034 ' . wp_generate_password(6, false);
    $settings = get_option('woocommerce_customer_processing_order_settings', []);
    $settings = is_array($settings) ? $settings : [];
    $settings['heading'] = $sentinel;
    update_option('woocommerce_customer_processing_order_settings', $settings, false);
    delete_option('petshop_email_layout_defaults_version');
    TransactionalEmails::ensureEmailDefaults();
    $after = get_option('woocommerce_customer_processing_order_settings', []);
    if (!is_array($after) || ($after['heading'] ?? '') !== $sentinel) {
        $failures[] = 'Defaults dos e-mails sobrescreveram heading editado.';
    }

    $customBox = 'Caixa persistente 034 ' . wp_generate_password(6, false);
    set_theme_mod('petshop_email_info_text', $customBox);
    if (get_theme_mod('petshop_email_info_text') !== $customBox) {
        $failures[] = 'Customizer de e-mails nao preservou texto editado.';
    }

    foreach (['customer_processing_order', 'customer_invoice', 'customer_cancelled_order'] as $plainId) {
        $plainEmail = petshop_gate_034_email($emails, $plainId);
        if (!is_object($plainEmail)) {
            continue;
        }
        petshop_gate_034_prime_email($plainEmail, $order);
        $plain = (string) $plainEmail->get_content_plain();
        foreach (['<!DOCTYPE', 'petshop-email', '#004F50', '#126E70', '#C94B0B'] as $htmlNeedle) {
            if (str_contains($plain, $htmlNeedle)) {
                $failures[] = "Plain text {$plainId} contem HTML customizado ({$htmlNeedle}).";
            }
        }
        if (!str_contains($plain, 'Produto gate 034') || !str_contains($plain, 'Ana Cliente')) {
            $failures[] = "Plain text {$plainId} perdeu dados nativos do pedido/cliente.";
        }
    }
} finally {
    foreach ($originalMods as $mod => $value) {
        if ($value === null) {
            remove_theme_mod($mod);
        } else {
            set_theme_mod($mod, $value);
        }
    }
    if ($originalDefaultsVersion === false) {
        delete_option('petshop_email_layout_defaults_version');
    } else {
        update_option('petshop_email_layout_defaults_version', $originalDefaultsVersion, false);
    }
    if ($originalProcessingSettings === false) {
        delete_option('woocommerce_customer_processing_order_settings');
    } else {
        update_option('woocommerce_customer_processing_order_settings', $originalProcessingSettings, false);
    }
    if ($order instanceof WC_Order) {
        $order->delete(true);
    }
    if ($productId > 0) {
        $product = wc_get_product($productId);
        if ($product instanceof WC_Product) {
            $product->delete(true);
        }
    }
}

if ($failures !== []) {
    WP_CLI::error('Gate 034 falhou: ' . implode(' | ', array_unique($failures)));
}

WP_CLI::success('Gate 034: layout HTML dos e-mails de compra, CTAs, tokens, rastreio, plain text e persistencia aprovados.');

function petshop_gate_034_email(array $emails, string $emailId): ?object
{
    foreach ($emails as $candidate) {
        if (isset($candidate->id) && $candidate->id === $emailId) {
            return $candidate;
        }
    }

    return null;
}

function petshop_gate_034_prime_email(object $email, WC_Order $order): void
{
    $reflection = new ReflectionObject($email);
    foreach (['object' => $order, 'recipient' => $order->get_billing_email()] as $property => $value) {
        if ($reflection->hasProperty($property)) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($email, $value);
        }
    }
    if ($reflection->hasProperty('customer_note')) {
        $prop = $reflection->getProperty('customer_note');
        $prop->setAccessible(true);
        $prop->setValue($email, 'Atualização de teste do pedido.');
    }
}
