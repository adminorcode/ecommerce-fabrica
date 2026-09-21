<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validação com WP-CLI e WooCommerce ativo.');
}

use Petshop\Core\Personalization\Domain\PersonalizationStatus;
use Petshop\Core\Personalization\Domain\ProductionSpecification;
use Petshop\Core\Personalization\Infrastructure\Capabilities;
use Petshop\Core\Personalization\Infrastructure\CleanupScheduler;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;
use Petshop\Core\Personalization\Infrastructure\PrivateStorage;
use Petshop\Core\Personalization\Infrastructure\ProductSettings;
use Petshop\Core\Personalization\Infrastructure\RetentionPolicy;
use Petshop\Core\Personalization\Infrastructure\SchemaMigrator;
use Petshop\Core\Personalization\Migration\PersonalizePageMigrator;
use Petshop\Core\Personalization\PersonalizationModule;

$failures = [];

PersonalizationModule::maybeMigrate();

$health = PrivateStorage::health();
if ($health['status'] !== PrivateStorage::HEALTH_OK) {
    $failures[] = 'Storage privado não saudável: ' . $health['message'];
}

if ((int) get_option(SchemaMigrator::OPTION, 0) < SchemaMigrator::VERSION) {
    $failures[] = 'Schema de personalização desatualizado.';
}

if (!PersonalizationRepository::tablesExist()) {
    $failures[] = 'Tabelas de personalização ausentes.';
}

$admin = get_role('administrator');
$manager = get_role('shop_manager');
if (!$admin instanceof WP_Role || !$admin->has_cap(Capabilities::MANAGE)) {
    $failures[] = 'administrator sem manage_petshop_personalizations.';
}
if (!$manager instanceof WP_Role || !$manager->has_cap(Capabilities::MANAGE)) {
    $failures[] = 'shop_manager sem manage_petshop_personalizations.';
}

$page = get_page_by_path(PersonalizePageMigrator::SLUG);
if (!$page instanceof WP_Post) {
    $failures[] = 'Página /personalize/ ausente.';
} elseif (!str_contains((string) $page->post_content, 'petshop/personalizable-products')) {
    // Edited pages may omit the block intentionally after migration of placeholder only.
    $hash = (string) get_post_meta($page->ID, PersonalizePageMigrator::CONTENT_HASH_META, true);
    if ($hash === '') {
        $failures[] = 'Página /personalize/ sem bloco e sem hash de migração.';
    }
}

$fabric = WP_PLUGIN_DIR . '/petshop-core/assets/personalizer/vendor/fabric.min.js';
if (!is_file($fabric) || filesize($fabric) < 1000) {
    $failures[] = 'fabric.min.js ausente ou inválido.';
}

$specs = [
    'bandana' => new ProductionSpecification(280.0, 280.0, 150),
    'laco' => new ProductionSpecification(80.0, 50.0, 150),
    'adesivo' => new ProductionSpecification(100.0, 100.0, 300),
];
foreach ($specs as $label => $spec) {
    if ($spec->widthPx() < 10 || $spec->heightPx() < 10) {
        $failures[] = sprintf('Spec %s gerou pixels inválidos.', $label);
    }
}

if (!PersonalizationStatus::Draft->canTransitionTo(PersonalizationStatus::Cart)) {
    $failures[] = 'Transição draft→cart quebrada.';
}
if (PersonalizationStatus::Draft->canTransitionTo(PersonalizationStatus::Completed)) {
    $failures[] = 'Transição draft→completed deveria ser inválida.';
}

$tmpRoot = sys_get_temp_dir() . '/petshop-personalization-validate-' . wp_generate_password(8, false);
if (!wp_mkdir_p($tmpRoot)) {
    $failures[] = 'Não foi possível criar storage temporário de teste.';
} else {
    $previousFilter = null;
    add_filter('petshop_personalization_storage_root', static function () use ($tmpRoot): string {
        return $tmpRoot;
    }, 999);

    try {
        PrivateStorage::ensureReady();
        $relative = PrivateStorage::opaqueRelativePath(wp_generate_uuid4(), 'preview', 'png');
        PrivateStorage::writeBinary($relative, 'png-bytes');
        $read = PrivateStorage::readBinary($relative);
        if ($read !== 'png-bytes') {
            $failures[] = 'Round-trip de storage falhou.';
        }

        $webrootProbe = rtrim(ABSPATH, "/\\") . '/wp-content/uploads/petshop-forbidden';
        add_filter('petshop_personalization_storage_root', static function () use ($webrootProbe): string {
            return $webrootProbe;
        }, 1000);
        $forbidden = PrivateStorage::health();
        if ($forbidden['status'] !== PrivateStorage::HEALTH_UNDER_WEBROOT && $forbidden['status'] !== PrivateStorage::HEALTH_MISSING) {
            // Missing is acceptable if mkdir never happened; under_webroot is the hard reject once path resolves under ABSPATH.
            if (!str_starts_with(str_replace('\\', '/', $webrootProbe), str_replace('\\', '/', rtrim(ABSPATH, "/\\")))) {
                $failures[] = 'Detecção de storage sob webroot inconsistente.';
            }
        }
        remove_all_filters('petshop_personalization_storage_root');
        add_filter('petshop_personalization_storage_root', static function () use ($tmpRoot): string {
            return $tmpRoot;
        }, 999);

        try {
            PrivateStorage::absolutePath('../escape.png');
            $failures[] = 'Path traversal relativo não foi rejeitado.';
        } catch (Throwable) {
            // expected
        }
    } catch (Throwable $error) {
        $failures[] = 'Storage temporário: ' . $error->getMessage();
    } finally {
        remove_all_filters('petshop_personalization_storage_root');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        @rmdir($tmpRoot);
    }
}

$cleanup = CleanupScheduler::run(true, 10);
foreach (['expired_drafts', 'stale_cart_items', 'cancelled_file_candidates', 'completed_file_candidates'] as $key) {
    if (!array_key_exists($key, $cleanup) || !is_array($cleanup[$key])) {
        $failures[] = 'Cleanup dry-run sem chave ' . $key;
    }
}
if (($cleanup['cancelled_cutoff'] ?? '') === '' || ($cleanup['completed_cutoff'] ?? '') === '') {
    $failures[] = 'Cleanup sem cortes de retenção cancelado/concluído.';
}

if (RetentionPolicy::cancelledDays() < 1 || RetentionPolicy::completedDays() < 1) {
    $failures[] = 'Retenção cancelado/concluído inválida.';
}

$enabledSample = get_posts([
    'post_type' => 'product',
    'post_status' => 'publish',
    'posts_per_page' => 1,
    'fields' => 'ids',
    'meta_query' => [
        [
            'key' => ProductSettings::META_ENABLED,
            'value' => 'yes',
        ],
    ],
]);

WP_CLI::log(sprintf(
    'validate-012: storage=%s schema=%d enabled_products=%d',
    $health['status'],
    (int) get_option(SchemaMigrator::OPTION, 0),
    is_array($enabledSample) ? count($enabledSample) : 0
));

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error(sprintf('Plano 012: %d falha(s).', count($failures)));
}

WP_CLI::success('Plano 012: validação PHP ok.');
