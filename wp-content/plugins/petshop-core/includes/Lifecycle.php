<?php

declare(strict_types=1);

namespace Petshop\Core;

use Petshop\Core\Personalization\PersonalizationModule;

defined('ABSPATH') || exit;

final class Lifecycle
{
    public const SCHEDULED_OPTION = 'petshop_storefront_migration_scheduled';

    public static function activate(): void
    {
        self::scheduleMigration();
        self::activatePersonalization();
        flush_rewrite_rules(false);
    }

    /**
     * Deactivation never removes personalizations, files or capabilities.
     */
    public static function deactivate(): void
    {
        delete_option('petshop_storefront_migration_lock');
        delete_option(self::SCHEDULED_OPTION);
        \Petshop\Core\Personalization\Infrastructure\CleanupScheduler::unschedule();
        flush_rewrite_rules(false);
    }

    public static function scheduleMigration(): void
    {
        update_option(self::SCHEDULED_OPTION, time(), false);
    }

    private static function activatePersonalization(): void
    {
        try {
            PersonalizationModule::activate();
        } catch (\Throwable $error) {
            error_log('Petshop personalization: ativação parcial (' . $error->getMessage() . ').');
        }
    }
}
