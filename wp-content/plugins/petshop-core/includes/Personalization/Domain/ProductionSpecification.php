<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Domain;

defined('ABSPATH') || exit;

/**
 * Physical print area for a personalizable product (mm + DPI).
 */
final class ProductionSpecification
{
    public function __construct(
        public readonly float $widthMm,
        public readonly float $heightMm,
        public readonly int $dpi,
    ) {
        if ($widthMm <= 0 || $heightMm <= 0) {
            throw new \InvalidArgumentException('Dimensões físicas devem ser positivas.');
        }
        if ($dpi < 72 || $dpi > 600) {
            throw new \InvalidArgumentException('DPI fora do intervalo suportado (72–600).');
        }
    }

    public function widthPx(): int
    {
        return (int) max(1, (int) round($this->widthMm / 25.4 * $this->dpi));
    }

    public function heightPx(): int
    {
        return (int) max(1, (int) round($this->heightMm / 25.4 * $this->dpi));
    }

    public function megapixels(): float
    {
        return ($this->widthPx() * $this->heightPx()) / 1_000_000;
    }

    /**
     * @return array{width_mm: float, height_mm: float, dpi: int, width_px: int, height_px: int}
     */
    public function toArray(): array
    {
        return [
            'width_mm' => $this->widthMm,
            'height_mm' => $this->heightMm,
            'dpi' => $this->dpi,
            'width_px' => $this->widthPx(),
            'height_px' => $this->heightPx(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (float) ($data['width_mm'] ?? 0),
            (float) ($data['height_mm'] ?? 0),
            (int) ($data['dpi'] ?? 0),
        );
    }
}
