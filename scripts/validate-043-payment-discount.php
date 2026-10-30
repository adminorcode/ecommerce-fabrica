<?php

use Petshop\Core\StorefrontProductCard;
use Petshop\Core\WooCommerce\PaymentDiscount\Cart;
use Petshop\Core\WooCommerce\PaymentDiscount\MiniCartPrice;
use Petshop\Core\WooCommerce\PaymentDiscount\Money;
use Petshop\Core\WooCommerce\PaymentDiscount\OrderItem;
use Petshop\Core\WooCommerce\PaymentDiscount\ProductMeta;
use Petshop\Core\WooCommerce\PaymentDiscount\Settings;
use Petshop\Core\WooCommerce\PaymentDiscount\Storefront;
use Petshop\Core\WooCommerce\PaymentDiscount\Surface;

defined('ABSPATH') || exit;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$decimal = static function (mixed $amount): string {
    return wc_format_decimal((string) $amount, 2);
};

$priceNumber = static function (string $amount): string {
    $text = html_entity_decode(wp_strip_all_tags(wc_price($amount)));
    $text = str_replace("\xc2\xa0", ' ', $text);
    if (preg_match('/\d{1,3}(?:\.\d{3})*,\d+|\d+\.\d+|\d+/', $text, $match) === 1) {
        return $match[0];
    }

    return trim($text);
};

$line = static function () use ($decimal): array {
    $item = null;
    foreach (WC()->cart->get_cart() as $cartItem) {
        $item = $cartItem;
    }
    if (!is_array($item)) {
        throw new RuntimeException('Carrinho sem linha.');
    }

    return [
        'item' => $item,
        'subtotal' => $decimal($item['line_subtotal'] ?? '0'),
        'total' => $decimal($item['line_total'] ?? '0'),
    ];
};

$fee = static function (): string {
    $minor = 0;
    foreach (WC()->cart->get_fees() as $cartFee) {
        if (($cartFee->name ?? '') !== 'Personalização') {
            continue;
        }
        $minor += Money::toMinor((string) ($cartFee->total ?? $cartFee->amount ?? '0'));
    }

    return Money::fromMinor($minor);
};

$cleanupFixtures = static function (): void {
    $products = get_posts([
        'post_type' => ['product', 'product_variation'],
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_key' => '_plan043_fixture',
        'meta_value' => '1',
    ]);
    foreach ($products as $productId) {
        wp_delete_post((int) $productId, true);
    }

    $couponId = function_exists('wc_get_coupon_id_by_code') ? wc_get_coupon_id_by_code('plan043-gate') : 0;
    if ($couponId > 0) {
        wp_delete_post($couponId, true);
    }

    if (function_exists('wc_get_orders')) {
        $orders = wc_get_orders([
            'limit' => 20,
            'status' => 'any',
            'meta_key' => '_plan043_fixture',
            'meta_value' => '1',
        ]);
        foreach ($orders as $order) {
            if ($order instanceof WC_Order) {
                $order->delete(true);
            }
        }
    }
};

$previousCart = WC()->cart;
$previousCustomer = WC()->customer;
$previousSession = WC()->session;
$previousUser = get_current_user_id();
$backupGateways = get_option(Settings::OPTION_GATEWAYS, null);
$backupLabel = get_option(Settings::OPTION_LABEL, null);
$gatewaysExisted = get_option(Settings::OPTION_GATEWAYS, '__missing__') !== '__missing__';
$labelExisted = get_option(Settings::OPTION_LABEL, '__missing__') !== '__missing__';
$quantityProductId = 0;
$quantityHook = static function (WC_Cart $cart) use (&$quantityProductId): void {
    if ($quantityProductId <= 0) {
        return;
    }
    foreach ($cart->get_cart() as $cartItem) {
        $product = $cartItem['data'] ?? null;
        $productId = $product instanceof WC_Product ? $product->get_id() : 0;
        if ($productId === $quantityProductId && $product instanceof WC_Product) {
            $product->set_price('80');
        }
    }
};

add_filter('pre_option_woocommerce_enable_coupons', static fn (): string => 'yes');
add_filter('pre_option_woocommerce_calc_taxes', static fn (): string => 'no');
$cleanupFixtures();

