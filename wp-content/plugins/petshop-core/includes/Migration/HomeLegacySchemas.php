<?php

declare(strict_types=1);

namespace Petshop\Core\Migration;

use Petshop\Core\HomeCampaignBlocks;

defined('ABSPATH') || exit;

trait HomeLegacySchemas
{
    private static function blockContainsImage(array $block): bool
    {
        if (($block['blockName'] ?? '') === 'core/image') {
            return true;
        }

        foreach ($block['innerBlocks'] ?? [] as $innerBlock) {
            if (is_array($innerBlock) && self::blockContainsImage($innerBlock)) {
                return true;
            }
        }

        return false;
    }

    private static function applyHomeSchemaEighteen(string $content): string
    {
        if (str_contains($content, '[petshop_support_banner]')) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceSupportBannerBlock($blocks);

        return serialize_blocks($blocks);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private static function replaceSupportBannerBlock(array $blocks): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/group') {
                $className = (string) ($block['attrs']['className'] ?? '');
                if (str_contains($className, 'petshop-support-cta')) {
                    $updated[] = self::shortcodeBlock('[petshop_support_banner]');
                    continue;
                }
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceSupportBannerBlock($block['innerBlocks']);
            }

            $updated[] = $block;
        }

        return $updated;
    }

    private static function applyHomeSchemaSeventeen(string $content): string
    {
        if (str_contains($content, '[petshop_product_showcase')) {
            return $content;
        }

        $content = (string) preg_replace_callback(
            '/\[petshop_featured_products(?!\s[^\]]*cta=)([^\]]*)\]/',
            static fn (array $matches): string => '[petshop_featured_products' . $matches[1] . ' cta="Ver todos →"]',
            $content
        );
        $content = (string) preg_replace_callback(
            '/\[petshop_kits_section(?!\s[^\]]*cta=)([^\]]*)\]/',
            static fn (array $matches): string => '[petshop_kits_section' . $matches[1] . ' cta="Ver todos →"]',
            $content
        );

        $blocks = parse_blocks($content);
        $blocks = self::replaceHomeShowcaseBlocks($blocks);

        return serialize_blocks($blocks);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private static function replaceHomeShowcaseBlocks(array $blocks): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/group') {
                $className = (string) ($block['attrs']['className'] ?? '');
                if (str_contains($className, 'petshop-seasonal')) {
                    $updated[] = self::shortcodeBlock(
                        '[petshop_seasonal_products limit="4" columns="4" title="Coleção da estação" cta="Ver todos →"]'
                    );
                    continue;
                }
                if (str_contains($className, 'petshop-professional')) {
                    $updated[] = self::shortcodeBlock(
                        '[petshop_product_showcase limit="4" columns="4" title="Seleção para banho e tosa" intro="Modelos pensados para finalização profissional, apresentação de kits e recompra recorrente." cta="Ver todos →" category="adesivos,gravatas,lacos" orderby="date"]'
                    );
                    continue;
                }
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceHomeShowcaseBlocks($block['innerBlocks']);
            }

            $updated[] = $block;
        }

        return $updated;
    }

    private static function shortcodeBlock(string $shortcode): array
    {
        return [
            'blockName' => 'core/shortcode',
            'attrs' => [],
            'innerBlocks' => [],
            'innerHTML' => $shortcode,
            'innerContent' => [$shortcode],
        ];
    }

    private static function applyHomeSchemaSixteen(string $content): string
    {
        if (!str_contains($content, 'petshop-benefits__item') || str_contains($content, 'petshop-benefits__content')) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceBenefitsBlocks($blocks);

        return serialize_blocks($blocks);
    }

    private static function applyHomeSchemaFifteen(string $content): string
    {
        if (!str_contains($content, 'petshop-benefits')) {
            return $content;
        }

        return str_replace(
            '<strong>Envio para todo o Brasil</strong>',
            '<strong>Frete para todo o Brasil</strong>',
            $content
        );
    }

    private static function applyHomeSchemaFourteen(string $content): string
    {
        if (!str_contains($content, 'petshop-benefits__item') || str_contains($content, 'petshop-benefits__copy')) {
            return $content;
        }

        $blocks = parse_blocks($content);
        $blocks = self::replaceBenefitsBlocks($blocks);

        return serialize_blocks($blocks);
    }

    /**
     * @param array<string, mixed> $benefitsBlock
     * @return list<array{title: string, detail?: string}>
     */
    private static function benefitsOverridesFromBlock(array $benefitsBlock): array
    {
        if (str_contains((string) ($benefitsBlock['attrs']['className'] ?? ''), 'petshop-benefits__item')) {
            return self::benefitItemOverrides($benefitsBlock);
        }

        foreach ($benefitsBlock['innerBlocks'] ?? [] as $innerBlock) {
            if (str_contains((string) ($innerBlock['attrs']['className'] ?? ''), 'petshop-benefits__inner')) {
                return self::benefitItemOverrides($innerBlock);
            }
        }

        return self::legacyBenefitOverrides($benefitsBlock);
    }

    /**
     * @param array<string, mixed> $containerBlock
     * @return list<array{title: string, detail?: string}>
     */
    private static function benefitItemOverrides(array $containerBlock): array
    {
        $overrides = [];

        foreach ($containerBlock['innerBlocks'] ?? [] as $block) {
            if (($block['blockName'] ?? '') !== 'core/group') {
                continue;
            }
            if (!str_contains((string) ($block['attrs']['className'] ?? ''), 'petshop-benefits__item')) {
                continue;
            }

            $title = '';
            $detail = '';
            $collect = static function (array $blocks) use (&$collect, &$title, &$detail): void {
                foreach ($blocks as $inner) {
                    $className = (string) ($inner['attrs']['className'] ?? '');
                    if (($inner['blockName'] ?? '') === 'core/paragraph' && str_contains($className, 'petshop-benefits__title')) {
                        $title = trim(wp_strip_all_tags((string) ($inner['innerHTML'] ?? '')));
                    }
                    if (($inner['blockName'] ?? '') === 'core/paragraph' && str_contains($className, 'petshop-benefits__detail')) {
                        $detail = trim(wp_strip_all_tags((string) ($inner['innerHTML'] ?? '')));
                    }
                    if (!empty($inner['innerBlocks'])) {
                        $collect($inner['innerBlocks']);
                    }
                }
            };
            $collect($block['innerBlocks'] ?? []);

            if ($title !== '') {
                $override = ['title' => $title];
                if ($detail !== '') {
                    $override['detail'] = $detail;
                }
                $overrides[] = $override;
            }
        }

        return $overrides;
    }

    /**
     * @param array<string, mixed> $benefitsBlock
     * @return list<array{title: string, detail?: string}>
     */
    private static function legacyBenefitOverrides(array $benefitsBlock): array
    {
        $lines = [];
        $collect = static function (array $blocks) use (&$collect, &$lines): void {
            foreach ($blocks as $block) {
                if (($block['blockName'] ?? '') === 'core/paragraph') {
                    $text = trim(wp_strip_all_tags((string) ($block['innerHTML'] ?? '')));
                    if ($text !== '') {
                        $lines[] = $text;
                    }
                }
                if (!empty($block['innerBlocks'])) {
                    $collect($block['innerBlocks']);
                }
            }
        };
        $collect($benefitsBlock['innerBlocks'] ?? []);

        $overrides = [];
        foreach (array_slice($lines, 0, 3) as $line) {
            $overrides[] = ['title' => $line];
        }

        return $overrides;
    }

    private static function applyHomeSchemaTen(string $content, string $supportUrl): string
    {
        $heroEnd = strpos($content, '<!-- /wp:cover -->');
        if ($heroEnd === false) {
            return $content;
        }

        $heroEnd += strlen('<!-- /wp:cover -->');

        $benefitOverrides = self::benefitsOverridesFromContent($content);

        return trim(substr($content, 0, $heroEnd))
            . "\n"
            . self::benefitsContent($benefitOverrides)
            . "\n"
            . self::managedHomeTail($supportUrl);
    }

    /**
     * @return list<array{title: string, detail?: string}>
     */
    private static function benefitsOverridesFromContent(string $content): array
    {
        $find = static function (array $blocks) use (&$find): ?array {
            foreach ($blocks as $block) {
                if (
                    ($block['blockName'] ?? '') === 'core/group'
                    && str_contains((string) ($block['attrs']['className'] ?? ''), 'petshop-benefits')
                ) {
                    return $block;
                }

                if (!empty($block['innerBlocks'])) {
                    $found = $find($block['innerBlocks']);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }

            return null;
        };

        $benefitsBlock = $find(parse_blocks($content));

        return $benefitsBlock === null ? [] : self::benefitsOverridesFromBlock($benefitsBlock);
    }

    private static function managedHomeTail(string $supportUrl): string
    {
        $shopUrl = self::resolveShopCtaUrl('');
        $featuredTitle = self::hasCatalogSales()
            ? __('Mais vendidos', 'petshop-core')
            : (string) get_theme_mod(
                'petshop_featured_section_title',
                \Petshop\Core\Settings\DefaultSettings::get('petshop_featured_section_title')
            );
        $kitsTerm = get_term_by('slug', 'conjuntos', 'product_cat');
        $kitsUrl = $kitsTerm instanceof \WP_Term ? get_term_link($kitsTerm) : $shopUrl;
        if (is_wp_error($kitsUrl)) {
            $kitsUrl = $shopUrl;
        }
        $seasonalTerms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'petshop_seasonal', 'value' => '1'],
                ['key' => 'petshop_visible_in_menu', 'value' => '1'],
            ],
        ]);
        $seasonalTerms = is_wp_error($seasonalTerms) ? [] : $seasonalTerms;
        $seasonalUrl = self::resolveSeasonalCtaUrl('', $seasonalTerms);
        $professionalUrl = self::resolveCategoryCtaUrl('', 'adesivos,gravatas,lacos');
        $imageId = self::ensureSupportBannerAttachment();
        $banner = self::supportBannerContent($imageId, self::resolveSupportBannerUrl($supportUrl));

        $featured = self::showcaseSectionGutenbergMarkup(
            'petshop-featured-section',
            'petshop-featured-heading',
            $featuredTitle,
            __('Ver todos →', 'petshop-core'),
            $shopUrl,
            '',
            '[petshop_featured_products_grid limit="4" columns="4"]'
        );
        $kits = self::showcaseSectionGutenbergMarkup(
            'petshop-kits-section petshop-section--soft',
            'petshop-kits-heading',
            __('Economize comprando kits', 'petshop-core'),
            __('Ver todos →', 'petshop-core'),
            (string) $kitsUrl,
            __('Escolhas práticas para compor o visual e facilitar a rotina profissional.', 'petshop-core'),
            '[petshop_kits_section_grid limit="4" columns="4" category="conjuntos"]'
        );
        $seasonal = self::showcaseSectionGutenbergMarkup(
            'petshop-seasonal-section',
            'petshop-seasonal-heading',
            __('Coleção da estação', 'petshop-core'),
            __('Ver todos →', 'petshop-core'),
            $seasonalUrl,
            '',
            '[petshop_seasonal_products_grid limit="4" columns="4"]'
        );
        $professional = self::showcaseSectionGutenbergMarkup(
            'petshop-professional-section',
            'selecao-para-banho-e-tosa-heading',
            __('Seleção para banho e tosa', 'petshop-core'),
            __('Ver todos →', 'petshop-core'),
            $professionalUrl,
            __(
                'Modelos pensados para finalização profissional, apresentação de kits e recompra recorrente.',
                'petshop-core'
            ),
            '[petshop_product_showcase_grid limit="4" columns="4" category="adesivos,gravatas,lacos" orderby="date"]'
        );
        $reviews = self::reviewsSectionGutenbergMarkup(3);

        return <<<BLOCKS
<!-- wp:group {"className":"petshop-section","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section">
<!-- wp:heading --><h2 class="wp-block-heading">Compre por categoria</h2><!-- /wp:heading -->
<!-- wp:shortcode -->[petshop_categories limit="8"]<!-- /wp:shortcode --></div><!-- /wp:group -->
{$featured}
{$kits}
{$seasonal}
{$professional}
{$reviews}
{$banner}
BLOCKS;
    }

    private static function homeContent(string $shopUrl, string $supportUrl, int $heroId): string
    {
        $campaigns = HomeCampaignBlocks::initialCampaignsBlockMarkup($heroId, $shopUrl);

        return self::heroContent($shopUrl, $heroId)
            . "\n"
            . self::benefitsContent()
            . ($campaigns !== '' ? "\n" . $campaigns : '')
            . "\n"
            . self::managedHomeTail($supportUrl);
    }
}
