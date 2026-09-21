<?php

declare(strict_types=1);

use Petshop\Core\Settings\DefaultSettings;
use Petshop\Core\WooCommerce\OrderReceivedMessage;
use PHPUnit\Framework\TestCase;

final class OrderReceivedMessageTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['petshop_test_theme_mods'] = [];
        $GLOBALS['petshop_test_filters'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['petshop_test_theme_mods'] = [];
        $GLOBALS['petshop_test_filters'] = [];
    }

    public function testSanitizeRestoresTheProvisionedPhraseWhenEmpty(): void
    {
        self::assertSame(
            'Parabéns! Seu pedido foi recebido!',
            OrderReceivedMessage::sanitize('   ')
        );
        self::assertSame(
            'Pedido confirmado com sucesso',
            OrderReceivedMessage::sanitize(' Pedido confirmado com sucesso ')
        );
    }

    public function testTextFallsBackToDefaultWhenThemeModIsEmpty(): void
    {
        $GLOBALS['petshop_test_theme_mods'][OrderReceivedMessage::SETTING] = '';

        self::assertSame('Parabéns! Seu pedido foi recebido!', OrderReceivedMessage::text());
    }

    public function testTextUsesTheStoredCustomizerValue(): void
    {
        $GLOBALS['petshop_test_theme_mods'][OrderReceivedMessage::SETTING] = 'Frase editada no painel!';

        self::assertSame('Frase editada no painel!', OrderReceivedMessage::text());
    }

    public function testFilterReplacesNativeReceivedCopyAndLeavesOtherStatuses(): void
    {
        $expected = 'Parabéns! Seu pedido foi recebido!';

        self::assertSame(
            $expected,
            OrderReceivedMessage::filterReceivedText('Thank you. Your order has been received.')
        );
        self::assertSame(
            $expected,
            OrderReceivedMessage::filterReceivedText('Obrigado. Seu pedido foi recebido.')
        );
        self::assertSame(
            'Your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.',
            OrderReceivedMessage::filterReceivedText(
                'Your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.'
            )
        );
        self::assertSame(
            'Your order has been cancelled.',
            OrderReceivedMessage::filterReceivedText('Your order has been cancelled.')
        );
    }

    public function testBootstrapRegistersTheOfficialWooCommerceFilter(): void
    {
        OrderReceivedMessage::bootstrap();

        $hooks = $GLOBALS['petshop_test_filters']['woocommerce_thankyou_order_received_text'] ?? [];
        self::assertNotSame([], $hooks);
        self::assertSame(20, $hooks[0]['priority']);
        self::assertSame(2, $hooks[0]['accepted_args']);
        self::assertSame([OrderReceivedMessage::class, 'filterReceivedText'], $hooks[0]['callback']);
        self::assertSame(
            'Petshop\\Core\\WooCommerce\\OrderReceivedMessage::sanitize',
            DefaultSettings::definitions()[OrderReceivedMessage::SETTING]['sanitize']
        );
    }
}
