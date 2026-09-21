<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Infrastructure;

use Petshop\Core\Personalization\Domain\ProductionSpecification;

defined('ABSPATH') || exit;

/**
 * Reads the administrable personalization configuration stored as product meta.
 */
final class ProductSettings
{
    public const META_ENABLED = '_petshop_personalization_enabled';
    public const META_INSTRUCTION = '_petshop_personalization_instruction';
    public const META_MOCKUP_ID = '_petshop_personalization_mockup_id';
    public const META_MASK_ID = '_petshop_personalization_mask_id';
    public const META_WIDTH_MM = '_petshop_personalization_width_mm';
    public const META_HEIGHT_MM = '_petshop_personalization_height_mm';
    public const META_DPI = '_petshop_personalization_dpi';
    public const META_ALLOW_TEXT = '_petshop_personalization_allow_text';
    public const META_ALLOW_IMAGE = '_petshop_personalization_allow_image';
    public const META_MAX_TEXT_BOXES = '_petshop_personalization_max_text_boxes';
    public const META_FONTS = '_petshop_personalization_fonts';
    public const META_COLORS = '_petshop_personalization_colors';

    public const DEFAULT_FONTS = 'Arial, Georgia, Verdana, Courier New';
    public const DEFAULT_COLORS = '#111111, #ffffff, #17676a, #e2703a';
    public const DEFAULT_DPI = 150;
    public const MAX_TEXT_BOXES = 5;

    /**
     * @param list<string> $fonts
     * @param list<string> $colors
     */
    private function __construct(
        public readonly int $productId,
        public readonly bool $enabled,
        public readonly string $instruction,
        public readonly int $mockupId,
        public readonly int $maskId,
        public readonly float $widthMm,
        public readonly float $heightMm,
        public readonly int $dpi,
        public readonly bool $allowText,
        public readonly bool $allowImage,
        public readonly int $maxTextBoxes,
        public readonly array $fonts,
        public readonly array $colors,
    ) {
    }

    public static function forProduct(int $productId): self
    {
        $fonts = self::listFromCsv((string) get_post_meta($productId, self::META_FONTS, true), self::DEFAULT_FONTS);
        $colors = self::colorsFromCsv((string) get_post_meta($productId, self::META_COLORS, true));

        return new self(
            $productId,
            get_post_meta($productId, self::META_ENABLED, true) === 'yes',
            (string) get_post_meta($productId, self::META_INSTRUCTION, true),
            (int) get_post_meta($productId, self::META_MOCKUP_ID, true),
            (int) get_post_meta($productId, self::META_MASK_ID, true),
            (float) get_post_meta($productId, self::META_WIDTH_MM, true),
            (float) get_post_meta($productId, self::META_HEIGHT_MM, true),
            self::normalizeDpi((int) get_post_meta($productId, self::META_DPI, true)),
            get_post_meta($productId, self::META_ALLOW_TEXT, true) !== 'no',
            get_post_meta($productId, self::META_ALLOW_IMAGE, true) === 'yes',
            self::normalizeTextBoxes((int) get_post_meta($productId, self::META_MAX_TEXT_BOXES, true)),
            $fonts,
            $colors,
        );
    }

    public static function isEnabledFor(int $productId): bool
    {
        return self::forProduct($productId)->isUsable();
    }

    /**
     * A product is only offered to buyers when the physical specification is valid.
     */
    public function isUsable(): bool
    {
        return $this->enabled && $this->specification() instanceof ProductionSpecification;
    }

    public function specification(): ?ProductionSpecification
    {
        try {
            return new ProductionSpecification($this->widthMm, $this->heightMm, $this->dpi);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function mockupUrl(): string
    {
        if ($this->mockupId > 0) {
            $url = wp_get_attachment_image_url($this->mockupId, 'large');
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        $product = wc_get_product($this->productId);
        if ($product instanceof \WC_Product) {
            $imageId = (int) $product->get_image_id();
            if ($imageId > 0) {
                $url = wp_get_attachment_image_url($imageId, 'large');
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }

        return '';
    }

    public function maskUrl(): string
    {
        if ($this->maskId <= 0) {
            return '';
        }

        $url = wp_get_attachment_image_url($this->maskId, 'large');

        return is_string($url) ? $url : '';
    }

    public function mockupAlt(): string
    {
        $id = $this->mockupId;
        if ($id <= 0) {
            return '';
        }

        return (string) get_post_meta($id, '_wp_attachment_image_alt', true);
    }

    /**
     * Immutable configuration frozen into every draft.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        $specification = $this->specification();

        return [
            'schema' => 1,
            'product_id' => $this->productId,
            'instruction' => $this->instruction,
            'mockup_id' => $this->mockupId,
            'mask_id' => $this->maskId,
            'specification' => $specification instanceof ProductionSpecification ? $specification->toArray() : [],
            'allow_text' => $this->allowText,
            'allow_image' => $this->allowImage,
            'max_text_boxes' => $this->maxTextBoxes,
            'fonts' => $this->fonts,
            'colors' => $this->colors,
            'frozen_at' => gmdate('c'),
        ];
    }

    /**
     * Payload consumed by the browser editor.
     *
     * @return array<string, mixed>
     */
    public function toEditorPayload(): array
    {
        $specification = $this->specification();

        return [
            'productId' => $this->productId,
            'instruction' => $this->instruction,
            'mockupUrl' => $this->mockupUrl(),
            'mockupAlt' => $this->mockupAlt(),
            'maskUrl' => $this->maskUrl(),
            'allowText' => $this->allowText,
            'allowImage' => $this->allowImage,
            'maxTextBoxes' => $this->maxTextBoxes,
            'fonts' => $this->fonts,
            'colors' => $this->colors,
            'widthMm' => $this->widthMm,
            'heightMm' => $this->heightMm,
            'dpi' => $this->dpi,
            'widthPx' => $specification instanceof ProductionSpecification ? $specification->widthPx() : 0,
            'heightPx' => $specification instanceof ProductionSpecification ? $specification->heightPx() : 0,
        ];
    }

    public function isFontAllowed(string $font): bool
    {
        return in_array($font, $this->fonts, true);
    }

    public function isColorAllowed(string $color): bool
    {
        return in_array(strtolower($color), array_map('strtolower', $this->colors), true);
    }

    public static function normalizeDpi(int $dpi): int
    {
        if ($dpi < 72 || $dpi > 600) {
            return self::DEFAULT_DPI;
        }

        return $dpi;
    }

    public static function normalizeTextBoxes(int $value): int
    {
        return max(1, min(self::MAX_TEXT_BOXES, $value > 0 ? $value : 1));
    }

    /**
     * @return list<string>
     */
    public static function listFromCsv(string $csv, string $fallback = ''): array
    {
        $source = trim($csv) !== '' ? $csv : $fallback;
        $items = [];
        foreach (explode(',', $source) as $item) {
            $clean = sanitize_text_field(trim($item));
            if ($clean !== '' && !in_array($clean, $items, true)) {
                $items[] = $clean;
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    public static function colorsFromCsv(string $csv): array
    {
        $source = trim($csv) !== '' ? $csv : self::DEFAULT_COLORS;
        $colors = [];
        foreach (explode(',', $source) as $item) {
            $color = sanitize_hex_color(trim($item));
            if (is_string($color) && $color !== '' && !in_array($color, $colors, true)) {
                $colors[] = $color;
            }
        }

        return $colors === [] ? ['#111111'] : $colors;
    }
}
