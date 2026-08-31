<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Migration;

use Petshop\Core\Personalization\Blocks\PersonalizableProductsBlock;

defined('ABSPATH') || exit;

/**
 * Replaces the managed "Personalização em preparação" placeholder with editable
 * Gutenberg content. Any client edit is preserved.
 */
final class PersonalizePageMigrator
{
    public const OPTION = 'petshop_personalization_page_version';
    public const VERSION = 1;
    public const SLUG = 'personalize';
    public const CONTENT_HASH_META = '_petshop_personalize_012_hash';

    public const RESULT_MIGRATED = 'migrated';
    public const RESULT_ALREADY_DONE = 'already_done';
    public const RESULT_PRESERVED = 'preserved_client_content';
    public const RESULT_MISSING = 'page_missing';

    public static function maybeMigrate(): string
    {
        if ((int) get_option(self::OPTION, 0) >= self::VERSION) {
            return self::RESULT_ALREADY_DONE;
        }

        $result = self::migrate();
        if ($result !== self::RESULT_MISSING) {
            update_option(self::OPTION, self::VERSION, false);
        }

        return $result;
    }

    public static function migrate(): string
    {
        $page = get_page_by_path(self::SLUG);
        if (!$page instanceof \WP_Post) {
            return self::RESULT_MISSING;
        }

        $current = self::normalize((string) $page->post_content);
        if (str_contains($current, PersonalizableProductsBlock::NAME)) {
            return self::RESULT_ALREADY_DONE;
        }

        if (!in_array($current, array_map([self::class, 'normalize'], self::managedPlaceholders()), true)) {
            return self::RESULT_PRESERVED;
        }

        $content = self::content();
        $updated = wp_update_post([
            'ID' => (int) $page->ID,
            'post_content' => wp_slash($content),
        ], true);

        if (is_wp_error($updated)) {
            throw new \RuntimeException($updated->get_error_message());
        }

        update_post_meta((int) $page->ID, self::CONTENT_HASH_META, hash('sha256', $content));

        return self::RESULT_MIGRATED;
    }

    public static function content(): string
    {
        return '<!-- wp:heading -->' . "\n"
            . '<h2 class="wp-block-heading">Produtos personalizados do seu jeito</h2>' . "\n"
            . '<!-- /wp:heading -->' . "\n\n"
            . '<!-- wp:paragraph -->' . "\n"
            . '<p>Escolha um produto abaixo, escreva o nome do pet ou envie uma imagem e confira a prévia antes de finalizar o pedido.</p>' . "\n"
            . '<!-- /wp:paragraph -->' . "\n\n"
            . '<!-- wp:' . PersonalizableProductsBlock::NAME . ' {"limit":8,"columns":4} /-->';
    }

    /**
     * @return list<string>
     */
    private static function managedPlaceholders(): array
    {
        return [
            '<!-- wp:heading --><h2 class="wp-block-heading">Personalização em preparação</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Esta área está reservada para uma futura experiência de personalização. O catálogo atual continua disponível normalmente.</p><!-- /wp:paragraph -->',
            '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">Personalização em preparação</h2>' . "\n"
            . '<!-- /wp:heading -->' . "\n\n"
            . '<!-- wp:paragraph -->' . "\n"
            . '<p>Esta área está reservada para uma futura experiência de personalização. O catálogo atual continua disponível normalmente.</p>' . "\n"
            . '<!-- /wp:paragraph -->',
        ];
    }

    private static function normalize(string $content): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($content));
        $normalized = (string) preg_replace('/\n{2,}/', "\n\n", $normalized);

        return (string) preg_replace('/[ \t]+\n/', "\n", $normalized);
    }
}
