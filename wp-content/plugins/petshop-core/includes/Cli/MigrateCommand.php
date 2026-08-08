<?php

declare(strict_types=1);

namespace Petshop\Core\Cli;

use Petshop\Core\Lifecycle;
use Petshop\Core\StorefrontExperience;

defined('ABSPATH') || exit;

final class MigrateCommand
{
    public static function register(): void
    {
        \WP_CLI::add_command('petshop migrate', [self::class, 'run']);
    }

    public static function run(): void
    {
        Lifecycle::scheduleMigration();
        StorefrontExperience::maybeEnsureStorefront();

        $errorCode = (string) get_option('petshop_storefront_migration_error', '');
        if ($errorCode !== '') {
            \WP_CLI::error(sprintf('Migração falhou (%s). Consulte o log do WordPress.', $errorCode));
        }

        \WP_CLI::success('Migrações do Petshop Core concluídas.');
    }
}
