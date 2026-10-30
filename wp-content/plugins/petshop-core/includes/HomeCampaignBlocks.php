<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

final class HomeCampaignBlocks
{
    use HomeCampaignAssets;

    public const MAX_CAMPAIGNS = 3;
    public const DEFAULT_DURATION_SECONDS = 10;
    public const MIN_DURATION_SECONDS = 3;
    public const MAX_DURATION_SECONDS = 60;

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerBlocks']);
        add_filter('block_categories_all', [self::class, 'registerBlockCategory'], 10, 2);
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    public static function registerBlockCategory(array $categories, \WP_Block_Editor_Context $context): array
    {
        unset($context);

        foreach ($categories as $category) {
            if (($category['slug'] ?? '') === 'petshop') {
                return $categories;
            }
        }

        return array_merge(
            [
                [
                    'slug' => 'petshop',
                    'title' => __('Petshop', 'petshop-core'),
                    'icon' => 'store',
                ],
            ],
            $categories
        );
    }

    public static function registerBlocks(): void
    {
        $base = plugin_dir_path(PETSHOP_CORE_FILE) . 'blocks/build/';
        self::registerEditorScript('petshop-home-campaign-editor', 'home-campaign.js', $base);
        self::registerEditorScript('petshop-home-campaigns-editor', 'home-campaigns.js', $base);
        self::registerViewScript('petshop-home-campaigns-view', 'view.js', $base);

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('petshop-home-campaign-editor', 'petshop-core');
            wp_set_script_translations('petshop-home-campaigns-editor', 'petshop-core');
        }

        register_block_type(
            'petshop/home-campaign',
            [
                'api_version' => 3,
                'title' => __('Banner de campanha', 'petshop-core'),
                'category' => 'petshop',
                'parent' => ['petshop/home-campaigns'],
                'icon' => 'format-image',
                'description' => __('Imagem de campanha com link de destino para a Home.', 'petshop-core'),
                'attributes' => self::campaignAttributes(),
                'supports' => [
                    'html' => false,
                    'reusable' => false,
                    'lock' => false,
                ],
                'editor_script' => 'petshop-home-campaign-editor',
                'render_callback' => [self::class, 'renderCampaignBlock'],
            ]
        );

