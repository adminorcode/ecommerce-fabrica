<?php

declare(strict_types=1);

namespace Petshop\Core\Storefront;

use Petshop\Core\HomeCampaignBlocks;
use Petshop\Core\Provisioning\StorefrontProvisioner;

defined('ABSPATH') || exit;

final class SupportContent
{
    private const LEGACY_PLACEHOLDER_KEY = 'support-banner-whatsapp';

    public static function resolveSupportBannerUrl(string $fallbackUrl = ''): string
    {
        $url = trim((string) get_theme_mod('petshop_support_banner_url', ''));
        if ($url !== '') {
            return $url;
        }

        $url = trim((string) get_theme_mod('petshop_footer_whatsapp', ''));
        if ($url !== '') {
            return $url;
        }

        $supportPageId = (int) get_theme_mod('petshop_support_page', 0);
        if ($supportPageId > 0) {
            $permalink = get_permalink($supportPageId);
            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        return trim($fallbackUrl);
    }

    public static function resolveWhatsAppUrl(string $fallbackUrl = ''): string
    {
        foreach ([
            trim((string) get_theme_mod('petshop_footer_whatsapp', '')),
            trim((string) get_theme_mod('petshop_support_banner_url', '')),
            trim($fallbackUrl),
        ] as $url) {
            if (self::isValidWhatsAppUrl($url)) {
                return $url;
            }
        }

        return '';
    }

    /**
     * @return array{url: string, label: string, is_whatsapp: bool}
     */
    public static function resolveSupportCta(string $fallbackUrl = ''): array
    {
        $whatsAppUrl = self::resolveWhatsAppUrl($fallbackUrl);
        if ($whatsAppUrl !== '') {
            return [
                'url' => $whatsAppUrl,
                'label' => __('Falar pelo WhatsApp', 'petshop-core'),
                'is_whatsapp' => true,
            ];
        }

        $supportUrl = self::resolveSupportBannerUrl($fallbackUrl);
        if ($supportUrl !== '') {
            return [
                'url' => $supportUrl,
                'label' => __('Falar com atendimento', 'petshop-core'),
                'is_whatsapp' => false,
            ];
        }

        return ['url' => '', 'label' => '', 'is_whatsapp' => false];
    }

    public static function resolveSupportCtaUrl(string $fallbackUrl = ''): string
    {
        return self::resolveSupportCta($fallbackUrl)['url'];
    }

    public static function isValidWhatsAppUrl(string $url): bool
    {
        $parts = wp_parse_url(trim($url));

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'wa.me'
            && preg_match('/^\/[1-9][0-9]{7,15}$/', (string) ($parts['path'] ?? '')) === 1;
    }

    public static function supportBannerContent(int $imageId, string $url): string
    {
        if ($imageId <= 0 || trim($url) === '') {
            return '';
        }

        $imageUrl = wp_get_attachment_image_url($imageId, 'full') ?: '';
        if ($imageUrl === '') {
            return '';
        }

        $alt = trim((string) get_post_meta($imageId, '_wp_attachment_image_alt', true));
        if ($alt === '') {
            $alt = __(
                'Precisa de ajuda para escolher? Fale com nossa equipe no WhatsApp.',
                'petshop-core'
            );
        }

        $altAttribute = esc_attr($alt);
        $url = esc_url($url);
        $imageUrl = esc_url($imageUrl);
        $imageAttrs = wp_json_encode([
            'id' => $imageId,
            'sizeSlug' => 'full',
            'linkDestination' => 'custom',
            'className' => 'petshop-support-banner__image',
            'href' => $url,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<BLOCKS
<!-- wp:group {"className":"petshop-section petshop-support-banner","layout":{"type":"constrained"}} -->
<div class="wp-block-group petshop-section petshop-support-banner"><!-- wp:image {$imageAttrs} -->
<figure class="wp-block-image size-full petshop-support-banner__image"><a href="{$url}"><img src="{$imageUrl}" alt="{$altAttribute}" class="wp-image-{$imageId}"/></a></figure>
<!-- /wp:image --></div>
<!-- /wp:group -->
BLOCKS;
    }

    public static function supportSectionContent(int $desktopImageId, int $mobileImageId, string $ctaUrl = '', string $ctaLabel = ''): string
    {
        if ($desktopImageId <= 0 || $mobileImageId <= 0) {
            return '';
        }

        $desktopImageUrl = wp_get_attachment_image_url($desktopImageId, 'full') ?: '';
        $mobileImageUrl = wp_get_attachment_image_url($mobileImageId, 'full') ?: '';
        if ($desktopImageUrl === '' || $mobileImageUrl === '') {
            return '';
        }

        $desktopAlt = self::attachmentAlt(
            $desktopImageId,
            __('Bancada com acessórios pet organizados para atendimento e envio.', 'petshop-core')
        );
        $mobileAlt = self::attachmentAlt(
            $mobileImageId,
            __('Acessórios pet em embalagem sobre bancada de atendimento.', 'petshop-core')
        );
        $desktopImageAttrs = wp_json_encode([
            'id' => $desktopImageId,
            'sizeSlug' => 'full',
            'className' => 'petshop-support-banner__image petshop-support-banner__image--desktop',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $mobileImageAttrs = wp_json_encode([
            'id' => $mobileImageId,
            'sizeSlug' => 'full',
            'className' => 'petshop-support-banner__image petshop-support-banner__image--mobile',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($desktopImageAttrs) || !is_string($mobileImageAttrs)) {
            return '';
        }

        $button = '';
        $ctaUrl = trim($ctaUrl);
        if ($ctaUrl !== '') {
            $url = esc_url($ctaUrl);
            $label = esc_html($ctaLabel !== '' ? $ctaLabel : __('Falar com atendimento', 'petshop-core'));
            $button = <<<BLOCKS
<!-- wp:buttons {"className":"petshop-support-banner__actions","layout":{"type":"flex","justifyContent":"left"}} -->
<div class="wp-block-buttons petshop-support-banner__actions"><!-- wp:button {"className":"petshop-support-banner__button"} -->
<div class="wp-block-button petshop-support-banner__button"><a class="wp-block-button__link wp-element-button" href="{$url}">{$label}</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
BLOCKS;
        }

        $desktopImageUrl = esc_url($desktopImageUrl);
        $mobileImageUrl = esc_url($mobileImageUrl);
        $desktopAlt = esc_attr($desktopAlt);
        $mobileAlt = esc_attr($mobileAlt);

        return <<<BLOCKS
<!-- wp:group {"className":"petshop-section petshop-support-banner","layout":{"type":"constrained"}} -->
<div class="wp-block-group petshop-section petshop-support-banner"><!-- wp:group {"className":"petshop-support-banner__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group petshop-support-banner__inner"><!-- wp:group {"className":"petshop-support-banner__content","layout":{"type":"constrained"}} -->
<div class="wp-block-group petshop-support-banner__content"><!-- wp:paragraph {"className":"petshop-support-banner__eyebrow"} -->
<p class="petshop-support-banner__eyebrow">Atendimento especializado</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2,"className":"petshop-support-banner__title"} -->
<h2 class="wp-block-heading petshop-support-banner__title">Precisa de ajuda para escolher?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"petshop-support-banner__text"} -->
<p class="petshop-support-banner__text">Nossa equipe ajuda voce a encontrar acessorios adequados para seu pet ou negocio.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"petshop-support-banner__benefit"} -->
<p class="petshop-support-banner__benefit">Orientacao para pedidos, kits e reposicao.</p>
<!-- /wp:paragraph -->

{$button}</div>
<!-- /wp:group -->

<!-- wp:group {"className":"petshop-support-banner__media","layout":{"type":"constrained"}} -->
<div class="wp-block-group petshop-support-banner__media"><!-- wp:image {$desktopImageAttrs} -->
<figure class="wp-block-image size-full petshop-support-banner__image petshop-support-banner__image--desktop"><img src="{$desktopImageUrl}" alt="{$desktopAlt}" class="wp-image-{$desktopImageId}"/></figure>
<!-- /wp:image -->

<!-- wp:image {$mobileImageAttrs} -->
<figure class="wp-block-image size-full petshop-support-banner__image petshop-support-banner__image--mobile"><img src="{$mobileImageUrl}" alt="{$mobileAlt}" class="wp-image-{$mobileImageId}"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
BLOCKS;
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    public static function normalizeLinkedImageBlock(array $block): array
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $href = trim((string) ($attrs['href'] ?? ''));
        if (
            $href === ''
            && preg_match('/<a\s[^>]*href=(["\'])([^"\']+)\1/i', (string) ($block['innerHTML'] ?? ''), $matches)
        ) {
            $href = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if ($href === '') {
            return $block;
        }

        $attrs['linkDestination'] = 'custom';
        $attrs['href'] = $href;
        $block['attrs'] = $attrs;

        return $block;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    public static function repairSupportBannerImageBlocks(array $blocks): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (
                ($block['blockName'] ?? '') === 'core/image'
                && str_contains((string) ($block['attrs']['className'] ?? ''), 'petshop-support-banner__image')
            ) {
                $block = self::normalizeLinkedImageBlock($block);
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::repairSupportBannerImageBlocks($block['innerBlocks']);
            }

            $updated[] = $block;
        }

        return $updated;
    }

    public static function applyHomeSchemaTwentyTwo(string $content): string
    {
        if (!str_contains($content, 'petshop-support-banner__image')) {
            return $content;
        }

        return serialize_blocks(self::repairSupportBannerImageBlocks(parse_blocks($content)));
    }

    public static function applyHomeSchemaTwentyThree(string $content): string
    {
        if (
            str_contains($content, 'petshop-reviews-section')
            && !str_contains($content, 'petshop-product-showcase petshop-reviews-section')
        ) {
            return $content;
        }

        $updated = str_replace(
            'petshop-section petshop-product-showcase petshop-reviews-section',
            'petshop-section petshop-reviews-section',
            $content
        );
        $updated = str_replace(
            'petshop-product-showcase__intro">Avaliações reais',
            'petshop-reviews-section__intro">Avaliações reais',
            $updated
        );

        return $updated;
    }

    public static function applyHomeSchemaTwentyFour(string $content, string $shopUrl, int $heroId): string
    {
        return HomeCampaignBlocks::insertCampaignsIfMissing($content, $heroId, $shopUrl);
    }

    public static function applyHomeSchemaTwentySix(
        string $content,
        int $desktopImageId,
        int $mobileImageId,
        string $ctaUrl,
        string $ctaLabel = ''
    ): string
    {
        $replacement = self::supportSectionContent($desktopImageId, $mobileImageId, $ctaUrl, $ctaLabel);
        if ($replacement === '') {
            return $content;
        }

        $replacementBlocks = parse_blocks($replacement);
        if ($replacementBlocks === []) {
            return $content;
        }

        $changed = false;
        $blocks = self::replaceLegacySupportBannerBlocks(parse_blocks($content), $replacementBlocks[0], $changed);
        if (!$changed) {
            return $content;
        }

        return serialize_blocks($blocks);
    }

    public static function needsSupportSectionMigration(string $content): bool
    {
        if (!str_contains($content, '[petshop_support_banner]') && !str_contains($content, 'petshop-support-banner')) {
            return false;
        }

        return self::hasLegacySupportBannerBlocks(parse_blocks($content))
            || self::hasManagedSupportSectionWithoutCta(parse_blocks($content));
    }

    public static function renderSupportBanner(): string
    {
        $images = StorefrontProvisioner::supportSectionAttachments();
        if ($images['desktop'] <= 0 || $images['mobile'] <= 0) {
            return '';
        }

        $cta = self::resolveSupportCta();
        $markup = self::supportSectionContent($images['desktop'], $images['mobile'], $cta['url'], $cta['label']);
        if ($markup === '') {
            return '';
        }

        return do_blocks($markup);
    }

    private static function attachmentAlt(int $imageId, string $fallback): string
    {
        $alt = trim((string) get_post_meta($imageId, '_wp_attachment_image_alt', true));

        return $alt === '' ? $fallback : $alt;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<string, mixed> $replacement
     * @return array<int, array<string, mixed>>
     */
    private static function replaceLegacySupportBannerBlocks(array $blocks, array $replacement, bool &$changed): array
    {
        $updated = [];

        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/shortcode') {
                $shortcode = trim(strip_tags((string) ($block['innerHTML'] ?? '')));
                if ($shortcode === '[petshop_support_banner]') {
                    $updated[] = $replacement;
                    $changed = true;
                    continue;
                }
            }

            if (self::isLegacySupportBannerBlock($block)) {
                $updated[] = $replacement;
                $changed = true;
                continue;
            }

            if (self::isManagedSupportSectionWithoutCta($block)) {
                $updated[] = $replacement;
                $changed = true;
                continue;
            }

            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::replaceLegacySupportBannerBlocks($block['innerBlocks'], $replacement, $changed);
            }

            $updated[] = $block;
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function isLegacySupportBannerBlock(array $block): bool
    {
        if (($block['blockName'] ?? '') !== 'core/group') {
            return false;
        }

        $className = (string) ($block['attrs']['className'] ?? '');
        if (!str_contains($className, 'petshop-support-banner')) {
            return false;
        }

        $imageBlocks = self::supportBannerImageBlocks($block);
        if (count($imageBlocks) !== 1) {
            return false;
        }

        $imageBlock = $imageBlocks[0];
        if (!str_contains((string) ($imageBlock['attrs']['className'] ?? ''), 'petshop-support-banner__image')) {
            return false;
        }

        $imageId = absint($imageBlock['attrs']['id'] ?? 0);
        if (
            $imageId > 0
            && (string) get_post_meta($imageId, '_petshop_placeholder_key', true) === self::LEGACY_PLACEHOLDER_KEY
        ) {
            return true;
        }

        return str_contains((string) ($imageBlock['innerHTML'] ?? ''), 'banner-whatsapp-atendimento');
    }

    /**
     * @param array<string, mixed> $block
     * @return array<int, array<string, mixed>>
     */
    private static function supportBannerImageBlocks(array $block): array
    {
        $matches = [];
        foreach (($block['innerBlocks'] ?? []) as $innerBlock) {
            if (!is_array($innerBlock)) {
                continue;
            }
            if (($innerBlock['blockName'] ?? '') === 'core/image') {
                $matches[] = $innerBlock;
                continue;
            }
            $matches = array_merge($matches, self::supportBannerImageBlocks($innerBlock));
        }

        return $matches;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    private static function hasLegacySupportBannerBlocks(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (($block['blockName'] ?? '') === 'core/shortcode') {
                $shortcode = trim(strip_tags((string) ($block['innerHTML'] ?? '')));
                if ($shortcode === '[petshop_support_banner]') {
                    return true;
                }
            }

            if (self::isLegacySupportBannerBlock($block)) {
                return true;
            }

            if (!empty($block['innerBlocks']) && self::hasLegacySupportBannerBlocks($block['innerBlocks'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function isManagedSupportSectionWithoutCta(array $block): bool
    {
        if (($block['blockName'] ?? '') !== 'core/group') {
            return false;
        }

        $className = (string) ($block['attrs']['className'] ?? '');
        $blockMarkup = serialize_blocks([$block]);

        return str_contains($className, 'petshop-support-banner')
            && str_contains($blockMarkup, 'Atendimento especializado')
            && str_contains($blockMarkup, 'Precisa de ajuda para escolher?')
            && str_contains($blockMarkup, 'Nossa equipe ajuda voce')
            && (
                !str_contains($blockMarkup, 'wp-block-button__link')
                || self::usesOutdatedSupportSectionPlaceholder($block)
            );
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function usesOutdatedSupportSectionPlaceholder(array $block): bool
    {
        foreach (self::supportBannerImageBlocks($block) as $imageBlock) {
            $imageId = absint($imageBlock['attrs']['id'] ?? 0);
            if ($imageId <= 0) {
                continue;
            }

            $key = (string) get_post_meta($imageId, '_petshop_placeholder_key', true);
            if ($key === 'support-section-desktop' || $key === 'support-section-mobile') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    private static function hasManagedSupportSectionWithoutCta(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (self::isManagedSupportSectionWithoutCta($block)) {
                return true;
            }

            if (!empty($block['innerBlocks']) && self::hasManagedSupportSectionWithoutCta($block['innerBlocks'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderReviewsSection(array $attributes = []): string
    {
        $attributes = shortcode_atts(['limit' => 3], $attributes, 'petshop_reviews_section');
        $reviews = ProductShortcodes::renderReviews($attributes);
        if ($reviews === '') {
            return '';
        }

        ob_start();
        echo '<section class="petshop-section petshop-reviews" aria-labelledby="petshop-reviews-heading">';
        echo '<h2 id="petshop-reviews-heading" class="wp-block-heading">';
        echo esc_html__('Quem compra, conta', 'petshop-core');
        echo '</h2>';
        echo '<p>';
        echo esc_html__(
            'Avaliações reais e aprovadas dos produtos aparecem nesta seção.',
            'petshop-core'
        );
        echo '</p>';
        echo $reviews; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</section>';

        return (string) ob_get_clean();
    }

    public static function renderProductAssurance(): void
    {
        $title = (string) get_theme_mod('petshop_product_assurance_title', \Petshop\Core\Settings\DefaultSettings::get('petshop_product_assurance_title'));
        $text = (string) get_theme_mod(
            'petshop_product_assurance_text',
            \Petshop\Core\Settings\DefaultSettings::get('petshop_product_assurance_text')
        );

        echo '<div class="petshop-product-assurance" aria-label="' . esc_attr__('Informações de compra', 'petshop-core') . '">';
        echo '<strong>' . esc_html($title) . '</strong>';
        echo '<p>' . esc_html($text) . '</p>';
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function relatedProductArgs(array $args): array
    {
        $args['posts_per_page'] = 4;
        $args['columns'] = 4;

        return $args;
    }
}
