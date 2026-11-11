<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Infrastructure;

defined('ABSPATH') || exit;

/**
 * Administrable retention and upload limits (defaults from docs/012-sessoes-00-decisoes.md).
 */
final class RetentionPolicy
{
    public const OPTION_DRAFT_DAYS = 'petshop_personalization_retention_draft_days';
    public const OPTION_CART_DAYS = 'petshop_personalization_retention_cart_days';
    public const OPTION_CANCELLED_DAYS = 'petshop_personalization_retention_cancelled_days';
    public const OPTION_COMPLETED_DAYS = 'petshop_personalization_retention_completed_days';
    public const OPTION_MAX_UPLOAD_MB = 'petshop_personalization_max_upload_mb';
    public const OPTION_MAX_MEGAPIXELS = 'petshop_personalization_max_megapixels';

    public const DEFAULT_DRAFT_DAYS = 7;
    public const DEFAULT_CART_DAYS = 14;
    public const DEFAULT_CANCELLED_DAYS = 90;
    public const DEFAULT_COMPLETED_DAYS = 365;
    public const DEFAULT_MAX_UPLOAD_MB = 8;
    public const DEFAULT_MAX_MEGAPIXELS = 40;

    public static function draftDays(): int
    {
        return self::days(self::OPTION_DRAFT_DAYS, self::DEFAULT_DRAFT_DAYS);
    }

    public static function cartDays(): int
    {
        return self::days(self::OPTION_CART_DAYS, self::DEFAULT_CART_DAYS);
    }

    public static function cancelledDays(): int
    {
        return self::days(self::OPTION_CANCELLED_DAYS, self::DEFAULT_CANCELLED_DAYS);
    }

    public static function completedDays(): int
    {
        return self::days(self::OPTION_COMPLETED_DAYS, self::DEFAULT_COMPLETED_DAYS);
    }

    public static function maxUploadBytes(): int
    {
        $megabytes = (int) get_option(self::OPTION_MAX_UPLOAD_MB, self::DEFAULT_MAX_UPLOAD_MB);
        $megabytes = max(1, min(32, $megabytes));

        return $megabytes * 1024 * 1024;
    }

    public static function maxMegapixels(): int
    {
        $value = (int) get_option(self::OPTION_MAX_MEGAPIXELS, self::DEFAULT_MAX_MEGAPIXELS);

        return max(4, min(200, $value));
    }

    public static function draftExpiresAt(): string
    {
        return gmdate('Y-m-d H:i:s', time() + self::draftDays() * DAY_IN_SECONDS);
    }

    public static function draftCutoffGmt(): string
    {
        return gmdate('Y-m-d H:i:s', time() - self::draftDays() * DAY_IN_SECONDS);
    }

    public static function cartCutoffGmt(): string
    {
        return gmdate('Y-m-d H:i:s', time() - self::cartDays() * DAY_IN_SECONDS);
    }

    public static function cancelledCutoffGmt(): string
    {
        return gmdate('Y-m-d H:i:s', time() - self::cancelledDays() * DAY_IN_SECONDS);
    }

    public static function completedCutoffGmt(): string
    {
        return gmdate('Y-m-d H:i:s', time() - self::completedDays() * DAY_IN_SECONDS);
    }

    private static function days(string $option, int $default): int
    {
        $value = (int) get_option($option, $default);

        return max(1, min(3650, $value));
    }
}
