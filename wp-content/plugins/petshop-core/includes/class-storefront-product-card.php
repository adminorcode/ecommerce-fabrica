<?php

declare(strict_types=1);

namespace Petshop\Core;

defined('ABSPATH') || exit;

final class StorefrontProductCard
{
    /**
     * Limiar mínimo de vendas reais (meta total_sales) para exibir "Mais pedido".
     */
    private const BEST_SELLER_MIN_SALES = 5;

    private const PERSONALIZATION_QUERY_FLAG = 'petshop_personalize';

    private static bool $insideLoopCard = false;

    /**
     * @var array<int, array<string, mixed>|null>
     */
    private static array $variableDataCache = [];

    public static function bootstrap(): void
    {
        add_filter('blocksy:woocommerce:product-card:badges', [self::class, 'filterBlocksyBadges'], 50);
        add_action('woocommerce_before_shop_loop_item_title', [self::class, 'renderBadges'], 9);

        add_action('woocommerce_before_shop_loop_item', [self::class, 'beginLoopCard'], 0);
        add_action('woocommerce_after_shop_loop_item', [self::class, 'endLoopCard'], 999);
        add_filter('woocommerce_get_price_html', [self::class, 'filterLoopPriceHtml'], 20, 2);
        add_action('woocommerce_after_shop_loop_item_title', [self::class, 'renderVariationControls'], 12);
        add_filter('woocommerce_loop_add_to_cart_link', [self::class, 'filterLoopAddToCartLink'], 20, 3);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function beginLoopCard(): void
    {
        self::$insideLoopCard = true;
    }

    public static function endLoopCard(): void
    {
        self::$insideLoopCard = false;
    }

    public static function enqueueAssets(): void
    {
        if (is_admin() || !class_exists('WooCommerce')) {
            return;
        }

        $base = plugin_dir_path(PETSHOP_CORE_FILE);
        $scriptRelative = 'assets/js/product-card.js';
        $scriptPath = $base . $scriptRelative;

        wp_enqueue_script(
            'petshop-product-card',
            plugins_url($scriptRelative, PETSHOP_CORE_FILE),
            [],
            is_file($scriptPath) ? (string) filemtime($scriptPath) : '1.0.0',
            true
        );


        wp_localize_script('petshop-product-card', 'petshopProductCardConfig', [
            'endpoint' => wp_make_link_relative(rest_url('wc/store/v1/cart/add-item')),
            'nonce' => wp_create_nonce('wc_store_api'),
            'i18n' => [
                'buyNow' => __('Comprar agora', 'petshop-core'),
                'adding' => __('Adicionando…', 'petshop-core'),
                'added' => __('Adicionado ao carrinho.', 'petshop-core'),
                'selectOption' => __('Selecione uma opção disponível.', 'petshop-core'),
                'unavailable' => __('Esta combinação está indisponível.', 'petshop-core'),
                'error' => __('Não foi possível adicionar ao carrinho.', 'petshop-core'),
            ],
        ]);
    }

    /**
     * @param string[] $badges
     * @return string[]
     */
    public static function filterBlocksyBadges(array $badges): array
    {
        $badges = array_values(array_filter(
            $badges,
            static fn (string $badge): bool => !str_contains($badge, 'onsale')
        ));

        $markup = self::buildBadgesMarkup();
        if ($markup !== '') {
            $badges[] = $markup;
        }

        return $badges;
    }

    public static function renderBadges(): void
    {
        if (self::isBlocksyProductCard()) {
            return;
        }

        echo self::buildBadgesMarkup();
    }

    public static function filterLoopPriceHtml(string $html, \WC_Product $product): string
    {
        if (!self::$insideLoopCard || !$product instanceof \WC_Product_Variable) {
            return $html;
        }

        $data = self::variableCardData($product);
        if ($data === null) {
            return $html;
        }

        return '<span class="petshop-product-card__selected-price" data-petshop-card-price>'
            . wp_kses_post((string) $data['initialPriceHtml'])
            . '</span>';
    }

    public static function renderVariationControls(): void
    {
        global $product;

        if (!$product instanceof \WC_Product_Variable) {
            return;
        }

        $data = self::variableCardData($product);
        if ($data === null) {
            return;
        }

        $json = wp_json_encode($data['variations'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }

        echo '<div class="petshop-product-card__variations" data-petshop-variable-card'
            . ' data-product-id="' . esc_attr((string) $product->get_id()) . '"'
            . ' data-initial-variation-id="' . esc_attr((string) $data['initialVariationId']) . '"'
            . ' data-variations="' . esc_attr($json) . '">';

        foreach ($data['attributes'] as $attribute) {
            $key = (string) $attribute['key'];
            $label = (string) $attribute['label'];
            $selected = (string) ($data['initialSelection'][$key] ?? '');

            echo '<fieldset class="petshop-product-card__attribute" data-petshop-attribute-group="' . esc_attr($key) . '">';
            echo '<legend class="petshop-product-card__attribute-label">' . esc_html($label) . '</legend>';
            echo '<div class="petshop-product-card__chips">';

            foreach ($attribute['options'] as $option) {
                $value = (string) $option['value'];
                $optionLabel = (string) $option['label'];
                $isSelected = $selected !== '' && $selected === $value;

                echo '<button type="button" class="petshop-product-card__chip' . ($isSelected ? ' is-selected' : '') . '"'
                    . ' data-petshop-attribute="' . esc_attr($key) . '"'
                    . ' data-value="' . esc_attr($value) . '"'
                    . ' aria-pressed="' . ($isSelected ? 'true' : 'false') . '">'
                    . esc_html($optionLabel)
                    . '</button>';
            }

            echo '</div></fieldset>';
        }

        echo '<p class="petshop-product-card__status" data-petshop-card-status role="status" aria-live="polite"></p>';
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function filterLoopAddToCartLink(string $html, \WC_Product $product, array $args): string
    {
        unset($args);

        if (!$product->is_type(['simple', 'variable'])) {
            return $html;
        }

        $productId = $product->get_id();
        $label = __('Comprar agora', 'petshop-core');

        if (\Petshop\Core\Personalization\Infrastructure\ProductSettings::isEnabledFor($productId)) {
            $url = add_query_arg(
                self::PERSONALIZATION_QUERY_FLAG,
                '1',
                (string) get_permalink($productId)
            );

            return '<a class="button petshop-product-card__buy-now petshop-product-card__buy-now--personalizable"'
                . ' href="' . esc_url($url) . '"'
                . ' data-petshop-personalizable="1"'
                . ' aria-label="' . esc_attr(sprintf(__('Personalizar %s', 'petshop-core'), $product->get_name())) . '">'
                . esc_html($label)
                . '</a>';
        }

        $disabled = false;
        if ($product instanceof \WC_Product_Simple) {
            $disabled = !$product->is_purchasable() || !$product->is_in_stock();
        } elseif ($product instanceof \WC_Product_Variable) {
            $disabled = self::variableCardData($product) === null;
        }

        return '<button type="button" class="button petshop-product-card__buy-now"'
            . ' data-petshop-buy-now="1"'
            . ' data-product-id="' . esc_attr((string) $productId) . '"'
            . ($product instanceof \WC_Product_Variable ? ' data-product-type="variable"' : ' data-product-type="simple"')
            . ($disabled ? ' disabled aria-disabled="true"' : '')
            . ' aria-label="' . esc_attr(sprintf(__('Comprar %s agora', 'petshop-core'), $product->get_name())) . '">'
            . esc_html($label)
            . '</button>';
    }

    private static function buildBadgesMarkup(): string
    {
        global $product;

        if (!$product instanceof \WC_Product) {
            return '';
        }

        $labels = [];

        if ($product->is_on_sale()) {
            $regular = (float) $product->get_regular_price();
            $sale = (float) $product->get_sale_price();
            if ($regular > 0 && $sale > 0 && $sale < $regular) {
                $percent = (int) round((($regular - $sale) / $regular) * 100);
                if ($percent > 0) {
                    $labels[] = [
                        'class' => 'petshop-badge petshop-badge--save',
                        'text' => sprintf(
                            __('Economize %d%%', 'petshop-core'),
                            $percent
                        ),
                    ];
                }
            }
        }

        if ((int) $product->get_total_sales() >= self::BEST_SELLER_MIN_SALES) {
            $labels[] = [
                'class' => 'petshop-badge petshop-badge--bestseller',
                'text' => __('Mais pedido', 'petshop-core'),
            ];
        }

        if ($labels === []) {
            return '';
        }

        $html = '<div class="petshop-product-card__badges">';
        foreach ($labels as $label) {
            $html .= '<span class="' . esc_attr($label['class']) . '">' . esc_html($label['text']) . '</span>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function variableCardData(\WC_Product_Variable $product): ?array
    {
        $productId = $product->get_id();
        if (array_key_exists($productId, self::$variableDataCache)) {
            return self::$variableDataCache[$productId];
        }

        $attributes = [];
        foreach ($product->get_variation_attributes() as $attributeName => $options) {
            $key = sanitize_title((string) $attributeName);
            $items = [];

            foreach ($options as $option) {
                $value = (string) $option;
                $label = $value;

                if (taxonomy_exists((string) $attributeName)) {
                    $term = get_term_by('slug', $value, (string) $attributeName);
                    if ($term instanceof \WP_Term) {
                        $label = $term->name;
                    }
                }

                $items[] = [
                    'value' => $value,
                    'label' => $label,
                ];
            }

            $attributes[] = [
                'key' => $key,
                'label' => wc_attribute_label((string) $attributeName, $product),
                'options' => $items,
            ];
        }

        $variations = [];
        $initialVariation = null;
        $initialPrice = null;

        foreach ($product->get_children() as $variationId) {
            $variation = wc_get_product($variationId);
            if (!$variation instanceof \WC_Product_Variation || $variation->get_status() !== 'publish') {
                continue;
            }

            $normalizedAttributes = [];
            foreach ($variation->get_attributes() as $name => $value) {
                $normalizedAttributes[sanitize_title((string) $name)] = (string) $value;
            }

            $image = '';
            $imageId = $variation->get_image_id();
            if ($imageId > 0) {
                $image = (string) wp_get_attachment_image_url($imageId, 'woocommerce_thumbnail');
            }

            $price = $variation->get_price();
            $isPurchasable = $variation->is_purchasable();
            $isInStock = $variation->is_in_stock();

            $variations[] = [
                'id' => $variation->get_id(),
                'attributes' => $normalizedAttributes,
                'priceHtml' => $variation->get_price_html(),
                'image' => $image,
                'purchasable' => $isPurchasable,
                'inStock' => $isInStock,
            ];

            if (!$isPurchasable || !$isInStock || $price === '') {
                continue;
            }

            $numericPrice = (float) $price;
            if ($initialVariation === null || $initialPrice === null || $numericPrice < $initialPrice) {
                $initialVariation = $variation;
                $initialPrice = $numericPrice;
            }
        }

        if (!$initialVariation instanceof \WC_Product_Variation || $variations === []) {
            self::$variableDataCache[$productId] = null;
            return null;
        }

        $initialSelection = [];
        $initialAttributes = [];
        foreach ($initialVariation->get_attributes() as $name => $value) {
            $initialAttributes[sanitize_title((string) $name)] = (string) $value;
        }

        foreach ($attributes as $attribute) {
            $key = (string) $attribute['key'];
            $value = (string) ($initialAttributes[$key] ?? '');
            if ($value === '' && !empty($attribute['options'][0]['value'])) {
                $value = (string) $attribute['options'][0]['value'];
            }
            $initialSelection[$key] = $value;
        }

        self::$variableDataCache[$productId] = [
            'attributes' => $attributes,
            'variations' => $variations,
            'initialVariationId' => $initialVariation->get_id(),
            'initialPriceHtml' => $initialVariation->get_price_html(),
            'initialSelection' => $initialSelection,
        ];

        return self::$variableDataCache[$productId];
    }

    private static function isBlocksyProductCard(): bool
    {
        return function_exists('blocksy_get_theme_mod');
    }
}
