<?php

declare(strict_types=1);

namespace Petshop\Core\Migration;

defined('ABSPATH') || exit;

trait ManagedHomeMigration
{
    private static function managedHomeContentPersisted(int $homeId, string $expected): bool
    {
        return (string) get_post_field('post_content', $homeId) === $expected;
    }

    public static function migrateManagedHome(
        int $homeId,
        string $shopUrl,
        string $supportUrl,
        int $heroId
    ): void
    {
        if (!(bool) get_post_meta($homeId, '_petshop_managed_page', true)) {
            return;
        }

        $content = (string) get_post_field('post_content', $homeId);
        $originalContent = $content;
        $legacy = '[product_categories number="8" parent="0" hide_empty="0" columns="4" orderby="menu_order"]';
        if (str_contains($content, $legacy)) {
            $content = str_replace($legacy, '[petshop_categories limit="8"]', $content);
        }

        $setSchemaTwo = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 2) {
            $newSections = <<<'BLOCKS'
<!-- wp:group {"className":"petshop-section petshop-section--soft","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section petshop-section--soft">
<!-- wp:heading --><h2 class="wp-block-heading">Novidades</h2><!-- /wp:heading -->
<!-- wp:shortcode -->[products limit="4" columns="4" orderby="date" order="DESC"]<!-- /wp:shortcode --></div><!-- /wp:group -->
<!-- wp:group {"className":"petshop-section petshop-seasonal","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section petshop-seasonal">
<!-- wp:heading --><h2 class="wp-block-heading">Coleção da estação</h2><!-- /wp:heading -->
<!-- wp:shortcode -->[petshop_seasonal_products limit="4" columns="4"]<!-- /wp:shortcode --></div><!-- /wp:group -->
<!-- wp:group {"className":"petshop-section petshop-reviews","layout":{"type":"constrained"}} --><div class="wp-block-group petshop-section petshop-reviews">
<!-- wp:heading --><h2 class="wp-block-heading">Quem compra, conta</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Avaliações reais e aprovadas dos produtos aparecem nesta seção.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->[petshop_reviews limit="3"]<!-- /wp:shortcode --></div><!-- /wp:group -->
BLOCKS;
            $anchor = '<!-- wp:group {"className":"petshop-section petshop-support-cta"';
            $position = strpos($content, $anchor);
            $content = $position === false
                ? $content . "\n" . $newSections
                : substr($content, 0, $position) . $newSections . "\n" . substr($content, $position);
            $setSchemaTwo = true;
        }

        $content = str_replace('href="/shop/"', 'href="' . esc_url($shopUrl) . '"', $content);
        $content = str_replace('href="/atendimento/"', 'href="' . esc_url($supportUrl) . '"', $content);
        $kitsHeading = '<h2 class="wp-block-heading">Kits e conjuntos</h2>';
        $kitsHeadingPosition = strpos($content, $kitsHeading);
        if ($kitsHeadingPosition !== false) {
            $beforeKits = substr($content, 0, $kitsHeadingPosition);
            $kitsClassPosition = strrpos($beforeKits, 'petshop-section petshop-section--soft');
            if ($kitsClassPosition !== false) {
                $content = substr_replace(
                    $content,
                    'petshop-section petshop-commerce-banner',
                    $kitsClassPosition,
                    strlen('petshop-section petshop-section--soft')
                );
            }
        }

        $setHeroSchema = false;
        $managedHeroMarkup = '';
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 7) {
            $heroClassPosition = strpos($content, '"className":"petshop-hero"');
            $heroStart = $heroClassPosition === false
                ? false
                : strrpos(substr($content, 0, $heroClassPosition), '<!-- wp:cover ');
            $heroEndMarker = '<!-- /wp:cover -->';
            if ($heroStart === false) {
                $heroStart = strpos($content, '<!-- wp:group {"className":"petshop-hero"');
                $heroEndMarker = '<!-- /wp:group -->';
            }
            $heroEnd = $heroStart === false ? false : strpos($content, $heroEndMarker, $heroStart);

            if ($heroStart === false || $heroEnd === false) {
                // A remoção ou substituição do hero no editor é uma escolha editorial válida.
                $setHeroSchema = true;
            } else {
                $heroEnd += strlen($heroEndMarker);
                $currentHero = substr($content, $heroStart, $heroEnd - $heroStart);
                $currentHash = hash('sha256', $currentHero);
                $knownHash = (string) get_post_meta($homeId, '_petshop_managed_hero_hash', true);
                $knownManaged = $knownHash !== '' && hash_equals($knownHash, $currentHash);
                $legacyManaged = $knownHash === ''
                    && hash_equals(
                        hash('sha256', self::legacyHeroContent($shopUrl, $heroId)),
                        $currentHash
                    );
                $hydratedCurrent = self::hydrateHeroAlt($currentHero);
                $currentManaged = $knownHash === ''
                    && hash_equals(
                        hash('sha256', self::campaignHeroContent($shopUrl, $heroId)),
                        hash('sha256', $hydratedCurrent)
                    );

                if ($legacyManaged) {
                    $managedHeroMarkup = self::heroContent($shopUrl, $heroId);
                    $content = substr($content, 0, $heroStart)
                        . $managedHeroMarkup
                        . substr($content, $heroEnd);
                } elseif ($knownManaged || $currentManaged) {
                    $managedHeroMarkup = $hydratedCurrent;
                    $content = substr($content, 0, $heroStart)
                        . $managedHeroMarkup
                        . substr($content, $heroEnd);
                }
                // Qualquer outro conteúdo é uma customização e deve permanecer intacto.
                $setHeroSchema = true;
            }
        }

        $setSchemaEight = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 8) {
            $heroClassPosition = strpos($content, '"className":"petshop-hero"');
            $heroStart = $heroClassPosition === false
                ? false
                : strrpos(substr($content, 0, $heroClassPosition), '<!-- wp:cover ');
            $heroEnd = $heroStart === false ? false : strpos($content, '<!-- /wp:cover -->', $heroStart);

            if ($heroStart !== false && $heroEnd !== false) {
                $heroEnd += strlen('<!-- /wp:cover -->');
                $currentHero = substr($content, $heroStart, $heroEnd - $heroStart);
                $currentHash = hash('sha256', $currentHero);
                $knownHash = (string) get_post_meta($homeId, '_petshop_managed_hero_hash', true);
                $isManaged = ($managedHeroMarkup !== '' && hash_equals(hash('sha256', $managedHeroMarkup), $currentHash))
                    || ($knownHash !== '' && hash_equals($knownHash, $currentHash))
                    || hash_equals(hash('sha256', self::heroContent($shopUrl, $heroId)), $currentHash)
                    || hash_equals(hash('sha256', self::campaignHeroContent($shopUrl, $heroId)), $currentHash);

                if ($isManaged) {
                    $managedHeroMarkup = self::heroContent($shopUrl, $heroId);
                    $content = substr($content, 0, $heroStart)
                        . $managedHeroMarkup
                        . substr($content, $heroEnd);
                    $heroEnd = $heroStart + strlen($managedHeroMarkup);
                } else {
                    delete_post_meta($homeId, '_petshop_managed_hero_hash');
                }
            } else {
                delete_post_meta($homeId, '_petshop_managed_hero_hash');
            }

            if (!str_contains($content, '"className":"petshop-benefits"')) {
                $benefits = self::benefitsContent();
                $insertAt = $heroEnd === false ? 0 : $heroEnd;
                $content = substr($content, 0, $insertAt)
                    . "\n" . $benefits
                    . substr($content, $insertAt);
            }
            $setSchemaEight = true;
        }

