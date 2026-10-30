<?php

defined('ABSPATH') || exit;

use Petshop\Core\WooCommerce\PaymentDiscount\Settings;

$signature = '_plan043_browser_fixture';
$snapshotOption = 'petshop_plan_043_browser_snapshot';
$fixturePath = '/var/www/html/.local/043-browser-fixture.json';

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

$snapshot = get_option($snapshotOption, null);
if (is_array($snapshot)) {
    if (array_key_exists('gateways', $snapshot)) {
        if ($snapshot['gateways'] === null || $snapshot['gateways'] === false) {
            delete_option(Settings::OPTION_GATEWAYS);
        } else {
            update_option(Settings::OPTION_GATEWAYS, $snapshot['gateways'], false);
        }
    }
    if (array_key_exists('label', $snapshot)) {
        if ($snapshot['label'] === null || $snapshot['label'] === false) {
            delete_option(Settings::OPTION_LABEL);
        } else {
            update_option(Settings::OPTION_LABEL, $snapshot['label'], false);
        }
    }
    if (isset($snapshot['coming_soon'])) {
        if ($snapshot['coming_soon'] === null) {
            delete_option('woocommerce_coming_soon');
        } else {
            update_option('woocommerce_coming_soon', $snapshot['coming_soon'], false);
        }
    }
    if (isset($snapshot['store_pages_only'])) {
        if ($snapshot['store_pages_only'] === null) {
            delete_option('woocommerce_store_pages_only');
        } else {
            update_option('woocommerce_store_pages_only', $snapshot['store_pages_only'], false);
        }
    }
    if (isset($snapshot['gateway_enabled']) && is_array($snapshot['gateway_enabled']) && function_exists('WC') && WC()->payment_gateways()) {
        $gateways = WC()->payment_gateways()->payment_gateways();
        foreach ($snapshot['gateway_enabled'] as $id => $enabled) {
            $id = (string) $id;
            $enabled = (string) $enabled;
            if (!isset($gateways[$id]) || !is_object($gateways[$id])) {
                continue;
            }
            $gateways[$id]->enabled = $enabled;
            if (method_exists($gateways[$id], 'update_option')) {
                $gateways[$id]->update_option('enabled', $enabled);
            }
            update_option('woocommerce_' . $id . '_settings', array_merge(
                (array) get_option('woocommerce_' . $id . '_settings', []),
                ['enabled' => $enabled]
            ), false);
        }
    }
    delete_option($snapshotOption);
}

if (is_file($fixturePath)) {
    unlink($fixturePath);
}

echo "043 fixture cleanup ok\n";
