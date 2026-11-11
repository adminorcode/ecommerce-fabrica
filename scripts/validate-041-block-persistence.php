<?php

use Petshop\Core\WooCommerce\CartBlocksIntegration;

defined('ABSPATH') || exit;
$cartPage = get_option('woocommerce_cart_page_id');
$migration = get_option('petshop_cart_shipping_quote_block_v1', null);
$repair = get_option('petshop_cart_shipping_quote_wrapper_v2', null);
$position = get_option('petshop_cart_shipping_quote_position_v3', null);
$page = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Synthetic block persistence 041',
    'post_content' => '<!-- wp:paragraph --><p>Synthetic editable text</p><!-- /wp:paragraph --><!-- wp:image {"id":123} --><figure class="wp-block-image"><img src="https://example.test/synthetic-initial.png" alt="Synthetic initial alt" class="wp-image-123"/></figure><!-- /wp:image --><!-- wp:woocommerce/cart --><div class="wp-block-woocommerce-cart"><!-- wp:woocommerce/cart-totals-block --><div class="wp-block-woocommerce-cart-totals-block"><!-- wp:petshop/cart-shipping-quote /--></div><!-- /wp:woocommerce/cart-totals-block --></div><!-- /wp:woocommerce/cart -->']);
try {
    $escaped = serialize_block(['blockName' => 'core/paragraph', 'attrs' => ['metadata' => ['name' => 'Synthetic "quoted" \\ label']],
        'innerBlocks' => [], 'innerHTML' => '<p>Synthetic escaping</p>', 'innerContent' => ['<p>Synthetic escaping</p>']]);
    wp_update_post(wp_slash(['ID' => $page, 'post_content' => get_post_field('post_content', $page) . $escaped]));
    update_option('woocommerce_cart_page_id', $page);
    delete_option('petshop_cart_shipping_quote_block_v1');
    $original = get_post_field('post_content', $page);
    CartBlocksIntegration::migrateCartPage();
    if (get_post_field('post_content', $page) !== $original || !get_option('petshop_cart_shipping_quote_block_v1')) {
        throw new RuntimeException('Existing nested block duplicated or migration not recorded');
    }
    $edited = str_replace('<!-- wp:petshop/cart-shipping-quote /-->', '', $original);
    $edited = str_replace('Synthetic editable text', 'Synthetic client revision', $edited);
    $edited = str_replace(['synthetic-initial.png', 'Synthetic initial alt'], ['synthetic-client.png', 'Synthetic client alt'], $edited);
    wp_update_post(wp_slash(['ID' => $page, 'post_content' => $edited]));
    CartBlocksIntegration::migrateCartPage();
    if (get_post_field('post_content', $page) !== $edited) throw new RuntimeException('Client text/removal overwritten by reprovision');
    delete_option('petshop_cart_shipping_quote_block_v1');
    CartBlocksIntegration::migrateCartPage();
    $inserted = get_post_field('post_content', $page);
    if (substr_count($inserted, 'wp:petshop/cart-shipping-quote') !== 1 || !str_contains($inserted, 'Synthetic client revision')
        || !str_contains($inserted, 'synthetic-client.png') || !str_contains($inserted, 'Synthetic client alt') || !str_contains($inserted, $escaped)) {
        throw new RuntimeException('Initial insertion lost editable content or duplicated block');
    }
    if (!str_contains($inserted, '<!-- wp:petshop/cart-shipping-quote /--></div>')) throw new RuntimeException('Quote inserted outside saved totals wrapper');
    $broken = str_replace('<!-- wp:petshop/cart-shipping-quote /--></div>', '</div><!-- wp:petshop/cart-shipping-quote /-->', $inserted);
    wp_update_post(wp_slash(['ID' => $page, 'post_content' => $broken]));
    delete_option('petshop_cart_shipping_quote_wrapper_v2');
    CartBlocksIntegration::repairCartQuoteWrapper();
    if (get_post_field('post_content', $page) !== $inserted || !get_option('petshop_cart_shipping_quote_wrapper_v2')) {
        throw new RuntimeException('Legacy wrapper repair lost content or did not move slot inside totals');
    }
    wp_update_post(wp_slash(['ID' => $page, 'post_content' => $edited]));
    delete_option('petshop_cart_shipping_quote_wrapper_v2');
    CartBlocksIntegration::repairCartQuoteWrapper();
    if (get_post_field('post_content', $page) !== $edited) throw new RuntimeException('Repair recreated a removed block');
    $beforeQuote = '<!-- wp:paragraph --><p>Synthetic summary</p><!-- /wp:paragraph -->';
    $lastQuote = str_replace('<!-- wp:petshop/cart-shipping-quote /-->', $beforeQuote . '<!-- wp:petshop/cart-shipping-quote /-->', $inserted);
    wp_update_post(wp_slash(['ID' => $page, 'post_content' => $lastQuote]));
    delete_option('petshop_cart_shipping_quote_position_v3');
    CartBlocksIntegration::positionCartQuoteBeforeSummary();
    $positioned = get_post_field('post_content', $page);
    if (strpos($positioned, '<!-- wp:petshop/cart-shipping-quote /-->') > strpos($positioned, $beforeQuote)
        || !str_contains($positioned, $escaped)) throw new RuntimeException('CEP not positioned before summary or editable attributes lost');
    wp_update_post(wp_slash(['ID' => $page, 'post_content' => $lastQuote]));
    CartBlocksIntegration::positionCartQuoteBeforeSummary();
    if (get_post_field('post_content', $page) !== $lastQuote) throw new RuntimeException('Position migration overwrote a later editor reorder');
    wp_update_post(wp_slash(['ID' => $page, 'post_content' => $edited]));
    delete_option('petshop_cart_shipping_quote_position_v3');
    CartBlocksIntegration::positionCartQuoteBeforeSummary();
    if (get_post_field('post_content', $page) !== $edited) throw new RuntimeException('Position migration recreated removed block');
    fwrite(STDOUT, "041 block persistence: insertion inside wrapper, legacy repair, existing block, client removal/text/image/alt revision passed.\n");
} finally {
    update_option('woocommerce_cart_page_id', $cartPage);
    if ($migration === null) delete_option('petshop_cart_shipping_quote_block_v1');
    else update_option('petshop_cart_shipping_quote_block_v1', $migration, false);
    if ($repair === null) delete_option('petshop_cart_shipping_quote_wrapper_v2');
    else update_option('petshop_cart_shipping_quote_wrapper_v2', $repair, false);
    if ($position === null) delete_option('petshop_cart_shipping_quote_position_v3');
    else update_option('petshop_cart_shipping_quote_position_v3', $position, false);
    wp_delete_post($page, true);
}
