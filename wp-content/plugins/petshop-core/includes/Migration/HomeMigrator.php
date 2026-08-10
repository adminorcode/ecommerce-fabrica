<?php

declare(strict_types=1);

namespace Petshop\Core\Migration;

use Petshop\Core\Provisioning\StorefrontProvisioner;
use Petshop\Core\Storefront\ProductShortcodes;
use Petshop\Core\Storefront\ProductShowcaseView;
use Petshop\Core\Storefront\SupportContent;

defined('ABSPATH') || exit;

final class HomeMigrator
{
    public const CURRENT_SCHEMA = 25;

    use ManagedHomeMigration;
    use HomeHeroContent;
    use HomeEditorialSchemas;
    use HomeLegacySchemas;

    public static function migrate(int $homeId, string $shopUrl, string $supportUrl, int $heroId): void
    {
        self::migrateManagedHome($homeId, $shopUrl, $supportUrl, $heroId);
    }

    public static function currentSchema(): int
    {
        return self::CURRENT_SCHEMA;
    }

    public static function needsProductGridShortcodeRepair(string $content, string $shopUrl): bool
    {
        return self::hasLegacyProductGridShortcodeBlocks(parse_blocks($content), $shopUrl);
    }

    public static function legacyHeroMarkup(string $shopUrl, int $heroId): string
    {
        return self::legacyHeroContent($shopUrl, $heroId);
    }

    public static function campaignHeroMarkup(string $shopUrl, int $heroId): string
    {
        return self::campaignHeroContent($shopUrl, $heroId);
    }

    public static function heroMarkup(string $shopUrl, int $heroId): string
    {
        return self::heroContent($shopUrl, $heroId);
    }

    public static function benefitsMarkup(array $overrides = []): string
    {
        return self::benefitsContent($overrides);
    }

    /**
     * Registry canônico dos schemas históricos da Home.
     *
     * Schemas 7–9 preservam a lógica especial de assinatura editorial no runner;
     * schemas posteriores apontam para o transformador idempotente correspondente.
     *
     * @return array<int, callable>
     */
    public static function registry(): array
    {
        $identity = static fn (string $content): string => $content;

        return [
            7 => $identity,
            8 => $identity,
            9 => $identity,
            10 => static fn (string $content, string $shopUrl, string $supportUrl): string => self::applyHomeSchemaTen($content, $supportUrl),
            11 => static fn (string $content, string $shopUrl, string $supportUrl): string => self::applyHomeSchemaTen($content, $supportUrl),
            12 => static fn (string $content, string $shopUrl, string $supportUrl): string => self::applyHomeSchemaTen($content, $supportUrl),
            13 => static fn (string $content): string => self::applyHomeSchemaThirteen($content),
            14 => static fn (string $content): string => self::applyHomeSchemaFourteen($content),
            15 => static fn (string $content): string => self::applyHomeSchemaFifteen($content),
            16 => static fn (string $content): string => self::applyHomeSchemaSixteen($content),
            17 => static fn (string $content): string => self::applyHomeSchemaSeventeen($content),
            18 => static fn (string $content): string => self::applyHomeSchemaEighteen($content),
            19 => static fn (string $content, string $shopUrl, string $supportUrl): string => self::applyHomeSchemaNineteen($content, $supportUrl),
            20 => static fn (string $content, string $shopUrl): string => self::applyHomeSchemaTwenty($content, $shopUrl),
            21 => static fn (string $content): string => self::applyHomeSchemaTwentyOne($content),
            22 => static fn (string $content): string => self::applyHomeSchemaTwentyTwo($content),
            23 => static fn (string $content): string => self::applyHomeSchemaTwentyThree($content),
            24 => static fn (string $content, string $shopUrl, string $supportUrl, int $heroId): string => self::applyHomeSchemaTwentyFour($content, $shopUrl, $heroId),
            25 => static fn (string $content, string $shopUrl): string => self::applyHomeSchemaTwentyFive($content, $shopUrl),
        ];
    }

    /**
     * @return array{content: string, applied: list<int>}
     */
    private static function applyRegisteredSchemas(
        string $content,
        int $currentSchema,
        string $shopUrl,
        string $supportUrl,
        int $heroId
    ): array {
        $applied = [];
        foreach (self::registry() as $schema => $transform) {
            if ($schema < 10) {
                continue;
            }
            $requiresRepair = ($schema === 17 && !str_contains($content, '[petshop_product_showcase'))
                || ($schema === 25 && self::needsProductGridShortcodeRepair($content, $shopUrl));
            if ($currentSchema >= $schema && !$requiresRepair) {
                continue;
            }
            $content = $transform($content, $shopUrl, $supportUrl, $heroId);
            $applied[] = $schema;
        }

        return ['content' => $content, 'applied' => $applied];
    }

    private static function ensureSupportBannerAttachment(): int
    {
        return StorefrontProvisioner::supportBannerAttachment();
    }

    private static function resolveSupportBannerUrl(string $fallbackUrl = ''): string
    {
        return SupportContent::resolveSupportBannerUrl($fallbackUrl);
    }

    private static function supportBannerContent(int $imageId, string $url): string
    {
        return SupportContent::supportBannerContent($imageId, $url);
    }

    private static function applyHomeSchemaTwentyTwo(string $content): string
    {
        return SupportContent::applyHomeSchemaTwentyTwo($content);
    }

    private static function applyHomeSchemaTwentyThree(string $content): string
    {
        return SupportContent::applyHomeSchemaTwentyThree($content);
    }

    private static function applyHomeSchemaTwentyFour(string $content, string $shopUrl, int $heroId): string
    {
        return SupportContent::applyHomeSchemaTwentyFour($content, $shopUrl, $heroId);
    }

    private static function showcaseSectionGutenbergMarkup(string $className, string $headingId, string $title, string $ctaLabel, string $ctaUrl, string $intro, string $gridShortcode): string
    {
        return ProductShowcaseView::showcaseSectionGutenbergMarkup($className, $headingId, $title, $ctaLabel, $ctaUrl, $intro, $gridShortcode);
    }

    private static function reviewsSectionGutenbergMarkup(int $limit = 3): string
    {
        return ProductShowcaseView::reviewsSectionGutenbergMarkup($limit);
    }

    private static function resolveShopCtaUrl(string $ctaUrl): string
    {
        return ProductShowcaseView::resolveShopCtaUrl($ctaUrl);
    }

    private static function resolveSeasonalCtaUrl(string $ctaUrl, array $terms): string
    {
        return ProductShowcaseView::resolveSeasonalCtaUrl($ctaUrl, $terms);
    }

    private static function resolveCategoryCtaUrl(string $ctaUrl, string $categorySlugs): string
    {
        return ProductShowcaseView::resolveCategoryCtaUrl($ctaUrl, $categorySlugs);
    }

    private static function hasCatalogSales(): bool
    {
        return ProductShortcodes::hasCatalogSales();
    }
}
