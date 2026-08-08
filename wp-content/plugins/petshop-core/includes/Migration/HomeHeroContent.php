<?php

declare(strict_types=1);

namespace Petshop\Core\Migration;

use Petshop\Core\HomeCampaignBlocks;

defined('ABSPATH') || exit;

trait HomeHeroContent
{
    private static function legacyHeroContent(string $shopUrl, int $heroId): string
    {
        $heroUrl = esc_url(wp_get_attachment_image_url($heroId, 'full') ?: '');
        $shopUrl = esc_url($shopUrl);

        return <<<BLOCKS
<!-- wp:cover {"url":"{$heroUrl}","id":{$heroId},"dimRatio":15,"overlayColor":"white","minHeight":440,"minHeightUnit":"px","contentPosition":"center left","className":"petshop-hero"} -->
<div class="wp-block-cover has-custom-content-position is-position-center-left petshop-hero" style="min-height:440px"><span aria-hidden="true" class="wp-block-cover__background has-white-background-color has-background-dim-15 has-background-dim"></span><img class="wp-block-cover__image-background wp-image-{$heroId}" alt="" src="{$heroUrl}" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:group {"className":"petshop-hero__copy","layout":{"type":"default"}} --><div class="wp-block-group petshop-hero__copy">
<!-- wp:paragraph {"className":"petshop-eyebrow"} --><p class="petshop-eyebrow">Acessórios para banho e tosa</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">O acabamento que faz seu cliente lembrar</h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Laços, bandanas, gravatas e finalizações para valorizar cada atendimento.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$shopUrl}">Comprar acessórios</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div><!-- /wp:group --></div></div>
<!-- /wp:cover -->
BLOCKS;
    }

