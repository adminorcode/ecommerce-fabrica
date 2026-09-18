<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce.');
}

$failures = [];

if (!class_exists(\Petshop\Core\WooCommerce\CartQuantityStability::class)) {
    $failures[] = 'CartQuantityStability nao foi carregada.';
}

$script = plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/cart-quantity-stability.js';
if (!is_file($script)) {
    $failures[] = 'assets/js/cart-quantity-stability.js ausente.';
}

$previous = get_option('woo_better_calc_enable_cart_page', 'no');
update_option('woo_better_calc_enable_cart_page', 'yes');

try {
    $home = wp_parse_url(home_url());
    $host = (string) ($home['host'] ?? 'localhost');
    if (isset($home['port'])) {
        $host .= ':' . $home['port'];
    }
    $cartPath = (string) (wp_parse_url(wc_get_cart_url() ?: home_url('/carrinho/'), PHP_URL_PATH) ?: '/carrinho/');
    $cartUrl = 'http://wordpress' . $cartPath;
    $response = wp_remote_get($cartUrl, [
        'timeout' => 30,
        'redirection' => 0,
        'sslverify' => false,
        'headers' => ['Host' => $host],
    ]);
    $status = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
    $body = is_wp_error($response) ? '' : (string) wp_remote_retrieve_body($response);

    if ($status !== 200) {
        $failures[] = 'Carrinho nao retornou HTTP 200 (HTTP ' . $status . ').';
    }
    if (str_contains($body, 'CustomCartPostcode')) {
        $failures[] = 'JS de CEP do plugin brasileiro ainda aparece no carrinho com a option ligada.';
    }
    if (str_contains($body, 'PublicCEPField.COMPILED') || str_contains($body, 'ProgressBar.COMPILED')) {
        $failures[] = 'JS extra do plugin brasileiro ainda aparece no carrinho.';
    }
    if (!str_contains($body, 'petshop-cart-quantity-stability') && !str_contains($body, 'data-petshop-cart-shipping')) {
        $failures[] = 'CEP proprio do petshop-core ausente no HTML do carrinho.';
    }
    if (!str_contains($body, 'petshop-cart-quantity-guard')) {
        $failures[] = 'Guard de quantidade ausente no HTML do carrinho.';
    }
    if (!str_contains($body, 'petshopCartQtyConfig') || !str_contains($body, 'shippingDebounceMs')) {
        $failures[] = 'Debounce de 1s do frete ausente no carrinho.';
    }
    if (str_contains($body, 'viacep.com.br')) {
        $failures[] = 'Calculadora de frete do carrinho nao pode consultar ViaCEP.';
    }
} finally {
    update_option('woo_better_calc_enable_cart_page', $previous);
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Gate 039 PHP aprovado.\n");
