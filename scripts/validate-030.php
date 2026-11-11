<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce.');
}

use Petshop\Core\Lifecycle;
use Petshop\Core\Settings\DefaultSettings;
use Petshop\Core\StorefrontExperience;
use Petshop\Core\WooCommerce\OrderReceivedMessage;

$failures = [];
$phrase = OrderReceivedMessage::defaultText();
$native = 'Obrigado. Seu pedido foi recebido.';
$originalMod = get_theme_mod(OrderReceivedMessage::SETTING, null);
$originalScheduled = get_option(Lifecycle::SCHEDULED_OPTION, false);

$definitions = DefaultSettings::definitions();
if (($definitions[OrderReceivedMessage::SETTING]['default'] ?? null) !== $phrase) {
    $failures[] = 'DefaultSettings nao provisiona a frase inicial do Plano 030.';
}
if (($definitions[OrderReceivedMessage::SETTING]['section'] ?? 'petshop_store_content') !== 'petshop_store_content') {
    $failures[] = 'A frase da confirmacao deveria estar em Aparencia → Personalizar → Conteudo da loja.';
}

if (!has_filter('woocommerce_thankyou_order_received_text')) {
    $failures[] = 'Filtro woocommerce_thankyou_order_received_text nao registrado.';
}

$english = 'Thank you. Your order has been received.';
$filteredEnglish = (string) apply_filters('woocommerce_thankyou_order_received_text', $english, null);
$filteredPt = (string) apply_filters('woocommerce_thankyou_order_received_text', $native, null);
$failedCopy = 'Your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.';
$filteredFailed = (string) apply_filters('woocommerce_thankyou_order_received_text', $failedCopy, null);

if (!str_contains($filteredEnglish, $phrase) || !str_contains($filteredPt, $phrase)) {
    $failures[] = 'Filtro nao substituiu a frase nativa de pedido recebido.';
}
if ($filteredFailed !== $failedCopy) {
    $failures[] = 'Filtro alterou mensagem de pedido recusado, fora do escopo.';
}

$themeThankyou = get_stylesheet_directory() . '/woocommerce/checkout/thankyou.php';
if (is_file($themeThankyou)) {
    $failures[] = 'Tema copiou o template thankyou.php do WooCommerce.';
}

$checkoutId = (int) get_option('woocommerce_checkout_page_id');
$checkoutContent = $checkoutId > 0 ? (string) get_post_field('post_content', $checkoutId) : '';
$hasCheckoutBlock = str_contains($checkoutContent, 'wp:woocommerce/checkout')
    || str_contains($checkoutContent, 'woocommerce/checkout')
    || str_contains($checkoutContent, 'wp:woocommerce/order-confirmation')
    || str_contains($checkoutContent, 'woocommerce/order-confirmation');
if (!$hasCheckoutBlock) {
    $failures[] = 'Pagina Finalizar compra sem Checkout Block / order-confirmation.';
}

