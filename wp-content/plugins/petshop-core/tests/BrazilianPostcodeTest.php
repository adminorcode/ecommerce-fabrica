<?php

declare(strict_types=1);

use Petshop\Core\WooCommerce\BrazilianPostcode;
use Petshop\Core\WooCommerce\ShippingQuoteDestination;
use Petshop\Core\WooCommerce\ShippingQuotes;
use PHPUnit\Framework\TestCase;

final class BrazilianPostcodeTest extends TestCase
{
    public function testStateFromPostcodeCoversEachRange(): void
    {
        $samples = [
            ['01000-000', 'SP'],
            ['01310100', 'SP'],
            ['20040020', 'RJ'],
            ['29010000', 'ES'],
            ['30130000', 'MG'],
            ['40020000', 'BA'],
            ['49010000', 'SE'],
            ['50010000', 'PE'],
            ['57020000', 'AL'],
            ['58010000', 'PB'],
            ['59010000', 'RN'],
            ['60010000', 'CE'],
            ['64001000', 'PI'],
            ['65010000', 'MA'],
            ['66010000', 'PA'],
            ['68900000', 'AP'],
            ['69010000', 'AM'],
            ['69301000', 'RR'],
            ['69400000', 'AM'],
            ['69900000', 'AC'],
            ['70040020', 'DF'],
            ['72800000', 'GO'],
            ['73000000', 'DF'],
            ['73700000', 'GO'],
            ['76801000', 'RO'],
            ['77001000', 'TO'],
            ['78005000', 'MT'],
            ['79002000', 'MS'],
            ['80010000', 'PR'],
            ['88010000', 'SC'],
            ['94010450', 'RS'],
        ];

        foreach ($samples as [$postcode, $state]) {
            self::assertSame($state, BrazilianPostcode::stateFromPostcode($postcode), $postcode);
        }
    }

    public function testStateFromPostcodeRejectsIncompleteOrUnknownCodes(): void
    {
        self::assertSame('', BrazilianPostcode::stateFromPostcode(''));
        self::assertSame('', BrazilianPostcode::stateFromPostcode('0131010'));
        self::assertSame('', BrazilianPostcode::stateFromPostcode('00000-000'));
        self::assertSame('', BrazilianPostcode::stateFromPostcode('abc'));
    }

    public function testCepOnlyQuoteBuildsOnlyTheSupportedDestinationFields(): void
    {
        self::assertSame(
            ['country' => 'BR', 'state' => 'SP', 'postcode' => '01310100'],
            ShippingQuoteDestination::forPostcode('01310-100')
        );
        self::assertSame(
            ['country' => '', 'state' => '', 'postcode' => ''],
            ShippingQuoteDestination::forPostcode('00000-000')
        );
    }

    public function testPdpDestinationCarriesTheUfAndNotAStoredCity(): void
    {
        $saoPaulo = ShippingQuotes::destinationFor('01310-100');
        $rio = ShippingQuotes::destinationFor('20040020');
        $unknown = ShippingQuotes::destinationFor('00000-000');

        self::assertSame('SP', $saoPaulo['state']);
        self::assertSame('RJ', $rio['state']);
        self::assertSame('', $unknown['state']);
        self::assertSame('', $saoPaulo['city']);
        self::assertSame('', $unknown['city']);
        self::assertSame('', $saoPaulo['address']);
        self::assertSame('', $saoPaulo['address_2']);
        self::assertSame('BR', $saoPaulo['country']);
        self::assertNotSame($saoPaulo['state'], $rio['state']);
    }
}
