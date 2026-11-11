<?php

declare(strict_types=1);

use Petshop\Core\WooCommerce\PaymentDiscount\Money;
use Petshop\Core\WooCommerce\PaymentDiscount\Percent;
use Petshop\Core\WooCommerce\PaymentDiscount\Quote;
use Petshop\Core\WooCommerce\PaymentDiscount\Settings;
use PHPUnit\Framework\TestCase;

final class PaymentDiscountTest extends TestCase
{
    public function testParseAcceptsUpToTwoDecimalsAndRejectsTheRest(): void
    {
        self::assertSame(['status' => 'empty', 'value' => ''], Percent::parse('  '));
        self::assertSame(['status' => 'value', 'value' => '0.00'], Percent::parse('0'));
        self::assertSame(['status' => 'value', 'value' => '10.50'], Percent::parse('10,5'));
        self::assertSame(['status' => 'value', 'value' => '100.00'], Percent::parse('100'));
        self::assertSame('invalid', Percent::parse('-1')['status']);
        self::assertSame('invalid', Percent::parse('100.01')['status']);
        self::assertSame('invalid', Percent::parse('10.555')['status']);
    }

    public function testVariationZeroOrEmptyInheritsParentAndPositiveReplacesIt(): void
    {
        self::assertSame('4.00', Percent::effective('4', '10'));
        self::assertSame('10.00', Percent::effective('', '10'));
        self::assertSame('10.00', Percent::effective('0', '10'));
        self::assertSame('0.00', Percent::effective('0', '0'));
    }

    public function testPixCouponIs81AndCardCouponIs90WhilePersonalizationStays20(): void
    {
        $pix = Quote::productAfterCoupon('100.00', '10', '10', true);
        $card = Quote::productAfterCoupon('100.00', '10', '10', false);

        self::assertSame('81.00', $pix);
        self::assertSame('90.00', $card);
        self::assertNotSame($pix, $card);
        self::assertSame('20.00', Money::normalize('20.00'));
        self::assertSame('101.00', Money::add($pix, '20.00'));
        self::assertSame('110.00', Money::add($card, '20.00'));
    }

    public function testQuantityPriceIsTheDiscountBase(): void
    {
        self::assertSame('64.80', Quote::productAfterCoupon('80.00', '10', '10', true));
        self::assertSame('72.00', Quote::productAfterCoupon('80.00', '10', '0', true));
    }

    public function testHalfUpRoundingUsesMinorUnits(): void
    {
        self::assertSame('9.05', Money::discounted('10.05', '10'));
        self::assertSame('1.00', Money::discountAmount('10.05', '10'));
    }

    public function testGatewaySanitizeKeepsOnlyEnabledIds(): void
    {
        $previous = WC()->gateways;
        WC()->gateways = [
            'woo-mercado-pago-pix' => (object) [
                'id' => 'woo-mercado-pago-pix',
                'enabled' => 'yes',
                'title' => 'Pix',
            ],
            'bacs' => (object) [
                'id' => 'bacs',
                'enabled' => 'no',
                'title' => 'Transferência',
            ],
        ];

        try {
            self::assertSame(
                ['woo-mercado-pago-pix'],
                Settings::sanitizeGateways(['woo-mercado-pago-pix', 'bacs', 'not-a-gateway'])
            );
            self::assertSame([], Settings::sanitizeGateways(['bacs', 'not-a-gateway']));
        } finally {
            WC()->gateways = $previous;
        }
    }

    public function testGatewaySanitizeRejectsIdsWhenNoneAreEnabled(): void
    {
        $previous = WC()->gateways;
        WC()->gateways = [];

        try {
            self::assertSame([], Settings::sanitizeGateways(['woo-mercado-pago-pix', 'not-a-gateway']));
        } finally {
            WC()->gateways = $previous;
        }
    }

    public function testEmptyLabelIsStoredAgainAsNoPix(): void
    {
        self::assertSame('no Pix', Settings::sanitizeLabel('   '));
        self::assertSame('na transferência', Settings::sanitizeLabel(' na transferência '));
    }
}
