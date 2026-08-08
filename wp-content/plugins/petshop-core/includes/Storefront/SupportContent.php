<?php

declare(strict_types=1);

namespace Petshop\Core\Storefront;

use Petshop\Core\HomeCampaignBlocks;
use Petshop\Core\Provisioning\StorefrontProvisioner;

defined('ABSPATH') || exit;

final class SupportContent
{
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

    public static function renderSupportBanner(): string
    {
        $imageId = (int) get_theme_mod('petshop_support_banner_image', 0);
        if ($imageId <= 0) {
            $imageId = StorefrontProvisioner::supportBannerAttachment();
        }
        if ($imageId <= 0) {
            return '';
        }

        $url = self::resolveSupportBannerUrl();
        if ($url === '') {
            return '';
        }

        $markup = self::supportBannerContent($imageId, $url);
        if ($markup === '') {
            return '';
        }

        return do_blocks($markup);
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