        register_block_type(
            'petshop/home-campaigns',
            [
                'api_version' => 3,
                'title' => __('Banners de campanha', 'petshop-core'),
                'category' => 'petshop',
                'icon' => 'images-alt2',
                'description' => __('Faixa de até 3 banners de campanha em carrossel na Home.', 'petshop-core'),
                'supports' => [
                    'html' => false,
                    'align' => ['wide', 'full'],
                ],
                'editor_script' => 'petshop-home-campaigns-editor',
                'view_script' => 'petshop-home-campaigns-view',
                'render_callback' => [self::class, 'renderCampaignsBlock'],
            ]
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function campaignAttributes(): array
    {
        return [
            'campaignMode' => ['type' => 'string', 'default' => 'artwork'],
            'desktopImageId' => ['type' => 'number', 'default' => 0],
            'desktopImageUrl' => ['type' => 'string', 'default' => ''],
            'mobileImageId' => ['type' => 'number', 'default' => 0],
            'mobileImageUrl' => ['type' => 'string', 'default' => ''],
            'imageAlt' => ['type' => 'string', 'default' => ''],
            'linkUrl' => ['type' => 'string', 'default' => ''],
            'editorLabel' => ['type' => 'string', 'default' => ''],
            'eyebrow' => ['type' => 'string', 'default' => ''],
            'title' => ['type' => 'string', 'default' => ''],
            'text' => ['type' => 'string', 'default' => ''],
            'benefit' => ['type' => 'string', 'default' => ''],
            'ctaLabel' => ['type' => 'string', 'default' => ''],
            'durationSeconds' => ['type' => 'number', 'default' => self::DEFAULT_DURATION_SECONDS],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderCampaignBlock(array $attributes, string $content, \WP_Block $block): string
    {
        unset($content, $block);

        return '';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderCampaignsBlock(array $attributes, string $content, \WP_Block $block): string
    {
        unset($attributes, $content);

        $campaigns = self::collectValidCampaigns($block->inner_blocks);
        if ($campaigns === []) {
            return '';
        }

        $isCarousel = count($campaigns) > 1;
        $classes = ['petshop-home-campaigns', 'petshop-section'];
        if ($isCarousel) {
            $classes[] = 'is-carousel';
        }

        $wrapperAttributes = get_block_wrapper_attributes([
            'class' => implode(' ', $classes),
            'aria-label' => esc_attr__('Banners de campanha', 'petshop-core'),
        ]);

        $slides = [];
        foreach ($campaigns as $index => $campaign) {
            $slides[] = self::renderSlide($campaign, $isCarousel && $index > 0, $index);
        }

        $track = '<div class="petshop-home-campaigns__track">' . implode('', $slides) . '</div>';
        $controls = $isCarousel ? self::renderControls(count($campaigns)) : '';

        return sprintf(
            '<section %1$s><div class="petshop-home-campaigns__inner">%2$s%3$s</div></section>',
            $wrapperAttributes,
            $track,
            $controls
        );
    }

    /**
     * @param iterable<int, \WP_Block|null> $innerBlocks
     * @return array<int, array<string, mixed>>
     */
    private static function collectValidCampaigns(iterable $innerBlocks): array
    {
        $campaigns = [];

        foreach ($innerBlocks as $innerBlock) {
            if (!$innerBlock instanceof \WP_Block || $innerBlock->name !== 'petshop/home-campaign') {
                continue;
            }

            $attrs = is_array($innerBlock->attributes ?? null) ? $innerBlock->attributes : [];
            if (!self::isValidCampaign($attrs)) {
                continue;
            }

            $campaigns[] = self::normalizeCampaign($attrs);

            if (count($campaigns) >= self::MAX_CAMPAIGNS) {
                break;
            }
        }

        return $campaigns;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function isValidCampaign(array $attributes): bool
    {
        $mode = self::campaignMode($attributes);
        $desktopId = (int) ($attributes['desktopImageId'] ?? 0);
        $alt = trim((string) ($attributes['imageAlt'] ?? ''));
        $link = esc_url_raw((string) ($attributes['linkUrl'] ?? ''));

        if ($mode === 'editorial') {
            $title = trim((string) ($attributes['title'] ?? ''));
            $ctaLabel = trim((string) ($attributes['ctaLabel'] ?? ''));

            return $desktopId > 0 && $alt !== '' && $title !== '' && $ctaLabel !== '' && $link !== '';
        }

        return $desktopId > 0 && $alt !== '' && $link !== '';
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private static function normalizeCampaign(array $attributes): array
    {
        $desktopId = (int) ($attributes['desktopImageId'] ?? 0);
        $mobileId = (int) ($attributes['mobileImageId'] ?? 0);
        $desktopUrl = self::resolveImageUrl($desktopId, (string) ($attributes['desktopImageUrl'] ?? ''));
        $mobileUrl = $mobileId > 0
            ? self::resolveImageUrl($mobileId, (string) ($attributes['mobileImageUrl'] ?? ''))
            : '';

        return [
            'mode' => self::campaignMode($attributes),
            'desktopUrl' => $desktopUrl,
            'mobileUrl' => $mobileUrl,
            'alt' => sanitize_text_field((string) ($attributes['imageAlt'] ?? '')),
            'link' => esc_url((string) ($attributes['linkUrl'] ?? '')),
            'eyebrow' => sanitize_text_field((string) ($attributes['eyebrow'] ?? '')),
            'title' => sanitize_text_field((string) ($attributes['title'] ?? '')),
            'text' => sanitize_textarea_field((string) ($attributes['text'] ?? '')),
            'benefit' => sanitize_text_field((string) ($attributes['benefit'] ?? '')),
            'ctaLabel' => sanitize_text_field((string) ($attributes['ctaLabel'] ?? '')),
            'durationSeconds' => self::sanitizeDurationSeconds($attributes['durationSeconds'] ?? null),
        ];
    }

    public static function sanitizeDurationSeconds(mixed $value): int
    {
        if (!is_numeric($value)) {
            return self::DEFAULT_DURATION_SECONDS;
        }

        $seconds = (int) $value;
        if ($seconds < 1) {
            return self::DEFAULT_DURATION_SECONDS;
        }

        return max(self::MIN_DURATION_SECONDS, min(self::MAX_DURATION_SECONDS, $seconds));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function campaignMode(array $attributes): string
    {
        return ($attributes['campaignMode'] ?? 'artwork') === 'editorial' ? 'editorial' : 'artwork';
    }

    private static function resolveImageUrl(int $attachmentId, string $fallbackUrl): string
    {
        if ($attachmentId > 0) {
            $resolved = wp_get_attachment_image_url($attachmentId, 'full');
            if (is_string($resolved) && $resolved !== '') {
                return $resolved;
            }
        }

        $fallbackUrl = esc_url_raw($fallbackUrl);

        return $fallbackUrl !== '' ? $fallbackUrl : '';
    }

    /**
     * @param array<string, mixed> $campaign
     */
    private static function renderSlide(array $campaign, bool $hidden, int $index): string
    {
        $desktopUrl = (string) ($campaign['desktopUrl'] ?? '');
        $mobileUrl = (string) ($campaign['mobileUrl'] ?? '');
        $alt = (string) ($campaign['alt'] ?? '');
        $link = (string) ($campaign['link'] ?? '');

        if ($desktopUrl === '' || $alt === '' || $link === '') {
            return '';
        }

        if (($campaign['mode'] ?? 'artwork') === 'editorial') {
            return self::renderEditorialSlide($campaign, $hidden, $index);
        }

        $picture = self::renderPicture($desktopUrl, $mobileUrl, $alt, $index > 0);
        $hiddenAttribute = $hidden ? ' hidden' : '';
        $durationAttribute = sprintf(
            ' data-duration-seconds="%d"',
            (int) ($campaign['durationSeconds'] ?? self::DEFAULT_DURATION_SECONDS)
        );

        return sprintf(
            '<div class="petshop-home-campaigns__slide"%1$s%2$s><a class="petshop-home-campaigns__link" href="%3$s">%4$s</a></div>',
            $hiddenAttribute,
            $durationAttribute,
            esc_url($link),
            $picture
        );
    }

    /**
     * @param array<string, mixed> $campaign
     */
    private static function renderEditorialSlide(array $campaign, bool $hidden, int $index): string
    {
        $desktopUrl = (string) ($campaign['desktopUrl'] ?? '');
        $mobileUrl = (string) ($campaign['mobileUrl'] ?? '');
        $alt = (string) ($campaign['alt'] ?? '');
        $link = (string) ($campaign['link'] ?? '');
        $title = (string) ($campaign['title'] ?? '');
        $ctaLabel = (string) ($campaign['ctaLabel'] ?? '');

        if ($desktopUrl === '' || $alt === '' || $link === '' || $title === '' || $ctaLabel === '') {
            return '';
        }

        $eyebrow = trim((string) ($campaign['eyebrow'] ?? ''));
        $text = trim((string) ($campaign['text'] ?? ''));
        $benefit = trim((string) ($campaign['benefit'] ?? ''));
        $picture = self::renderPicture($desktopUrl, $mobileUrl, $alt, $index > 0);
        $hiddenAttribute = $hidden ? ' hidden' : '';
        $durationAttribute = sprintf(
            ' data-duration-seconds="%d"',
            (int) ($campaign['durationSeconds'] ?? self::DEFAULT_DURATION_SECONDS)
        );

        return sprintf(
            '<div class="petshop-home-campaigns__slide petshop-home-campaigns__slide--editorial"%1$s%2$s>'
            . '<article class="petshop-home-campaigns__editorial">'
            . '<div class="petshop-home-campaigns__content">%3$s<h2 class="petshop-home-campaigns__title">%4$s</h2>%5$s%6$s'
            . '<a class="petshop-home-campaigns__cta" href="%7$s">%8$s</a></div>'
            . '<figure class="petshop-home-campaigns__media">%9$s</figure>'
            . '</article></div>',
            $hiddenAttribute,
            $durationAttribute,
            $eyebrow !== '' ? '<p class="petshop-home-campaigns__eyebrow">' . esc_html($eyebrow) . '</p>' : '',
            esc_html($title),
            $text !== '' ? '<p class="petshop-home-campaigns__text">' . esc_html($text) . '</p>' : '',
            $benefit !== '' ? '<p class="petshop-home-campaigns__benefit">' . esc_html($benefit) . '</p>' : '',
            esc_url($link),
            esc_html($ctaLabel),
            $picture
        );
    }

    private static function renderPicture(string $desktopUrl, string $mobileUrl, string $alt, bool $lazy = true): string
    {
        $altAttribute = esc_attr($alt);
        $desktopSrc = esc_url($desktopUrl);
        $loading = $lazy ? 'lazy' : 'eager';

        if ($mobileUrl !== '') {
            $mobileSrc = esc_url($mobileUrl);

            return sprintf(
                '<picture><source media="(max-width: 767px)" srcset="%1$s"><img src="%2$s" alt="%3$s" loading="%4$s" decoding="async"></picture>',
                $mobileSrc,
                $desktopSrc,
                $altAttribute,
                $loading
            );
        }

        return sprintf(
            '<img src="%1$s" alt="%2$s" loading="%3$s" decoding="async">',
            $desktopSrc,
            $altAttribute,
            $loading
        );
    }

    private static function renderControls(int $total): string
    {
        $prevLabel = esc_attr__('Banner anterior', 'petshop-core');
        $nextLabel = esc_attr__('Próximo banner', 'petshop-core');
        $statusLabel = esc_html__('Banner ativo', 'petshop-core');
        $dots = [];

        for ($index = 0; $index < $total; $index++) {
            $slideNumber = $index + 1;
            $label = esc_attr(
                sprintf(
                    /* translators: %d: slide number */
                    __('Ir para o banner %d', 'petshop-core'),
                    $slideNumber
                )
            );
            $selected = $index === 0 ? 'true' : 'false';
            $activeClass = $index === 0 ? ' is-active' : '';
            $dots[] = sprintf(
                '<button type="button" role="tab" class="petshop-home-campaigns__dot%1$s" aria-label="%2$s" aria-selected="%3$s" data-index="%4$d"></button>',
                $activeClass,
                $label,
                $selected,
                $index
            );
        }

        $chevronPrev = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14.5 5.5 8 12l6.5 6.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $chevronNext = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9.5 5.5 16 12l-6.5 6.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        return sprintf(
            '<div class="petshop-home-campaigns__controls" aria-label="%1$s">'
            . '<button type="button" class="petshop-home-campaigns__prev" aria-label="%2$s">%3$s</button>'
            . '<div class="petshop-home-campaigns__dots" role="tablist">%4$s</div>'
            . '<button type="button" class="petshop-home-campaigns__next" aria-label="%5$s">%6$s</button>'
            . '<p class="petshop-home-campaigns__status screen-reader-text" aria-live="polite" aria-atomic="true">%7$s</p>'
            . '</div>',
            esc_attr__('Navegação dos banners de campanha', 'petshop-core'),
            $prevLabel,
            $chevronPrev,
            implode('', $dots),
            $nextLabel,
            $chevronNext,
            $statusLabel
        );
    }

    public static function initialCampaignsBlockMarkup(int $imageId, string $linkUrl): string
    {
        if ($imageId <= 0) {
            return '';
        }

        $imageUrl = wp_get_attachment_image_url($imageId, 'full');
        $linkUrl = esc_url_raw($linkUrl);
        if (!is_string($imageUrl) || $imageUrl === '' || $linkUrl === '') {
            return '';
        }

        $attrs = wp_json_encode(
            [
                'campaignMode' => 'artwork',
                'desktopImageId' => $imageId,
                'desktopImageUrl' => $imageUrl,
                'mobileImageId' => 0,
                'mobileImageUrl' => '',
                'imageAlt' => __(
                    'Campanha demonstrativa — substitua pela imagem e link da sua promoção.',
                    'petshop-core'
                ),
                'linkUrl' => $linkUrl,
                'editorLabel' => __('Campanha demonstrativa', 'petshop-core'),
                'durationSeconds' => self::DEFAULT_DURATION_SECONDS,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return '<!-- wp:petshop/home-campaigns -->'
            . '<!-- wp:petshop/home-campaign ' . $attrs . ' /-->'
            . '<!-- /wp:petshop/home-campaigns -->';
    }

    public static function insertCampaignsIfMissing(string $content, int $imageId, string $linkUrl): string
    {
        if (str_contains($content, 'petshop/home-campaigns')) {
            return $content;
        }

        $block = self::initialCampaignsBlockMarkup($imageId, $linkUrl);
        if ($block === '') {
            return $content;
        }

        $anchor = '<h2 class="wp-block-heading">Compre por categoria</h2>';
        $position = strpos($content, $anchor);
        if ($position === false) {
            return $content;
        }

        $before = substr($content, 0, $position);
        $benefitsEnd = strrpos($before, '<!-- /wp:group -->');
        $insertAt = $benefitsEnd === false
            ? $position
            : $benefitsEnd + strlen('<!-- /wp:group -->');

        return substr($content, 0, $insertAt) . "\n" . $block . "\n" . substr($content, $insertAt);
    }
}
