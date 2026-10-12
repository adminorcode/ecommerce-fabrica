<?php

declare(strict_types=1);

namespace Petshop\Core\Cli;

use Petshop\Core\Personalization\Domain\PersonalizationStatus;
use Petshop\Core\Personalization\Infrastructure\Capabilities;
use Petshop\Core\Personalization\Infrastructure\CleanupScheduler;
use Petshop\Core\Personalization\Infrastructure\ImageProcessor;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;
use Petshop\Core\Personalization\Infrastructure\PrivateStorage;
use Petshop\Core\Personalization\Infrastructure\RetentionPolicy;
use Petshop\Core\Personalization\Infrastructure\SchemaMigrator;
use Petshop\Core\Personalization\Migration\PersonalizePageMigrator;

defined('ABSPATH') || exit;

/**
 * `wp petshop personalization <doctor|cleanup|integrity>`
 */
final class PersonalizationCommand
{
    public static function register(): void
    {
        \WP_CLI::add_command('petshop personalization doctor', [self::class, 'doctor']);
        \WP_CLI::add_command('petshop personalization cleanup', [self::class, 'cleanup']);
        \WP_CLI::add_command('petshop personalization integrity', [self::class, 'integrity']);
    }

    /**
     * Reports schema, storage, capabilities, cron and queue health.
     */
    public static function doctor(): void
    {
        $health = PrivateStorage::health();
        $schemaVersion = (int) get_option(SchemaMigrator::OPTION, 0);
        $tablesExist = PersonalizationRepository::tablesExist();
        $counts = $tablesExist ? PersonalizationRepository::countByStatus() : [];
        $cron = wp_next_scheduled(CleanupScheduler::HOOK);

        $rows = [
            ['item' => 'schema_version', 'value' => sprintf('%d (esperado %d)', $schemaVersion, SchemaMigrator::VERSION)],
            ['item' => 'tables', 'value' => $tablesExist ? 'ok' : 'ausentes'],
            ['item' => 'storage_status', 'value' => $health['status']],
            ['item' => 'storage_path', 'value' => $health['path']],
            ['item' => 'storage_writable', 'value' => $health['writable'] ? 'sim' : 'não'],
            ['item' => 'storage_owner', 'value' => self::storageOwnership((string) $health['path'])],
            ['item' => 'gd_extension', 'value' => ImageProcessor::hasGd() ? 'ok' : 'ausente'],
            ['item' => 'zip_extension', 'value' => class_exists('ZipArchive') ? 'ok' : 'ausente'],
            ['item' => 'capability_roles', 'value' => implode(', ', self::rolesWithCapability())],
            ['item' => 'cleanup_cron', 'value' => is_int($cron) ? gmdate('Y-m-d H:i:s', $cron) . ' UTC' : 'não agendado'],
            ['item' => 'retention_draft_days', 'value' => (string) RetentionPolicy::draftDays()],
            ['item' => 'retention_cart_days', 'value' => (string) RetentionPolicy::cartDays()],
            ['item' => 'personalize_page', 'value' => self::personalizePageState()],
        ];

        foreach (PersonalizationStatus::cases() as $status) {
            $rows[] = ['item' => 'queue_' . $status->value, 'value' => (string) ($counts[$status->value] ?? 0)];
        }

        \WP_CLI\Utils\format_items('table', $rows, ['item', 'value']);

        $problems = [];
        if ($schemaVersion < SchemaMigrator::VERSION || !$tablesExist) {
            $problems[] = 'schema desatualizado';
        }
        if ($health['status'] !== PrivateStorage::HEALTH_OK) {
            $problems[] = 'storage privado indisponível: ' . $health['message'];
        }
        if (!ImageProcessor::hasGd()) {
            $problems[] = 'extensão GD ausente (uploads não são reprocessados)';
        }

        if ($problems !== []) {
            \WP_CLI::warning('Pendências: ' . implode('; ', $problems));

            return;
        }

        \WP_CLI::success('Módulo de personalização saudável.');
    }