try {
    foreach (wc_get_orders([
        'limit' => 20,
        'meta_key' => '_petshop_gate_030',
        'meta_value' => '1',
        'return' => 'ids',
    ]) as $oldId) {
        $old = wc_get_order((int) $oldId);
        if ($old instanceof WC_Order) {
            $old->delete(true);
        }
    }

    $order = wc_create_order(['customer_id' => 0, 'status' => 'processing']);
    if (!$order instanceof WC_Order) {
        $failures[] = 'Nao foi possivel criar o pedido de teste 030.';
    } else {
        $order->set_billing_email('gate-030@example.test');
        $order->update_meta_data('_petshop_gate_030', '1');
        $productId = (int) wc_get_product_id_by_sku('PLAN013-SIMPLE');
        if ($productId > 0) {
            $product = wc_get_product($productId);
            if ($product instanceof WC_Product) {
                $order->add_product($product, 1);
            }
        }
        $order->calculate_totals();
        $order->save();

        $classicHtml = '';
        ob_start();
        wc_get_template('checkout/thankyou.php', ['order' => $order]);
        $classicHtml = (string) ob_get_clean();
        if (!str_contains($classicHtml, $phrase)) {
            $failures[] = 'Template classico de pedido recebido nao mostrou a frase do Personalizar.';
        }
        if (str_contains($classicHtml, $native)) {
            $failures[] = 'Template classico ainda mostra “Obrigado. Seu pedido foi recebido.”';
        }

        $receivedUrl = $order->get_checkout_order_received_url();
        $path = (string) (wp_parse_url($receivedUrl, PHP_URL_PATH) ?: '');
        $query = (string) (wp_parse_url($receivedUrl, PHP_URL_QUERY) ?: '');
        $internalUrl = 'http://wordpress' . $path . ($query !== '' ? '?' . $query : '');
        $response = wp_remote_get($internalUrl, [
            'timeout' => 30,
            'redirection' => 5,
            'sslverify' => false,
            'headers' => [
                'Host' => (string) (wp_parse_url(home_url(), PHP_URL_HOST) ?: 'localhost:8888'),
            ],
        ]);
        $pageHtml = is_wp_error($response) ? '' : (string) wp_remote_retrieve_body($response);
        $statusCode = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
        if ($statusCode >= 200 && $statusCode < 400 && $pageHtml !== '') {
            if (!str_contains($pageHtml, $phrase)) {
                $failures[] = 'Pagina /finalizar-compra/order-received/ nao mostrou a frase do Personalizar.';
            }
            if (str_contains($pageHtml, $native)) {
                $failures[] = 'Pagina de pedido recebido ainda mostra a string nativa “Obrigado.”';
            }
        } elseif ($classicHtml === '' || !str_contains($classicHtml, $phrase)) {
            $failures[] = 'Nao foi possivel inspecionar a confirmacao renderizada (HTTP ' . $statusCode . ').';
        }

        $uploads = wp_upload_dir();
        $gateDir = rtrim((string) ($uploads['basedir'] ?? ''), '/\\') . '/petshop-gates';
        if (!is_dir($gateDir) && !wp_mkdir_p($gateDir)) {
            $failures[] = 'Nao foi possivel gravar o fixture do gate browser 030.';
        } else {
            $written = file_put_contents(
                $gateDir . '/030.json',
                wp_json_encode([
                    'path' => $path,
                    'query' => $query,
                    'phrase' => OrderReceivedMessage::text(),
                    'native' => $native,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            if ($written === false) {
                $failures[] = 'Falha ao escrever uploads/petshop-gates/030.json.';
            }
        }
    }

    $sentinel = 'Frase persistente 030 ' . wp_generate_password(6, false);
    set_theme_mod(OrderReceivedMessage::SETTING, $sentinel);
    Lifecycle::scheduleMigration();
    StorefrontExperience::maybeEnsureStorefront();
    if (get_theme_mod(OrderReceivedMessage::SETTING) !== $sentinel) {
        $failures[] = 'Reprovisionamento sobrescreveu a frase editada no Personalizar.';
    }
    $afterPersist = (string) apply_filters('woocommerce_thankyou_order_received_text', $english, null);
    if (!str_contains($afterPersist, $sentinel)) {
        $failures[] = 'Filtro nao usou a frase editada apos migrate.';
    }
} finally {
    if ($originalMod === null) {
        remove_theme_mod(OrderReceivedMessage::SETTING);
    } else {
        set_theme_mod(OrderReceivedMessage::SETTING, $originalMod);
    }
    if ($originalScheduled === false) {
        delete_option(Lifecycle::SCHEDULED_OPTION);
    } else {
        update_option(Lifecycle::SCHEDULED_OPTION, $originalScheduled, false);
    }
}

if ($failures !== []) {
    WP_CLI::error('Gate 030 falhou: ' . implode(' | ', $failures));
}

WP_CLI::success('Gate 030: frase da confirmacao administravel, filtros WooCommerce e persistencia aprovados.');
