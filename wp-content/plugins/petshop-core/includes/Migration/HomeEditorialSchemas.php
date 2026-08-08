<?php

declare(strict_types=1);

namespace Petshop\Core\Migration;

defined('ABSPATH') || exit;

trait HomeEditorialSchemas
{
    private static function applyHomeSchemaThirteen(string $content): string
    {
        if (str_contains($content, 'petshop-benefits__item')) {
            return $content;
        }

        if (!str_contains($content, 'petshop-benefits')) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceBenefitsBlocks($blocks);

        return serialize_blocks($blocks);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private static function replaceBenefitsBlocks(array $blocks): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (
                ($block['blockName'] ?? '') === 'core/group'
                && str_contains((string) ($block['attrs']['className'] ?? ''), 'petshop-benefits')
                && ($block['attrs']['align'] ?? '') === 'full'
            ) {
                $replacement = parse_blocks(trim(self::benefitsContent(self::benefitsOverridesFromBlock($block))));
                foreach ($replacement as $replacementBlock) {
                    if (($replacementBlock['blockName'] ?? '') !== null) {
                        $updated[] = $replacementBlock;
                    }
                }
                continue;
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceBenefitsBlocks($block['innerBlocks']);
            }

            $updated[] = $block;
        }

        return $updated;
    }

    private static function applyHomeSchemaTwentyOne(string $content): string
    {
        if (str_contains($content, 'petshop-reviews-section')) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceLegacyReviewsShortcodeBlocks($blocks);

        return serialize_blocks($blocks);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private static function replaceLegacyReviewsShortcodeBlocks(array $blocks): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/shortcode') {
                $shortcode = trim(strip_tags((string) ($block['innerHTML'] ?? '')));
                if (preg_match('/^\[petshop_reviews_section\b/', $shortcode)) {
                    $attributes = shortcode_parse_atts(
                        (string) preg_replace('/^\[petshop_reviews_section\s*|\]$/', '', $shortcode)
                    ) ?: [];
                    $limit = max(1, absint($attributes['limit'] ?? 3));
                    foreach (parse_blocks(self::reviewsSectionGutenbergMarkup($limit)) as $parsedBlock) {
                        $updated[] = $parsedBlock;
                    }
                    continue;
                }
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceLegacyReviewsShortcodeBlocks($block['innerBlocks']);
            }

            $updated[] = $block;
        }

        return $updated;
    }

    private static function applyHomeSchemaTwenty(string $content, string $shopUrl): string
    {
        if (self::homeUsesEditableShowcaseHeads($content)) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceLegacyShowcaseShortcodeBlocks($blocks, $shopUrl);

        return serialize_blocks($blocks);
    }

    private static function homeUsesEditableShowcaseHeads(string $content): bool
    {
        if (!str_contains($content, 'petshop-section-head')) {
            return false;
        }

        return !preg_match(
            '/\[petshop_(featured_products|kits_section|seasonal_products|product_showcase)(?!_grid)\b/',
            $content
        );
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private static function replaceLegacyShowcaseShortcodeBlocks(array $blocks, string $shopUrl): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/shortcode') {
                $shortcode = trim(strip_tags((string) ($block['innerHTML'] ?? '')));
                $replacement = self::editableShowcaseMarkupFromShortcode($shortcode, $shopUrl);
                if ($replacement !== null) {
                    foreach (parse_blocks($replacement) as $parsedBlock) {
                        $updated[] = $parsedBlock;
                    }
                    continue;
                }
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceLegacyShowcaseShortcodeBlocks(
                    $block['innerBlocks'],
                    $shopUrl
                );
            }

            $updated[] = $block;
        }

        return $updated;
    }

    private static function editableShowcaseMarkupFromShortcode(string $shortcode, string $shopUrl): ?string
    {
        if (preg_match('/^\[petshop_featured_products\b/', $shortcode)) {
            $attributes = shortcode_parse_atts(
                (string) preg_replace('/^\[petshop_featured_products\s*|\]$/', '', $shortcode)
            ) ?: [];
            $fallbackTitle = trim((string) ($attributes['fallback_title'] ?? ''));
            if ($fallbackTitle === '') {
                $fallbackTitle = (string) get_theme_mod(
                    'petshop_featured_section_title',
                    \Petshop\Core\Settings\DefaultSettings::get('petshop_featured_section_title')
                );
            }
            $title = self::hasCatalogSales()
                ? __('Mais vendidos', 'petshop-core')
                : $fallbackTitle;
            $cta = (string) ($attributes['cta'] ?? __('Ver todos →', 'petshop-core'));
            $ctaUrl = self::resolveShopCtaUrl((string) ($attributes['cta_url'] ?? ''));
            $limit = max(1, absint($attributes['limit'] ?? 4));
            $columns = max(1, absint($attributes['columns'] ?? 4));

            return self::showcaseSectionGutenbergMarkup(
                'petshop-featured-section',
                'petshop-featured-heading',
                $title,
                $cta,
                $ctaUrl,
                '',
                sprintf('[petshop_featured_products_grid limit="%d" columns="%d"]', $limit, $columns)
            );
        }

        if (preg_match('/^\[petshop_kits_section\b/', $shortcode)) {
            $attributes = shortcode_parse_atts(
                (string) preg_replace('/^\[petshop_kits_section\s*|\]$/', '', $shortcode)
            ) ?: [];
            $category = (string) ($attributes['category'] ?? 'conjuntos');
            $term = get_term_by('slug', $category, 'product_cat');
            if (!$term instanceof \WP_Term || (int) $term->count <= 0) {
                return null;
            }
            $ctaUrl = get_term_link($term);
            if (is_wp_error($ctaUrl)) {
                $ctaUrl = $shopUrl;
            }
            $limit = max(1, absint($attributes['limit'] ?? 4));
            $columns = max(1, absint($attributes['columns'] ?? 4));

            return self::showcaseSectionGutenbergMarkup(
                'petshop-kits-section petshop-section--soft',
                'petshop-kits-heading',
                (string) ($attributes['title'] ?? __('Economize comprando kits', 'petshop-core')),
                (string) ($attributes['cta'] ?? __('Ver todos →', 'petshop-core')),
                (string) $ctaUrl,
                (string) ($attributes['intro'] ?? __(
                    'Escolhas práticas para compor o visual e facilitar a rotina profissional.',
                    'petshop-core'
                )),
                sprintf(
                    '[petshop_kits_section_grid limit="%d" columns="%d" category="%s"]',
                    $limit,
                    $columns,
                    esc_attr($category)
                )
            );
        }

        if (preg_match('/^\[petshop_seasonal_products\b/', $shortcode)) {
            $attributes = shortcode_parse_atts(
                (string) preg_replace('/^\[petshop_seasonal_products\s*|\]$/', '', $shortcode)
            ) ?: [];
            $terms = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => true,
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => 'petshop_seasonal', 'value' => '1'],
                    ['key' => 'petshop_visible_in_menu', 'value' => '1'],
                ],
            ]);
            if (is_wp_error($terms) || $terms === []) {
                return null;
            }
            $limit = max(1, absint($attributes['limit'] ?? 4));
            $columns = max(1, absint($attributes['columns'] ?? 4));

            return self::showcaseSectionGutenbergMarkup(
                'petshop-seasonal-section',
                'petshop-seasonal-heading',
                (string) ($attributes['title'] ?? __('Coleção da estação', 'petshop-core')),
                (string) ($attributes['cta'] ?? __('Ver todos →', 'petshop-core')),
                self::resolveSeasonalCtaUrl((string) ($attributes['cta_url'] ?? ''), $terms),
                '',
                sprintf('[petshop_seasonal_products_grid limit="%d" columns="%d"]', $limit, $columns)
            );
        }

        if (preg_match('/^\[petshop_product_showcase\b/', $shortcode)) {
            $attributes = shortcode_parse_atts(
                (string) preg_replace('/^\[petshop_product_showcase\s*|\]$/', '', $shortcode)
            ) ?: [];
            $title = trim((string) ($attributes['title'] ?? ''));
            if ($title === '') {
                return null;
            }
            $category = trim((string) ($attributes['category'] ?? ''));
            $limit = max(1, absint($attributes['limit'] ?? 4));
            $columns = max(1, absint($attributes['columns'] ?? 4));
            $orderby = esc_attr((string) ($attributes['orderby'] ?? 'date'));
            $order = esc_attr((string) ($attributes['order'] ?? 'DESC'));
            $gridShortcode = sprintf(
                '[petshop_product_showcase_grid limit="%d" columns="%d" category="%s" orderby="%s" order="%s"]',
                $limit,
                $columns,
                esc_attr($category),
                $orderby,
                $order
            );

            return self::showcaseSectionGutenbergMarkup(
                (string) ($attributes['class'] ?? 'petshop-professional-section'),
                sanitize_title($title) . '-heading',
                $title,
                (string) ($attributes['cta'] ?? __('Ver todos →', 'petshop-core')),
                self::resolveCategoryCtaUrl((string) ($attributes['cta_url'] ?? ''), $category),
                (string) ($attributes['intro'] ?? ''),
                $gridShortcode
            );
        }

        return null;
    }

    private static function applyHomeSchemaNineteen(string $content, string $supportUrl): string
    {
        if (
            str_contains($content, 'petshop-support-banner')
            && str_contains($content, 'wp-block-image')
            && !str_contains($content, '[petshop_support_banner]')
        ) {
            return $content;
        }

        $imageId = (int) get_theme_mod('petshop_support_banner_image', 0);
        if ($imageId <= 0) {
            $imageId = self::ensureSupportBannerAttachment();
        }

        $url = self::resolveSupportBannerUrl($supportUrl);
        $banner = self::supportBannerContent($imageId, $url);
        if ($banner === '') {
            return $content;
        }

        $replacementBlocks = parse_blocks($banner);
        if ($replacementBlocks === []) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceEditableSupportBannerBlocks($blocks, $replacementBlocks[0]);

        return serialize_blocks($blocks);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<string, mixed> $replacement
     * @return array<int, array<string, mixed>>
     */
    private static function replaceEditableSupportBannerBlocks(array $blocks, array $replacement): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/shortcode') {
                $shortcode = trim(strip_tags((string) ($block['innerHTML'] ?? '')));
                if ($shortcode === '[petshop_support_banner]') {
                    $updated[] = $replacement;
                    continue;
                }
            }

            if (($block['blockName'] ?? '') === 'core/group') {
                $className = (string) ($block['attrs']['className'] ?? '');
                if (
                    str_contains($className, 'petshop-support-cta')
                    || (
                        str_contains($className, 'petshop-support-banner')
                        && !self::blockContainsImage($block)
                    )
                ) {
                    $updated[] = $replacement;
                    continue;
                }
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceEditableSupportBannerBlocks($block['innerBlocks'], $replacement);
            }

            $updated[] = $block;
        }

        return $updated;
    }
}
