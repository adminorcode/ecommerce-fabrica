<?php

declare(strict_types=1);

use Petshop\Core\Personalization\Domain\ProductionSpecification;
use PHPUnit\Framework\TestCase;

final class ProductionSpecificationTest extends TestCase
{
    public function testBandanaPixelsMatchSessionZeroSpec(): void
    {
        $spec = new ProductionSpecification(280.0, 280.0, 150);
        self::assertSame(1654, $spec->widthPx());
        self::assertSame(1654, $spec->heightPx());
    }

    public function testLacoPixelsMatchSessionZeroSpec(): void
    {
        $spec = new ProductionSpecification(80.0, 50.0, 150);
        self::assertSame(472, $spec->widthPx());
        self::assertSame(295, $spec->heightPx());
    }

    public function testAdesivoPixelsMatchSessionZeroSpec(): void
    {
        $spec = new ProductionSpecification(100.0, 100.0, 300);
        self::assertSame(1181, $spec->widthPx());
        self::assertSame(1181, $spec->heightPx());
    }

    public function testRejectsInvalidPhysicalDimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProductionSpecification(0, 100, 150);
    }

    public function testRejectsDpiOutsideRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProductionSpecification(100, 100, 50);
    }
}
