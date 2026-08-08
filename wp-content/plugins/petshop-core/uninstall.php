<?php

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

// Remove somente estado técnico próprio. Conteúdo, mídia, menus e theme_mods
// administráveis são preservados para evitar perda editorial na reinstalação.
$petshopCoreOptions = [
    'petshop_storefront_version',
    'petshop_storefront_migration_lock',
    'petshop_storefront_migration_error',
    'petshop_storefront_migration_scheduled',
    'petshop_commercial_menu_version',
    'petshop_catalog_taxonomy_version',
    'petshop_catalog_taxonomy_lock',
    'petshop_catalog_taxonomy_error',
    'petshop_logo_attachment_id',
    'petshop_support_banner_attachment_id',
];

foreach ($petshopCoreOptions as $petshopCoreOption) {
    delete_option($petshopCoreOption);
}
