<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce;

use Petshop\Core\WooCommerce\PaymentDiscount\Cart;
use Petshop\Core\WooCommerce\PaymentDiscount\CheckoutSync;
use Petshop\Core\WooCommerce\PaymentDiscount\MiniCartPrice;
use Petshop\Core\WooCommerce\PaymentDiscount\OrderItem;
use Petshop\Core\WooCommerce\PaymentDiscount\ProductMeta;
use Petshop\Core\WooCommerce\PaymentDiscount\Settings;
use Petshop\Core\WooCommerce\PaymentDiscount\Storefront;

defined('ABSPATH') || exit;

final class PaymentDiscount
{
    public static function bootstrap(): void
    {
        Settings::bootstrap();
        ProductMeta::bootstrap();
        Cart::bootstrap();
        OrderItem::bootstrap();
        Storefront::bootstrap();
        CheckoutSync::bootstrap();
        MiniCartPrice::bootstrap();
    }
}