        $setSchemaNine = false;
        if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) < 9) {
            $heroClassPosition = strpos($content, '"className":"petshop-hero"');
            $heroStart = $heroClassPosition === false
                ? false
                : strrpos(substr($content, 0, $heroClassPosition), '<!-- wp:cover ');
            $heroEnd = $heroStart === false ? false : strpos($content, '<!-- /wp:cover -->', $heroStart);
            if ($heroStart !== false && $heroEnd !== false) {
                $heroEnd += strlen('<!-- /wp:cover -->');
                $currentHero = substr($content, $heroStart, $heroEnd - $heroStart);
                $currentHash = hash('sha256', $currentHero);
                $knownHash = (string) get_post_meta($homeId, '_petshop_managed_hero_hash', true);
                $isManaged = ($managedHeroMarkup !== '' && hash_equals(hash('sha256', $managedHeroMarkup), $currentHash))
                    || ($knownHash !== '' && hash_equals($knownHash, $currentHash))
                    || hash_equals(hash('sha256', self::invalidInstitutionalHeroContent($shopUrl, $heroId)), $currentHash)
                    || hash_equals(hash('sha256', self::heroContent($shopUrl, $heroId)), $currentHash);
                if ($isManaged) {
                    $managedHeroMarkup = self::heroContent($shopUrl, $heroId);
                    $content = substr($content, 0, $heroStart)
                        . $managedHeroMarkup
                        . substr($content, $heroEnd);
                } else {
                    delete_post_meta($homeId, '_petshop_managed_hero_hash');
                }
            } else {
                delete_post_meta($homeId, '_petshop_managed_hero_hash');
            }
            $setSchemaNine = true;
        }

        $registeredSchemas = self::applyRegisteredSchemas(
            $content,
            (int) get_post_meta($homeId, '_petshop_home_schema_version', true),
            $shopUrl,
            $supportUrl,
            $heroId
        );
        $content = $registeredSchemas['content'];
        $appliedSchemas = $registeredSchemas['applied'];

        if ($content !== $originalContent) {
            wp_save_post_revision($homeId);
        }
        $saved = wp_update_post(['ID' => $homeId, 'post_content' => wp_slash($content)], true);
        if (is_wp_error($saved)) {
            throw new \RuntimeException($saved->get_error_message());
        }
        if (!self::managedHomeContentPersisted($homeId, $content)) {
            throw new \RuntimeException('A atualização da Home não foi persistida integralmente.');
        }
        if ($setSchemaTwo) {
            update_post_meta($homeId, '_petshop_home_schema_version', 2);
        }
        if ($setHeroSchema) {
            update_post_meta($homeId, '_petshop_home_schema_version', 7);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 7) {
                throw new \RuntimeException('Não foi possível confirmar o schema da Home.');
            }
            if ($managedHeroMarkup !== '') {
                $managedHash = hash('sha256', $managedHeroMarkup);
                update_post_meta($homeId, '_petshop_managed_hero_hash', $managedHash);
                if ((string) get_post_meta($homeId, '_petshop_managed_hero_hash', true) !== $managedHash) {
                    throw new \RuntimeException('Não foi possível confirmar a assinatura do hero gerenciado.');
                }
            }
        }
        if ($setSchemaEight) {
            update_post_meta($homeId, '_petshop_home_schema_version', 8);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 8) {
                throw new \RuntimeException('Não foi possível confirmar o schema institucional da Home.');
            }
            if ($managedHeroMarkup !== '') {
                update_post_meta($homeId, '_petshop_managed_hero_hash', hash('sha256', $managedHeroMarkup));
            }
        }
        if ($setSchemaNine) {
            update_post_meta($homeId, '_petshop_home_schema_version', 9);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== 9) {
                throw new \RuntimeException('Não foi possível confirmar o schema válido do Gutenberg.');
            }
            if ($managedHeroMarkup !== '') {
                update_post_meta($homeId, '_petshop_managed_hero_hash', hash('sha256', $managedHeroMarkup));
            }
        }
        if ($appliedSchemas !== []) {
            $schema = max(
                (int) get_post_meta($homeId, '_petshop_home_schema_version', true),
                ...$appliedSchemas
            );
            update_post_meta($homeId, '_petshop_home_schema_version', $schema);
            if ((int) get_post_meta($homeId, '_petshop_home_schema_version', true) !== $schema) {
                throw new MigrationException(
                    'PETSHOP_HOME_SCHEMA_PERSISTENCE_FAILED',
                    sprintf('Não foi possível confirmar o schema %d da Home.', $schema)
                );
            }
        }
    }
}