    private static function hydrateHeroAlt(string $heroMarkup): string
    {
        if (!preg_match('/wp-image-(\d+)/', $heroMarkup, $matches)) {
            return $heroMarkup;
        }
        $openingEnd = strpos($heroMarkup, '-->');
        $opening = $openingEnd === false ? '' : substr($heroMarkup, 0, $openingEnd);
        $alt = '';
        if (preg_match('/"alt":"([^"]+)"/u', $opening, $altMatch)) {
            $decoded = json_decode('"' . $altMatch[1] . '"', true);
            $alt = is_string($decoded) ? $decoded : '';
        }
        if (
            $alt === ''
            && preg_match(
                '/<img\b[^>]*\bclass="[^"]*wp-block-cover__image-background[^"]*"[^>]*\balt="([^"]+)"/',
                $heroMarkup,
                $altMatch
            )
        ) {
            $alt = html_entity_decode($altMatch[1], ENT_QUOTES);
        }
        if ($alt === '') {
            $alt = (string) get_post_meta((int) $matches[1], '_wp_attachment_image_alt', true);
        }
        if ($alt === '') {
            return $heroMarkup;
        }

        if ($openingEnd !== false) {
            if (str_contains($opening, '"alt":""')) {
                $opening = str_replace(
                    '"alt":""',
                    '"alt":' . wp_json_encode($alt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $opening
                );
                $heroMarkup = $opening . substr($heroMarkup, $openingEnd);
            } elseif (!str_contains($opening, '"alt":')) {
                $opening = str_replace(
                    '"dimRatio":',
                    '"alt":' . wp_json_encode($alt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ',"dimRatio":',
                    $opening
                );
                $heroMarkup = $opening . substr($heroMarkup, $openingEnd);
            }
        }

        if (
            preg_match(
                '/<img\b[^>]*\bclass="[^"]*wp-block-cover__image-background[^"]*"[^>]*\balt="[^"]+"/',
                $heroMarkup
            )
        ) {
            return $heroMarkup;
        }

        return (string) preg_replace(
            '/(<img\b[^>]*\bclass="[^"]*wp-block-cover__image-background[^"]*"[^>]*\balt=")[^"]*(")/',
            '$1' . esc_attr($alt) . '$2',
            $heroMarkup,
            1
        );
    }

    private static function campaignHeroContent(string $shopUrl, int $heroId): string
    {
        $heroUrl = wp_get_attachment_image_url($heroId, 'full') ?: '';
        $heroAlt = (string) get_post_meta($heroId, '_wp_attachment_image_alt', true);
        $heroAltJson = wp_json_encode($heroAlt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $heroAltAttribute = esc_attr($heroAlt);
        $destination = get_term_link('dia-dos-pais', 'product_cat');
        $destination = is_wp_error($destination) ? $shopUrl : $destination;
        $destination = esc_url($destination);
        $heroUrl = esc_url($heroUrl);

        return <<<BLOCKS
<!-- wp:cover {"url":"{$heroUrl}","id":{$heroId},"alt":{$heroAltJson},"dimRatio":15,"overlayColor":"white","minHeight":440,"minHeightUnit":"px","contentPosition":"center left","align":"full","className":"petshop-hero"} -->
<div class="wp-block-cover alignfull has-custom-content-position is-position-center-left petshop-hero" style="min-height:440px"><span aria-hidden="true" class="wp-block-cover__background has-white-background-color has-background-dim-15 has-background-dim"></span><img class="wp-block-cover__image-background wp-image-{$heroId}" alt="{$heroAltAttribute}" src="{$heroUrl}" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:group {"className":"petshop-hero__copy","layout":{"type":"default"}} --><div class="wp-block-group petshop-hero__copy">
<!-- wp:paragraph {"className":"petshop-eyebrow"} --><p class="petshop-eyebrow">Coleção Dia dos Pais</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">O detalhe que <span class="petshop-hero__accent">fideliza seu cliente</span></h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Gravatas, laços e adesivos temáticos que vão transformar cada atendimento em uma experiência especial e encantar o tutor.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$destination}">Ver a coleção de Dia dos Pais</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
<!-- wp:paragraph {"className":"petshop-hero__note"} --><p class="petshop-hero__note">Frete grátis para todo o Brasil acima de R$ 299</p><!-- /wp:paragraph -->
</div><!-- /wp:group --></div></div>
<!-- /wp:cover -->
BLOCKS;
    }

    private static function invalidInstitutionalHeroContent(string $shopUrl, int $heroId): string
    {
        $heroUrl = esc_url(wp_get_attachment_image_url($heroId, 'full') ?: '');
        $heroAlt = (string) get_post_meta($heroId, '_wp_attachment_image_alt', true);
        $heroAltJson = wp_json_encode($heroAlt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $heroAltAttribute = esc_attr($heroAlt);
        $featuredUrl = esc_url($shopUrl);
        $kitsUrl = get_term_link('conjuntos', 'product_cat');
        $kitsUrl = esc_url(is_wp_error($kitsUrl) ? $shopUrl : $kitsUrl);

        return <<<BLOCKS
<!-- wp:cover {"url":"{$heroUrl}","id":{$heroId},"alt":{$heroAltJson},"dimRatio":15,"overlayColor":"white","minHeight":440,"minHeightUnit":"px","contentPosition":"center left","align":"full","className":"petshop-hero"} -->
<div class="wp-block-cover alignfull has-custom-content-position is-position-center-left petshop-hero" style="min-height:440px"><span aria-hidden="true" class="wp-block-cover__background has-white-background-color has-background-dim-15 has-background-dim"></span><img class="wp-block-cover__image-background wp-image-{$heroId}" alt="{$heroAltAttribute}" src="{$heroUrl}" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:group {"className":"petshop-hero__copy","layout":{"type":"default"}} --><div class="wp-block-group petshop-hero__copy">
<!-- wp:paragraph {"className":"petshop-eyebrow"} --><p class="petshop-eyebrow">Acessórios para banho e tosa</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Acessórios que valorizam cada banho e tosa</h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Bandanas, laços, gravatas e adesivos com acabamento profissional e opções para diferentes volumes.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$featuredUrl}">Ver destaques da loja</a></div><!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="{$kitsUrl}">Conhecer kits econômicos</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div><!-- /wp:group --></div></div>
<!-- /wp:cover -->
BLOCKS;
    }

    private static function heroContent(string $shopUrl, int $heroId): string
    {
        $heroUrl = esc_url(wp_get_attachment_image_url($heroId, 'full') ?: '');
        $heroAlt = (string) get_post_meta($heroId, '_wp_attachment_image_alt', true);
        $heroAltJson = wp_json_encode($heroAlt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $heroAltAttribute = esc_attr($heroAlt);
        $featuredUrl = esc_url($shopUrl);
        $kitsUrl = get_term_link('conjuntos', 'product_cat');
        $kitsUrl = esc_url(is_wp_error($kitsUrl) ? $shopUrl : $kitsUrl);

        return <<<BLOCKS
<!-- wp:cover {"url":"{$heroUrl}","id":{$heroId},"alt":{$heroAltJson},"dimRatio":0,"minHeight":480,"minHeightUnit":"px","contentPosition":"center left","align":"full","className":"petshop-hero"} -->
<div class="wp-block-cover alignfull has-custom-content-position is-position-center-left petshop-hero" style="min-height:480px"><img class="wp-block-cover__image-background wp-image-{$heroId}" alt="{$heroAltAttribute}" src="{$heroUrl}" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:group {"className":"petshop-hero__copy","layout":{"type":"default"}} --><div class="wp-block-group petshop-hero__copy">
<!-- wp:paragraph {"className":"petshop-eyebrow"} --><p class="petshop-eyebrow">Acessórios para banho e tosa</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Acessórios que valorizam cada banho e tosa</h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Bandanas, laços, gravatas e adesivos com acabamento profissional e opções para diferentes volumes.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$featuredUrl}">Ver destaques da loja</a></div><!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="{$kitsUrl}">Conhecer kits econômicos</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div><!-- /wp:group --></div></div>
<!-- /wp:cover -->
BLOCKS;
    }

    private static function benefitsContent(array $overrides = []): string
    {
        $items = [
            [
                'class' => 'petshop-benefits__item--delivery',
                'title' => __('Pronta entrega', 'petshop-core'),
                'detail' => __('Produtos à pronta entrega', 'petshop-core'),
            ],
            [
                'class' => 'petshop-benefits__item--volume',
                'title' => __('Condições para volume', 'petshop-core'),
                'detail' => __('Preços especiais para pet shops', 'petshop-core'),
            ],
            [
                'class' => 'petshop-benefits__item--shipping',
                'title' => __('Frete para todo o Brasil', 'petshop-core'),
                'detail' => __('Envio rápido e seguro', 'petshop-core'),
            ],
        ];

        foreach ($overrides as $index => $override) {
            if (!isset($items[$index]) || !is_array($override)) {
                continue;
            }
            if (isset($override['title']) && trim((string) $override['title']) !== '') {
                $items[$index]['title'] = (string) $override['title'];
            }
            if (isset($override['detail']) && trim((string) $override['detail']) !== '') {
                $items[$index]['detail'] = (string) $override['detail'];
            }
        }

        $itemsMarkup = '';
        foreach ($items as $item) {
            $class = esc_attr($item['class']);
            $title = esc_html($item['title']);
            $detail = esc_html($item['detail']);
            $itemsMarkup .= <<<ITEM
<!-- wp:group {"className":"petshop-benefits__item {$class}","layout":{"type":"default"}} -->
<div class="wp-block-group petshop-benefits__item {$class}">
<!-- wp:group {"className":"petshop-benefits__content","layout":{"type":"default"}} -->
<div class="wp-block-group petshop-benefits__content">
<!-- wp:image {"className":"petshop-benefits__icon","linkDestination":"none"} -->
<figure class="wp-block-image petshop-benefits__icon"><img alt="" /></figure>
<!-- /wp:image -->
<!-- wp:group {"className":"petshop-benefits__copy","layout":{"type":"default"}} -->
<div class="wp-block-group petshop-benefits__copy">
<!-- wp:paragraph {"className":"petshop-benefits__title"} --><p class="petshop-benefits__title"><strong>{$title}</strong></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"petshop-benefits__detail"} --><p class="petshop-benefits__detail">{$detail}</p><!-- /wp:paragraph -->
</div><!-- /wp:group -->
</div><!-- /wp:group -->
</div><!-- /wp:group -->

ITEM;
        }

        return <<<BLOCKS
<!-- wp:group {"align":"full","className":"petshop-benefits","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull petshop-benefits">
<!-- wp:group {"className":"petshop-benefits__inner","layout":{"type":"default"}} -->
<div class="wp-block-group petshop-benefits__inner">
{$itemsMarkup}</div><!-- /wp:group --></div><!-- /wp:group -->
BLOCKS;
    }
}
