<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce.');
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$failures = [];
$plugins = [
    'melhor-envio-cotacao/melhor-envio-beta.php' => [
        'name' => 'Melhor Envio',
        'version' => '2.16.6',
        'autoload' => WP_PLUGIN_DIR . '/melhor-envio-cotacao/vendor/autoload.php',
    ],
    'woo-better-shipping-calculator-for-brazil/wc-better-shipping-calculator-for-brazil.php' => [
        'name' => 'Calculadora de Frete e Campos Checkout para o Brasil',
        'version' => '4.17.1',
        'autoload' => WP_PLUGIN_DIR . '/woo-better-shipping-calculator-for-brazil/vendor/autoload.php',
    ],
];

foreach ($plugins as $pluginFile => $expected) {
    $absolute = WP_PLUGIN_DIR . '/' . $pluginFile;
    if (!file_exists($absolute)) {
        $failures[] = "Plugin ausente: {$pluginFile}";
        continue;
    }

    if (!is_plugin_active($pluginFile)) {
        $failures[] = "Plugin inativo: {$pluginFile}";
    }

    $data = get_plugin_data($absolute, false, false);
    if (($data['Version'] ?? '') !== $expected['version']) {
        $failures[] = sprintf(
            '%s em versao inesperada: esperado %s, encontrado %s',
            $expected['name'],
            $expected['version'],
            (string) ($data['Version'] ?? '')
        );
    }

    if (!file_exists($expected['autoload'])) {
        $failures[] = "Autoload ausente: {$expected['autoload']}";
    }
}

$brazilianMarket = 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php';
if (file_exists(WP_PLUGIN_DIR . '/' . $brazilianMarket) && is_plugin_active($brazilianMarket)) {
    $failures[] = 'Brazilian Market esta ativo junto da Calculadora BR; os plugins declaram conflito de campos.';
}

if (class_exists('\MelhorEnvio\Services\CheckHealthService')) {
    $health = (new \MelhorEnvio\Services\CheckHealthService())->checkPathPlugin(WP_PLUGIN_DIR);
    if (!empty($health['errors'])) {
        $failures[] = 'Health check do Melhor Envio ainda retorna erro: ' . implode(' | ', array_map('wp_strip_all_tags', $health['errors']));
    }
} else {
    $failures[] = 'Classe de health check do Melhor Envio nao carregou.';
}

$expectedOptions = [
    'woo_better_calc_enable_product_page' => 'no',
    'woo_better_calc_enable_cart_page' => 'no',
    'woo_better_calc_enable_auto_address_fill' => 'no',
];

foreach ($expectedOptions as $optionName => $expectedValue) {
    $actualValue = (string) get_option($optionName, '');
    if ($actualValue !== $expectedValue) {
        $failures[] = "{$optionName} deve ser {$expectedValue}; encontrado {$actualValue}";
    }
}

$noticeOption = get_option('wp_option_notices_melhor_envio', []);
$noticeText = is_array($noticeOption) ? implode("\n", array_map('strval', $noticeOption)) : (string) $noticeOption;
if (str_contains($noticeText, 'necessita obrigatoriamente') || str_contains($noticeText, 'woo-better-shipping-calculator-for-brazil') || str_contains($noticeText, 'woocommerce-extra-checkout-fields-for-brazil')) {
    $failures[] = 'Aviso antigo de dependencia do Melhor Envio ainda esta persistido.';
}

$sensitivePatterns = [
    'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9',
    'WORDPRESS_ADMIN_PASSWORD',
    'MYSQL_PASSWORD',
    'BEGIN RSA',
    'PRIVATE KEY',
];

$versionedPluginDirs = [
    WP_PLUGIN_DIR . '/melhor-envio-cotacao',
    WP_PLUGIN_DIR . '/woo-better-shipping-calculator-for-brazil',
];

foreach ($versionedPluginDirs as $dir) {
    if (!is_dir($dir)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
        $path = $file->getPathname();
        if ($file->getSize() > 1024 * 1024) continue;
        $contents = file_get_contents($path);
        if ($contents === false) continue;
        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($contents, $pattern)) {
                $failures[] = "Padrao sensivel encontrado em plugin versionado: {$path}";
            }
        }
    }
}

if ($failures !== []) {
    WP_CLI::error('Gate 036 falhou: ' . implode(' | ', $failures));
}

WP_CLI::success('Gate 036: dependencias versionadas de frete ativas, vendors presentes, calculadoras duplicadas desativadas e aviso do Melhor Envio ausente.');