    /**
     * Lists (and optionally removes) expired drafts and stale cart items.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Apenas lista os candidatos. Padrão quando `--execute` não é informado.
     *
     * [--execute]
     * : Executa a limpeza de fato.
     *
     * [--limit=<limit>]
     * : Máximo de registros avaliados por tipo. Padrão: 200.
     *
     * @param list<string> $args
     * @param array<string, string> $assocArgs
     */
    public static function cleanup(array $args, array $assocArgs = []): void
    {
        unset($args);

        $execute = isset($assocArgs['execute']);
        $limit = isset($assocArgs['limit']) ? max(1, (int) $assocArgs['limit']) : 200;
        $report = CleanupScheduler::run(!$execute, $limit);

        \WP_CLI::log(sprintf('Modo: %s', $report['dry_run'] ? 'dry-run (nada foi excluído)' : 'execução'));
        \WP_CLI::log(sprintf('Corte de rascunhos: %s UTC', $report['draft_cutoff']));
        \WP_CLI::log(sprintf('Corte de carrinhos: %s UTC', $report['cart_cutoff']));
        \WP_CLI::log(sprintf('Corte de cancelados (arquivos): %s UTC', $report['cancelled_cutoff']));
        \WP_CLI::log(sprintf('Corte de concluídos (arquivos): %s UTC', $report['completed_cutoff']));

        self::listCandidates('Rascunhos expirados', $report['expired_drafts']);
        self::listCandidates('Carrinhos abandonados', $report['stale_cart_items']);
        self::listCandidates('Arquivos de cancelados', $report['cancelled_file_candidates']);
        self::listCandidates('Arquivos de concluídos', $report['completed_file_candidates']);

        foreach ($report['errors'] as $error) {
            \WP_CLI::warning($error);
        }

        if ($report['dry_run']) {
            \WP_CLI::success(sprintf(
                'Dry-run concluído: %d rascunho(s), %d carrinho(s), %d cancelado(s) e %d concluído(s) candidatos.',
                count($report['expired_drafts']),
                count($report['stale_cart_items']),
                count($report['cancelled_file_candidates']),
                count($report['completed_file_candidates'])
            ));

            return;
        }

        \WP_CLI::success(sprintf(
            'Limpeza concluída: %d rascunho(s) excluído(s), %d carrinho(s) cancelado(s), %d arquivo(s) removido(s).',
            $report['deleted'],
            $report['cancelled'],
            $report['files_purged']
        ));
    }

    /**
     * Compares database records with the private storage (hashes and presence).
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Máximo de arquivos verificados. Padrão: 500.
     *
     * @param list<string> $args
     * @param array<string, string> $assocArgs
     */
    public static function integrity(array $args, array $assocArgs = []): void
    {
        unset($args);

        $limit = isset($assocArgs['limit']) ? max(1, (int) $assocArgs['limit']) : 500;
        $report = PersonalizationRepository::integrityReport($limit);

        \WP_CLI::log(sprintf('Arquivos verificados: %d', $report['checked']));
        \WP_CLI::log(sprintf('Registros órfãos: %d', $report['orphans']));

        foreach ($report['missing'] as $reference) {
            \WP_CLI::warning('Arquivo ausente no storage: ' . $reference);
        }
        foreach ($report['mismatched'] as $reference) {
            \WP_CLI::warning('Hash divergente: ' . $reference);
        }

        if ($report['missing'] === [] && $report['mismatched'] === [] && $report['orphans'] === 0) {
            \WP_CLI::success('Banco e storage estão consistentes.');

            return;
        }

        \WP_CLI::warning('Inconsistências encontradas; nenhuma exclusão automática foi feita.');
    }

    /**
     * @param list<array{public_id: string, product_id: int, updated_at: string}> $candidates
     */
    private static function listCandidates(string $label, array $candidates): void
    {
        \WP_CLI::log(sprintf('%s: %d', $label, count($candidates)));
        foreach ($candidates as $candidate) {
            \WP_CLI::log(sprintf(
                '  %s · produto #%d · atualizado em %s UTC',
                $candidate['public_id'],
                $candidate['product_id'],
                $candidate['updated_at']
            ));
        }
    }

    /**
     * @return list<string>
     */
    private static function rolesWithCapability(): array
    {
        $roles = [];
        foreach (wp_roles()->roles as $roleName => $definition) {
            unset($definition);
            $role = get_role((string) $roleName);
            if ($role instanceof \WP_Role && $role->has_cap(Capabilities::MANAGE)) {
                $roles[] = (string) $roleName;
            }
        }

        return $roles === [] ? ['nenhum'] : $roles;
    }

    /**
     * The CLI usually runs as root, so a writable check alone hides permission problems that
     * only affect the web user. Reporting owner and mode makes that case visible.
     */
    private static function storageOwnership(string $path): string
    {
        if (!is_dir($path)) {
            return 'inexistente';
        }

        $owner = function_exists('posix_getpwuid') ? posix_getpwuid((int) fileowner($path)) : null;
        $group = function_exists('posix_getgrgid') ? posix_getgrgid((int) filegroup($path)) : null;
        $mode = substr(sprintf('%o', (int) fileperms($path)), -4);

        return sprintf(
            '%s:%s (%s)',
            is_array($owner) ? (string) $owner['name'] : (string) fileowner($path),
            is_array($group) ? (string) $group['name'] : (string) filegroup($path),
            $mode
        );
    }

    private static function personalizePageState(): string
    {
        $page = get_page_by_path(PersonalizePageMigrator::SLUG);
        if (!$page instanceof \WP_Post) {
            return 'ausente';
        }

        $hasBlock = str_contains((string) $page->post_content, 'petshop/personalizable-products');

        return sprintf('#%d %s', (int) $page->ID, $hasBlock ? 'com vitrine' : 'sem vitrine (conteúdo do cliente preservado)');
    }
}
