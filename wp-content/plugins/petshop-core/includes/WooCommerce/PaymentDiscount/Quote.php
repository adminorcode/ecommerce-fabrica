<?php

declare(strict_types=1);

namespace Petshop\Core\WooCommerce\PaymentDiscount;

defined('ABSPATH') || exit;

/**
 * Sale price, then payment-method percent, then a percent coupon on what remains.
 * Personalization is not an input: it stays outside this quote.
 */
final class Quote
{
    public static function productAfterCoupon(string $sale, string $paymentPercent, string $couponPercent, bool $paymentApplies): string
    {
        $afterPayment = $paymentApplies
            ? Money::discounted($sale, $paymentPercent)
            : Money::normalize($sale);

        if (Money::basisPoints($couponPercent) <= 0) {
            return $afterPayment;
        }

        return Money::discounted($afterPayment, $couponPercent);
    }
}
