<?php

defined('ABSPATH') || exit;

use Petshop\Core\WooCommerce\PaymentDiscount\ProductMeta;
use Petshop\Core\WooCommerce\PaymentDiscount\Settings;

$signature = '_plan043_browser_fixture';
$snapshotOption = 'petshop_plan_043_browser_snapshot';
$fixturePath = '/var/www/html/.local/043-browser-fixture.json';

$deleteMarked = static function () use ($signature): void {
    $ids = get_posts([
        'post_type' => ['product', 'product_variation'],
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_key' => $signature,
        'meta_value' => '1',
    ]);
    foreach ($ids as $id) {
        wp_delete_post((int) $id, true);
    }
};

$deleteMarked();

if (get_option($snapshotOption, null) === null) {
    $gatewayStates = [];
    if (function_exists('WC') && WC()->payment_gateways()) {
        foreach (WC()->payment_gateways()->payment_gateways() as $gateway) {
            if (!is_object($gateway) || !isset($gateway->id)) {
                continue;
            }
            $gatewayStates[(string) $gateway->id] = (string) ($gateway->enabled ?? 'no');
        }
    }

    update_option($snapshotOption, [
        'gateways' => get_option(Settings::OPTION_GATEWAYS, null),
        'label' => get_option(Settings::OPTION_LABEL, null),
        'gateway_enabled' => $gatewayStates,
        'coming_soon' => get_option('woocommerce_coming_soon', null),
        'store_pages_only' => get_option('woocommerce_store_pages_only', null),
    ], false);
}

update_option('woocommerce_coming_soon', 'no', false);
update_option('woocommerce_store_pages_only', 'no', false);
update_option(Settings::OPTION_GATEWAYS, ['cheque'], false);
update_option(Settings::OPTION_LABEL, Settings::DEFAULT_LABEL, false);

$enableGateway = static function (string $id, string $enabled): void {
    if (!function_exists('WC') || !WC()->payment_gateways()) {
        return;
    }
    $gateways = WC()->payment_gateways()->payment_gateways();
    if (!isset($gateways[$id]) || !is_object($gateways[$id])) {
        return;
    }
    $gateways[$id]->enabled = $enabled;
    if (method_exists($gateways[$id], 'update_option')) {
        $gateways[$id]->update_option('enabled', $enabled);
    }
    update_option('woocommerce_' . $id . '_settings', array_merge(
        (array) get_option('woocommerce_' . $id . '_settings', []),
        ['enabled' => $enabled]
    ), false);
};

$enableGateway('cheque', 'yes');
$enableGateway('cod', 'yes');

$mark = static function (WC_Product $product) use ($signature): void {
    $product->update_meta_data($signature, '1');
};

$makeAttribute = static function (array $options): WC_Product_Attribute {
    $attribute = new WC_Product_Attribute();
    $attribute->set_id(0);
    $attribute->set_name('opcao');
    $attribute->set_options($options);
    $attribute->set_visible(true);
    $attribute->set_variation(true);

    return $attribute;
};

$makeVariation = static function (
    int $parentId,
    string $option,
    string $price,
    ?string $percent,
    bool $inStock = true
) use ($signature): WC_Product_Variation {
    $variation = new WC_Product_Variation();
    $variation->set_parent_id($parentId);
    $variation->set_status('publish');
    $variation->set_attributes(['opcao' => $option]);
    $variation->set_regular_price($price);
    $variation->set_tax_status('none');
    $variation->set_manage_stock(true);
    $variation->set_stock_quantity($inStock ? 20 : 0);
    $variation->set_stock_status($inStock ? 'instock' : 'outofstock');
    $variation->update_meta_data($signature, '1');
    if ($percent !== null) {
        $variation->update_meta_data(ProductMeta::META, $percent);
    }
    $variation->save();
    $reloaded = wc_get_product($variation->get_id());
    if (!$reloaded instanceof WC_Product_Variation) {
        throw new RuntimeException('Variação não recarregou: ' . $option);
    }

    return $reloaded;
};

$makeVariable = static function (
    string $slug,
    string $name,
    string $description,
    ?string $parentPercent,
    array $variations
) use ($makeAttribute, $makeVariation, $mark): array {
    $options = array_keys($variations);
    $product = new WC_Product_Variable();
    $product->set_name($name);
    $product->set_slug($slug);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_description($description);
    $product->set_short_description($description);
    $product->set_attributes([$makeAttribute($options)]);
    $product->set_stock_status('instock');
    $mark($product);
    if ($parentPercent !== null) {
        $product->update_meta_data(ProductMeta::META, $parentPercent);
    }
    $parentId = $product->save();

    $children = [];
    foreach ($variations as $option => $spec) {
        $child = $makeVariation(
            $parentId,
            $option,
            (string) $spec['price'],
            array_key_exists('percent', $spec) ? $spec['percent'] : null,
            (bool) ($spec['stock'] ?? true)
        );
        $children[$option] = [
            'id' => $child->get_id(),
            'price' => (string) $child->get_regular_price(),
            'percent' => (string) $child->get_meta(ProductMeta::META, true),
            'effective' => ProductMeta::effectivePercent($child),
        ];
    }

    WC_Product_Variable::sync($parentId);
    wc_delete_product_transients($parentId);
    $reloaded = wc_get_product($parentId);
    if (!$reloaded instanceof WC_Product_Variable) {
        throw new RuntimeException('Produto variável não recarregou: ' . $slug);
    }

    return [
        'id' => $parentId,
        'slug' => $slug,
        'url' => get_permalink($parentId),
        'type' => 'variable',
        'parent_percent' => $parentPercent,
        'children' => $children,
    ];
};

