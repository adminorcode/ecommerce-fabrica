<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

final class Lifecycle
{
    public const SCHEDULED_OPTION = 'petshop_storefront_migration_scheduled';

    public static function activate(): void
    {
        self::scheduleMigration();
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void
    {
        delete_option('petshop_storefront_migration_lock');
        delete_option(self::SCHEDULED_OPTION);
        flush_rewrite_rules(false);
    }

    public static function scheduleMigration(): void
    {
        update_option(self::SCHEDULED_OPTION, time(), false);
    }
}
