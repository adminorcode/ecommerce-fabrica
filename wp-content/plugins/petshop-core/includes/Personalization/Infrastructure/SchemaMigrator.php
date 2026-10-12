<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Infrastructure;

defined('ABSPATH') || exit;

/**
 * Versioned schema for personalization tables (dbDelta).
 */
final class SchemaMigrator
{
    public const OPTION = 'petshop_personalization_schema_version';
    public const VERSION = 1;

    public static function maybeMigrate(): void
    {
        $current = (int) get_option(self::OPTION, 0);
        if ($current >= self::VERSION) {
            return;
        }

        self::install();
        update_option(self::OPTION, self::VERSION, false);
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $personalizations = $wpdb->prefix . 'petshop_personalizations';
        $files = $wpdb->prefix . 'petshop_personalization_files';
        $history = $wpdb->prefix . 'petshop_personalization_status_history';

        $sqlPersonalizations = "CREATE TABLE {$personalizations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            user_id bigint(20) unsigned NULL,
            cart_hash char(64) NULL,
            product_id bigint(20) unsigned NOT NULL,
            variation_id bigint(20) unsigned NULL,
            order_id bigint(20) unsigned NULL,
            order_item_id bigint(20) unsigned NULL,
            status varchar(32) NOT NULL,
            status_version int unsigned NOT NULL DEFAULT 1,
            design_schema_version int unsigned NOT NULL DEFAULT 1,
            design_json longtext NOT NULL,
            config_snapshot longtext NOT NULL,
            text_summary text NOT NULL,
            snapshot_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            expires_at datetime NULL,
            completed_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            KEY status_updated (status, updated_at),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY cart_hash (cart_hash),
            KEY expires_at (expires_at)
        ) {$charset};";

        $sqlFiles = "CREATE TABLE {$files} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            personalization_id bigint(20) unsigned NOT NULL,
            file_type varchar(16) NOT NULL,
            relative_path varchar(255) NOT NULL,
            mime_type varchar(100) NOT NULL,
            extension varchar(16) NOT NULL,
            byte_size bigint(20) unsigned NOT NULL DEFAULT 0,
            width_px int unsigned NULL,
            height_px int unsigned NULL,
            dpi_target int unsigned NULL,
            content_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            deleted_at datetime NULL,
            PRIMARY KEY  (id),
            KEY personalization_id (personalization_id),
            KEY file_type (file_type),
            UNIQUE KEY personalization_type (personalization_id, file_type)
        ) {$charset};";

        $sqlHistory = "CREATE TABLE {$history} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            personalization_id bigint(20) unsigned NOT NULL,
            from_status varchar(32) NULL,
            to_status varchar(32) NOT NULL,
            actor_user_id bigint(20) unsigned NULL,
            note text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY personalization_id (personalization_id)
        ) {$charset};";

        dbDelta($sqlPersonalizations);
        dbDelta($sqlFiles);
        dbDelta($sqlHistory);
    }
}