$makeSimple = static function (
    string $slug,
    string $name,
    string $regular,
    ?string $sale,
    string $percent
) use ($mark): array {
    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_slug($slug);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_regular_price($regular);
    if ($sale !== null) {
        $product->set_sale_price($sale);
    }
    $product->set_tax_status('none');
    $product->set_stock_status('instock');
    $product->update_meta_data(ProductMeta::META, $percent);
    $mark($product);
    $id = $product->save();
    $reloaded = wc_get_product($id);
    if (!$reloaded instanceof WC_Product_Simple) {
        throw new RuntimeException('Produto simples não recarregou: ' . $slug);
    }

    return [
        'id' => $id,
        'slug' => $slug,
        'url' => get_permalink($id),
        'type' => 'simple',
        'regular' => $regular,
        'sale' => $sale,
        'percent' => $percent,
        'pix_expected' => $sale !== null
            ? wc_format_decimal((string) ((float) $sale * (1 - ((float) $percent / 100))), 2)
            : wc_format_decimal((string) ((float) $regular * (1 - ((float) $percent / 100))), 2),
    ];
};

$products = [
    'p1' => $makeVariable(
        'plano-043-p1-uma-filha',
        'Plano 043 P1 uma filha com Pix',
        'Pai sem %; só a opção Com desconto tem 10%. Preços iguais.',
        null,
        [
            'Com desconto' => ['price' => '100', 'percent' => '10.00'],
            'Sem A' => ['price' => '100', 'percent' => null],
            'Sem B' => ['price' => '100', 'percent' => null],
        ]
    ),
    'p1b' => $makeVariable(
        'plano-043-p1b-pai-e-override',
        'Plano 043 P1B pai 7% e override',
        'Pai 7%; Override 10%; Herdada vazia e 0 herdam 7%. Preços iguais.',
        '7.00',
        [
            'Override' => ['price' => '100', 'percent' => '10.00'],
            'Herdada vazia' => ['price' => '100', 'percent' => null],
            'Herdada zero' => ['price' => '100', 'percent' => '0'],
        ]
    ),
    'p2' => $makeVariable(
        'plano-043-p2-so-pai',
        'Plano 043 P2 só pai',
        'Pai 10%; filhas sem % própria. Preços iguais → sem a partir de.',
        '10.00',
        [
            'A' => ['price' => '100', 'percent' => null],
            'B' => ['price' => '100', 'percent' => null],
            'C' => ['price' => '100', 'percent' => null],
        ]
    ),
    'p3' => $makeVariable(
        'plano-043-p3-sem-desconto',
        'Plano 043 P3 sem desconto',
        'Duas variações sem desconto.',
        null,
        [
            'P' => ['price' => '80', 'percent' => null],
            'G' => ['price' => '90', 'percent' => null],
        ]
    ),
    'p4' => $makeSimple(
        'plano-043-p4-simples',
        'Plano 043 P4 simples 5%',
        '100',
        null,
        '5.00'
    ),
    'p5' => $makeVariable(
        'plano-043-p5-tres-sem',
        'Plano 043 P5 três sem desconto',
        'Três variações sem desconto e preços diferentes.',
        null,
        [
            'P' => ['price' => '70', 'percent' => null],
            'M' => ['price' => '80', 'percent' => null],
            'G' => ['price' => '90', 'percent' => null],
        ]
    ),
    'e2' => $makeVariable(
        'plano-043-e2-a-partir',
        'Plano 043 E2 a partir de',
        'Pai 10%; preços 100/150/200 → a partir de R$ 90.',
        '10.00',
        [
            'Barata' => ['price' => '100', 'percent' => null],
            'Media' => ['price' => '150', 'percent' => null],
            'Cara' => ['price' => '200', 'percent' => null],
        ]
    ),
    'e4' => $makeSimple(
        'plano-043-e4-promocao',
        'Plano 043 E4 promoção + Pix',
        '150',
        '100',
        '10.00'
    ),
    'e5' => $makeVariable(
        'plano-043-e5-zero',
        'Plano 043 E5 variação grátis',
        'Pai 10%; uma variação R$ 0 não deve mostrar R$ 0,00 no Pix.',
        '10.00',
        [
            'Gratis' => ['price' => '0', 'percent' => null],
            'Paga' => ['price' => '100', 'percent' => null],
        ]
    ),
];

$payload = [
    'created_at' => gmdate('c'),
    'discount_gateways' => Settings::gateways(),
    'label' => Settings::label(),
    'payment_with_discount' => 'cheque',
    'payment_without_discount' => 'cod',
    'products' => $products,
];

if (!is_dir(dirname($fixturePath))) {
    wp_mkdir_p(dirname($fixturePath));
}
file_put_contents($fixturePath, wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
