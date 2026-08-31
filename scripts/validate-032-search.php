<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce.');
}

$failures = [];

ob_start();
get_product_search_form();
$form = (string) ob_get_clean();

if (!preg_match('/<form\b[^>]*method=["\']get["\']/i', $form)) {
    $failures[] = 'Formulario de busca nao usa GET.';
}
if (!preg_match('/<input\b[^>]*name=["\']s["\']/i', $form)) {
    $failures[] = 'Formulario de busca nao possui input name=s.';
}
if (!preg_match('/<input\b[^>]*name=["\']post_type["\'][^>]*value=["\']product["\']/i', $form)) {
    $failures[] = 'Formulario de busca nao preserva post_type=product.';
}
if (!preg_match('/<button\b[^>]*type=["\']submit["\']/i', $form)) {
    $failures[] = 'Lupa nao e botao submit.';
}

$host = (string) (wp_parse_url(home_url(), PHP_URL_HOST) ?: 'localhost:8888');
$searchUrl = 'http://wordpress/?s=' . rawurlencode('Bandana') . '&post_type=product';
$response = wp_remote_get($searchUrl, [
    'timeout' => 30,
    'redirection' => 0,
    'sslverify' => false,
    'headers' => ['Host' => $host],
]);
$status = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
$body = is_wp_error($response) ? '' : (string) wp_remote_retrieve_body($response);
if ($status !== 200) {
    $failures[] = 'Busca por nome nao retornou HTTP 200 sem JS (HTTP ' . $status . ').';
}
if (!str_contains($body, 'products') || !str_contains($body, 'Bandana')) {
    $failures[] = 'Busca por nome nao renderizou grade/lista de produtos esperada.';
}
if (str_contains($body, 'name="search"')) {
    $failures[] = 'Pagina de resultados confundiu query WP com parametro search da Store API.';
}

$emptyUrl = 'http://wordpress/?s=consulta-sem-resultado-032&post_type=product';
$emptyResponse = wp_remote_get($emptyUrl, [
    'timeout' => 30,
    'redirection' => 0,
    'sslverify' => false,
    'headers' => ['Host' => $host],
]);
$emptyBody = is_wp_error($emptyResponse) ? '' : (string) wp_remote_retrieve_body($emptyResponse);
if (!str_contains($emptyBody, 'petshop-search-empty')) {
    $failures[] = 'Termo inexistente nao manteve estado vazio do Plano 013.';
}

$skuResponse = wp_remote_get('http://wordpress/?s=PLAN013-SIMPLE&post_type=product', [
    'timeout' => 30,
    'redirection' => 0,
    'sslverify' => false,
    'headers' => ['Host' => $host],
]);
$skuStatus = is_wp_error($skuResponse) ? 0 : (int) wp_remote_retrieve_response_code($skuResponse);
$skuLocation = is_wp_error($skuResponse) ? '' : (string) wp_remote_retrieve_header($skuResponse, 'location');
if ($skuStatus !== 302 || !str_contains($skuLocation, '/product/bandana-essencial-amostra-plano-013/')) {
    $failures[] = 'SKU exato nao preservou redirecionamento para PDP.';
}

if ($failures !== []) {
    WP_CLI::error('Gate 032 falhou: ' . implode(' | ', $failures));
}

WP_CLI::success('Gate 032: busca sem JS, URL de produto, vazio e SKU exato aprovados.');