try {
    $checkoutJs = (string) file_get_contents(plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/payment-discount-checkout.js');
    $assert(str_contains($checkoutJs, 'petshop-payment-discount'), 'Script do checkout não avisa o meio selecionado.');
    $assert(str_contains($checkoutJs, 'getActivePaymentMethod'), 'Script do checkout não observa o meio do Checkout Block.');

    global $wp;
    $wp->query_vars['rest_route'] = '/wc/store/v1/checkout';
    $_SERVER['HTTP_REFERER'] = '';
    $assert(Surface::allowsCurrentRequest(), 'Checkout da Store API deveria calcular o desconto.');

    $wp->query_vars['rest_route'] = '/wc/store/v1/cart';
    $_SERVER['HTTP_REFERER'] = home_url('/');
    $assert(!Surface::allowsCurrentRequest(), 'Minicarrinho fora do checkout não deveria calcular o desconto.');

    $_SERVER['HTTP_REFERER'] = wc_get_checkout_url();
    $assert(Surface::allowsCurrentRequest(), 'Carrinho aberto a partir do checkout deveria calcular o desconto.');
    $_SERVER['HTTP_REFERER'] = wc_get_cart_url();
    $assert(Surface::allowsCurrentRequest(), 'Página de carrinho deveria calcular o desconto.');

    update_option(Settings::OPTION_GATEWAYS, [Settings::DEFAULT_GATEWAY]);
    update_option(Settings::OPTION_LABEL, Settings::DEFAULT_LABEL);
    $assert(!in_array('woo-mercado-pago-basic', Settings::gateways(), true), 'Checkout Pro não pode entrar na lista do teste.');

    WC()->session = new class extends WC_Session {};
    WC()->customer = new WC_Customer();
    WC()->cart = new WC_Cart();

    $simple = new WC_Product_Simple();
    $simple->set_name('PLAN043 simples');
    $simple->set_status('publish');
    $simple->set_catalog_visibility('visible');
    $simple->set_regular_price('150');
    $simple->set_sale_price('100');
    $simple->set_tax_status('none');
    $simple->set_stock_status('instock');
    $simple->update_meta_data('_plan043_fixture', '1');
    $simple->update_meta_data(ProductMeta::META, '10.00');
    $simple->save();
    $simple = wc_get_product($simple->get_id());
    $assert($simple instanceof WC_Product_Simple, 'Produto simples não foi recarregado.');

    $coupon = new WC_Coupon();
    $coupon->set_code('plan043-gate');
    $coupon->set_discount_type('percent');
    $coupon->set_amount(10);
    $coupon->save();

    $wp->query_vars['rest_route'] = '/wc/store/v1/checkout';
    $_SERVER['HTTP_REFERER'] = '';
    Cart::remember(Settings::DEFAULT_GATEWAY);
    $added = WC()->cart->add_to_cart($simple->get_id(), 1, 0, [], [Cart::SURCHARGE_KEY => '20.00']);
    $assert((bool) $added, 'Não adicionou o produto simples.');
    $assert(WC()->cart->apply_coupon('plan043-gate'), 'Cupom de 10% não entrou no carrinho.');
    WC()->cart->calculate_totals();
    $pix = $line();
    $assert($pix['subtotal'] === '90.00', 'Pix deveria anunciar R$ 90,00 antes do cupom. Veio ' . $pix['subtotal']);
    $assert($pix['total'] === '81.00', 'Pix com cupom deveria cobrar R$ 81,00. Veio ' . $pix['total']);
    $assert($fee() === '20.00', 'Personalização no Pix deveria continuar R$ 20,00. Veio ' . $fee());
    $pixShipping = $decimal(WC()->cart->get_shipping_total());

    $order = wc_create_order();
    $order->update_meta_data('_plan043_fixture', '1');
    $order->set_payment_method(Settings::DEFAULT_GATEWAY);
    $orderItem = new WC_Order_Item_Product();
    $orderItem->set_product($pix['item']['data']);
    $orderItem->set_quantity((int) $pix['item']['quantity']);
    $orderItem->set_subtotal((string) $pix['item']['line_subtotal']);
    $orderItem->set_total((string) $pix['item']['line_total']);
    OrderItem::snapshot($orderItem, (string) $added, $pix['item']);
    $order->add_item($orderItem);
    $order->save();
    $reloaded = wc_get_order($order->get_id());
    $assert($reloaded instanceof WC_Order, 'Pedido HPOS não reabriu.');
    $reloadedItems = array_values($reloaded->get_items());
    $saved = $reloadedItems[0] ?? null;
    $assert($saved instanceof WC_Order_Item_Product, 'Linha do pedido ausente.');
    $assert($saved->get_meta(OrderItem::META_PERCENT) === '10.00', 'Pedido não guardou a porcentagem efetiva.');
    $assert($saved->get_meta(OrderItem::META_AMOUNT) === '10.00', 'Pedido não guardou o valor do desconto.');
    $assert(OrderItem::displayKey(OrderItem::META_PERCENT, new WC_Meta_Data(['key' => OrderItem::META_PERCENT])) === 'Porcentagem do meio', 'Rótulo da porcentagem no pedido.');

    Cart::remember('woo-mercado-pago-basic');
    WC()->cart->calculate_totals();
    $card = $line();
    $assert($card['subtotal'] === '100.00', 'Cartão deveria voltar ao preço de venda R$ 100,00. Veio ' . $card['subtotal']);
    $assert($card['total'] === '90.00', 'Cartão com o mesmo cupom deveria cobrar R$ 90,00. Veio ' . $card['total']);
    $assert($fee() === '20.00', 'Personalização no cartão deveria continuar R$ 20,00. Veio ' . $fee());
    $assert($decimal(WC()->cart->get_shipping_total()) === $pixShipping, 'Frete mudou entre Pix e cartão.');

    $wp->query_vars['rest_route'] = '/wc/store/v1/cart';
    $_SERVER['HTTP_REFERER'] = home_url('/');
    Cart::remember(Settings::DEFAULT_GATEWAY);
    WC()->cart->calculate_totals();
    $mini = $line();
    $assert($mini['total'] === '90.00', 'Total cobrado do minicarrinho não deveria incluir o Pix antes da seleção. Veio ' . $mini['total']);
    $assert($fee() === '20.00', 'Personalização no minicarrinho deveria continuar R$ 20,00.');
    $miniView = MiniCartPrice::itemData($mini['item']);
    $assert($miniView['visible'] === true, 'Minicarrinho deveria mostrar o preço do meio.');
    $assert(str_contains($miniView['full'], '100,00') || str_contains($miniView['full'], '100.00'), 'Preço cheio do minicarrinho ausente: ' . $miniView['full']);
    $assert(str_contains($miniView['discounted'], '90,00') || str_contains($miniView['discounted'], '90.00'), 'Preço com desconto do minicarrinho ausente: ' . $miniView['discounted']);
    $assert($miniView['label'] === 'no Pix', 'Rótulo do meio ausente no minicarrinho.');
    $miniJs = (string) file_get_contents(plugin_dir_path(PETSHOP_CORE_FILE) . 'assets/js/payment-discount-mini-cart.js');
    $assert(str_contains($miniJs, 'wc-block-mini-cart'), 'Script do minicarrinho não limita a alteração ao bloco.');
    $assert(str_contains($miniJs, 'data-petshop-payment-discount'), 'Script do minicarrinho não marca o preço do meio.');
    $themeCss = (string) file_get_contents(get_stylesheet_directory() . '/style.css');
    $assert(str_contains($themeCss, '.wc-block-mini-cart .petshop-payment-discount'), 'CSS do minicarrinho não destaca o preço do meio.');
    $assert(str_contains($themeCss, '#146349'), 'Preço do meio no minicarrinho não está em verde.');

    $size = new WC_Product_Attribute();
    $size->set_id(0);
    $size->set_name('size');
    $size->set_options(['four', 'empty', 'zero', 'soldout']);
    $size->set_visible(true);
    $size->set_variation(true);
    $variable = new WC_Product_Variable();
    $variable->set_name('PLAN043 variavel');
    $variable->set_status('publish');
    $variable->set_catalog_visibility('visible');
    $variable->set_attributes([$size]);
    $variable->update_meta_data('_plan043_fixture', '1');
    $variable->update_meta_data(ProductMeta::META, '10.00');
    $variableId = $variable->save();

    $makeVariation = static function (string $option, string $price, ?string $percent, bool $inStock) use ($variableId): WC_Product_Variation {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($variableId);
        $variation->set_status('publish');
        $variation->set_attributes(['size' => $option]);
        $variation->set_regular_price($price);
        $variation->set_tax_status('none');
        $variation->set_manage_stock(true);
        $variation->set_stock_quantity($inStock ? 5 : 0);
        $variation->set_stock_status($inStock ? 'instock' : 'outofstock');
        $variation->update_meta_data('_plan043_fixture', '1');
        if ($percent !== null) {
            $variation->update_meta_data(ProductMeta::META, $percent);
        }
        $variation->save();
        $reloadedVariation = wc_get_product($variation->get_id());
        if (!$reloadedVariation instanceof WC_Product_Variation) {
            throw new RuntimeException('Variação não recarregou.');
        }

        return $reloadedVariation;
    };

    $four = $makeVariation('four', '100', '4.00', true);
    $empty = $makeVariation('empty', '100', null, true);
    $zero = $makeVariation('zero', '100', '0', true);
    $makeVariation('soldout', '10', null, false);
    WC_Product_Variable::sync($variableId);
    $variable = wc_get_product($variableId);
    $assert($variable instanceof WC_Product_Variable, 'Produto variável não recarregou.');
    $assert(ProductMeta::effectivePercent($four) === '4.00', 'Variação com 4% deveria usar 4%.');
    $assert(ProductMeta::effectivePercent($empty) === '10.00', 'Variação vazia deveria herdar 10%.');
    $assert(ProductMeta::effectivePercent($zero) === '10.00', 'Variação 0 deveria herdar 10%.');

    WC()->cart->empty_cart();
    $wp->query_vars['rest_route'] = '/wc/store/v1/checkout';
    $_SERVER['HTTP_REFERER'] = '';
    Cart::remember(Settings::DEFAULT_GATEWAY);
    $assert((bool) WC()->cart->add_to_cart($four->get_id(), 1), 'Não adicionou a variação de 4%.');
    $assert(WC()->cart->apply_coupon('plan043-gate'), 'Cupom não voltou na variação.');
    WC()->cart->calculate_totals();
    $variationLine = $line();
    $assert($variationLine['subtotal'] === '96.00', 'Variação de 4% deveria ficar R$ 96,00. Veio ' . $variationLine['subtotal']);
    $assert($variationLine['total'] === '86.40', 'Variação de 4% com cupom deveria ficar R$ 86,40. Veio ' . $variationLine['total']);

    $priceHtml = $simple->get_price_html();
    $assert(str_contains($priceHtml, 'petshop-payment-discount'), 'PDP/card simples sem o segundo preço.');
    $assert(str_contains($priceHtml, $priceNumber('90')), 'Segundo preço simples não é R$ 90,00.');
    $assert(str_contains($priceHtml, 'no Pix'), 'Rótulo inicial ausente.');
    $assert(!str_contains($priceHtml, $priceNumber('135')), 'Desconto usou o preço regular em vez da promoção.');

    StorefrontProductCard::beginLoopCard();
    $cardHtml = $variable->get_price_html();
    StorefrontProductCard::endLoopCard();
    $assert(str_contains($cardHtml, 'data-petshop-card-price'), 'Card variável perdeu o preço da variação selecionada.');
    $assert(str_contains($cardHtml, 'petshop-payment-discount'), 'Card variável sem o segundo preço da variação selecionada.');
    $assert(!str_contains($cardHtml, wc_get_price_html_from_text()), 'Card com variação selecionada ainda mostra “a partir de”.');
    $assert(str_contains($cardHtml, $priceNumber('96')), 'Card não usa o desconto de 4% da variação inicial.');
    $assert(!str_contains($cardHtml, $priceNumber('9')), 'Variação esgotada entrou no preço do card.');
    $parentHtml = $variable->get_price_html();
    $assert(str_contains($parentHtml, wc_get_price_html_from_text()), 'PDP sem variação deveria manter “a partir de” quando o pai tem desconto.');
    $assert(str_contains($parentHtml, $priceNumber('90')), '“A partir de” não usa o menor desconto comprável.');

    $orphanAttribute = new WC_Product_Attribute();
    $orphanAttribute->set_id(0);
    $orphanAttribute->set_name('size');
    $orphanAttribute->set_options(['own', 'none']);
    $orphanAttribute->set_visible(true);
    $orphanAttribute->set_variation(true);
    $orphan = new WC_Product_Variable();
    $orphan->set_name('PLAN043 pai sem desconto');
    $orphan->set_status('publish');
    $orphan->set_attributes([$orphanAttribute]);
    $orphan->update_meta_data('_plan043_fixture', '1');
    $orphanId = $orphan->save();
    $makeOrphanVariation = static function (string $option, string $price, ?string $percent) use ($orphanId): WC_Product_Variation {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($orphanId);
        $variation->set_status('publish');
        $variation->set_attributes(['size' => $option]);
        $variation->set_regular_price($price);
        $variation->set_tax_status('none');
        $variation->set_manage_stock(true);
        $variation->set_stock_quantity(5);
        $variation->set_stock_status('instock');
        $variation->update_meta_data('_plan043_fixture', '1');
        if ($percent !== null) {
            $variation->update_meta_data(ProductMeta::META, $percent);
        }
        $variation->save();
        $reloaded = wc_get_product($variation->get_id());
        if (!$reloaded instanceof WC_Product_Variation) {
            throw new RuntimeException('Variação do pai sem desconto não recarregou.');
        }

        return $reloaded;
    };
    $orphanWithDiscount = $makeOrphanVariation('own', '200', '10.00');
    $orphanWithoutDiscount = $makeOrphanVariation('none', '80', null);
    WC_Product_Variable::sync($orphanId);
    $orphan = wc_get_product($orphanId);
    $orphanWithDiscount = wc_get_product($orphanWithDiscount->get_id());
    $orphanWithoutDiscount = wc_get_product($orphanWithoutDiscount->get_id());
    $assert($orphan instanceof WC_Product_Variable, 'Pai sem desconto não recarregou.');
    $assert($orphanWithDiscount instanceof WC_Product_Variation, 'Variação com desconto próprio não recarregou.');
    $assert($orphanWithoutDiscount instanceof WC_Product_Variation, 'Variação sem desconto não recarregou.');
    $orphanHtml = $orphan->get_price_html();
    $assert(!str_contains($orphanHtml, 'petshop-payment-discount'), 'Pai sem desconto mostrou desconto antes da variação ser selecionada.');
    $assert(str_contains(Storefront::markup($orphanWithDiscount, false), $priceNumber('180')), 'Variação selecionada não usa o próprio desconto.');
    $assert(Storefront::markup($orphanWithoutDiscount, false) === '', 'Variação sem desconto e pai sem desconto ainda gera o segundo preço.');
    $inherited = wc_get_product($empty->get_id());
    $assert($inherited instanceof WC_Product_Variation, 'Variação vazia não recarregou para o preço do pai.');
    $assert(str_contains(Storefront::markup($inherited, false), $priceNumber('90')), 'Variação sem porcentagem própria não usou o desconto do pai.');

    $equal = new WC_Product_Variable();
    $equal->set_name('PLAN043 iguais');
    $equal->set_status('publish');
    $equalAttribute = new WC_Product_Attribute();
    $equalAttribute->set_id(0);
    $equalAttribute->set_name('size');
    $equalAttribute->set_options(['a', 'b']);
    $equalAttribute->set_visible(true);
    $equalAttribute->set_variation(true);
    $equal->set_attributes([$equalAttribute]);
    $equal->update_meta_data('_plan043_fixture', '1');
    $equal->update_meta_data(ProductMeta::META, '10.00');
    $equalId = $equal->save();
    $makeVariationEqual = static function (string $option) use ($equalId): void {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($equalId);
        $variation->set_status('publish');
        $variation->set_attributes(['size' => $option]);
        $variation->set_regular_price('100');
        $variation->set_tax_status('none');
        $variation->set_stock_status('instock');
        $variation->update_meta_data('_plan043_fixture', '1');
        $variation->save();
    };
    $makeVariationEqual('a');
    $makeVariationEqual('b');
    WC_Product_Variable::sync($equalId);
    $equal = wc_get_product($equalId);
    $assert($equal instanceof WC_Product, 'Produto de preços iguais não recarregou.');
    $equalHtml = $equal->get_price_html();
    $assert(!str_contains($equalHtml, wc_get_price_html_from_text()), 'Preço único de desconto não deveria dizer “a partir de”.');
    $assert(str_contains($equalHtml, $priceNumber('90')), 'Preço único com desconto ausente.');
    $equalVariations = $equal instanceof WC_Product_Variable ? $equal->get_available_variations() : [];
    $assert($equalVariations !== [], 'Variações de preço igual não ficaram disponíveis.');
    foreach ($equalVariations as $equalVariation) {
        $assert(
            is_string($equalVariation['price_html'] ?? null) && $equalVariation['price_html'] !== '',
            'Variação com o mesmo preço do pai deveria manter price_html na PDP.'
        );
        $assert(
            str_contains((string) $equalVariation['price_html'], $priceNumber('100')),
            'price_html da variação igual não mostra o preço base.'
        );
    }

    update_option(Settings::OPTION_LABEL, 'na transferência');
    update_option(Settings::OPTION_GATEWAYS, ['bacs']);
    Settings::provision();
    $assert(get_option(Settings::OPTION_LABEL) === 'na transferência', 'Reprovisionar sobrescreveu o rótulo.');
    $assert(get_option(Settings::OPTION_GATEWAYS) === ['bacs'], 'Reprovisionar sobrescreveu a lista.');
    $labeled = $simple->get_price_html();
    $assert(str_contains($labeled, 'na transferência'), 'Vitrine não usou o rótulo gravado.');

    delete_option(Settings::OPTION_GATEWAYS);
    delete_option(Settings::OPTION_LABEL);
    Settings::provision();
    $assert(get_option(Settings::OPTION_GATEWAYS) === [Settings::DEFAULT_GATEWAY], 'Lista inicial não é o Pix oficial.');
    $assert(get_option(Settings::OPTION_LABEL) === Settings::DEFAULT_LABEL, 'Rótulo inicial não é “no Pix”.');
    $assert(!in_array('woo-mercado-pago-basic', Settings::gateways(), true), 'Checkout Pro entrou na lista inicial.');

    $assert(Settings::sanitizeLabel('   ') === 'no Pix', 'Rótulo vazio não volta para “no Pix”.');
    update_option(Settings::OPTION_GATEWAYS, []);
    $assert(Settings::gateways() === [], 'Lista vazia não ficou vazia.');
    $hidden = $simple->get_price_html();
    $assert(!str_contains($hidden, 'petshop-payment-discount'), 'Lista vazia ainda mostra o segundo preço.');
    WC()->cart->empty_cart();
    $wp->query_vars['rest_route'] = '/wc/store/v1/checkout';
    Cart::remember(Settings::DEFAULT_GATEWAY);
    $assert((bool) WC()->cart->add_to_cart($simple->get_id(), 1), 'Não adicionou o produto com lista vazia.');
    $assert(WC()->cart->apply_coupon('plan043-gate'), 'Cupom não entrou com lista vazia.');
    WC()->cart->calculate_totals();
    $emptyList = $line();
    $assert($emptyList['total'] === '90.00', 'Lista vazia ainda aplicou o desconto do Pix. Veio ' . $emptyList['total']);

    update_option(Settings::OPTION_GATEWAYS, [Settings::DEFAULT_GATEWAY]);
    $assert(ProductMeta::persist($simple->get_id(), '10.555') === 'rejected', 'Porcentagem com três casas foi aceita.');
    $assert(get_post_meta($simple->get_id(), ProductMeta::META, true) === '10.00', 'Valor inválido sobrescreveu a porcentagem.');
    $assert(ProductMeta::persist($simple->get_id(), '-1') === 'rejected', 'Porcentagem negativa foi aceita.');
    $assert(ProductMeta::persist($simple->get_id(), '10,5') === 'saved', '10,5 não foi gravado.');
    $assert(get_post_meta($simple->get_id(), ProductMeta::META, true) === '10.50', '10,5 não normalizou para 10.50.');
    $assert(ProductMeta::persist($simple->get_id(), '0') === 'cleared', 'Zero não limpou a porcentagem.');
    $assert(get_post_meta($simple->get_id(), ProductMeta::META, true) === '', 'Zero ainda está gravado.');
    update_post_meta($simple->get_id(), ProductMeta::META, '10.00');

    $admin = get_user_by('id', 1);
    $assert($admin instanceof WP_User, 'Usuário administrador ausente para o nonce.');
    wp_set_current_user(1);
    $assert(current_user_can('manage_woocommerce'), 'Administrador sem manage_woocommerce.');
    $_POST[ProductMeta::FIELD] = '4';
    unset($_POST['woocommerce_meta_nonce']);
    ProductMeta::saveProduct($simple->get_id());
    $assert(get_post_meta($simple->get_id(), ProductMeta::META, true) === '10.00', 'Gravou porcentagem sem nonce.');
    $_POST['woocommerce_meta_nonce'] = wp_create_nonce('woocommerce_save_data');
    $_POST[ProductMeta::FIELD] = '12,5';
    ProductMeta::saveProduct($simple->get_id());
    $assert(get_post_meta($simple->get_id(), ProductMeta::META, true) === '12.50', 'Nonce válido não gravou 12,50.');

    update_option(Settings::OPTION_GATEWAYS, [Settings::DEFAULT_GATEWAY]);
    update_post_meta($simple->get_id(), ProductMeta::META, '10.00');
    $quantity = new WC_Product_Simple();
    $quantity->set_name('PLAN043 quantidade');
    $quantity->set_status('publish');
    $quantity->set_regular_price('100');
    $quantity->set_tax_status('none');
    $quantity->set_stock_status('instock');
    $quantity->update_meta_data('_plan043_fixture', '1');
    $quantity->update_meta_data(ProductMeta::META, '10.00');
    $quantity->save();
    $quantityProductId = $quantity->get_id();
    add_action('woocommerce_before_calculate_totals', $quantityHook, 20);
    WC()->cart->empty_cart();
    $wp->query_vars['rest_route'] = '/wc/store/v1/checkout';
    Cart::remember(Settings::DEFAULT_GATEWAY);
    $assert((bool) WC()->cart->add_to_cart($quantityProductId, 2), 'Não adicionou a quantidade promocional.');
    $assert(WC()->cart->apply_coupon('plan043-gate'), 'Cupom não entrou na quantidade.');
    WC()->cart->calculate_totals();
    $quantityLine = $line();
    $assert($quantityLine['subtotal'] === '144.00', 'Preço por quantidade não foi a base. Veio ' . $quantityLine['subtotal']);
    $assert($quantityLine['total'] === '129.60', 'Cupom não incidiu sobre o preço por quantidade já descontado. Veio ' . $quantityLine['total']);

    echo "043 ok\n";
} finally {
    remove_action('woocommerce_before_calculate_totals', $quantityHook, 20);
    wp_set_current_user($previousUser);
    unset($_POST[ProductMeta::FIELD], $_POST['woocommerce_meta_nonce']);
    if ($gatewaysExisted) {
        update_option(Settings::OPTION_GATEWAYS, $backupGateways);
    } else {
        delete_option(Settings::OPTION_GATEWAYS);
    }
    if ($labelExisted) {
        update_option(Settings::OPTION_LABEL, $backupLabel);
    } else {
        delete_option(Settings::OPTION_LABEL);
    }
    WC()->cart = $previousCart;
    WC()->customer = $previousCustomer;
    WC()->session = $previousSession;
    $cleanupFixtures();
    remove_all_filters('pre_option_woocommerce_enable_coupons');
    remove_all_filters('pre_option_woocommerce_calc_taxes');
}
