<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

trait HomeCampaignAssets
{
    private static function registerEditorScript(string $handle, string $file, string $base): void
    {
        $assetPath = $base . str_replace('.js', '.asset.php', $file);
        $asset = is_file($assetPath) ? require $assetPath : ['dependencies' => [], 'version' => '0.1.0'];

        wp_register_script(
            $handle,
            plugins_url('blocks/build/' . $file, PETSHOP_CORE_FILE),
            $asset['dependencies'],
            $asset['version'],
            true
        );
    }

    private static function registerViewScript(string $handle, string $file, string $base): void
    {
        $assetPath = $base . str_replace('.js', '.asset.php', $file);
        $asset = is_file($assetPath) ? require $assetPath : ['dependencies' => [], 'version' => '0.1.0'];

        wp_register_script(
            $handle,
            plugins_url('blocks/build/' . $file, PETSHOP_CORE_FILE),
            $asset['dependencies'],
            $asset['version'],
            true
        );
    }
}
