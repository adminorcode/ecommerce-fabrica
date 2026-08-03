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

    public static function bootstrap(): void
    {
        add_filter('blocksy:woocommerce:product-card:badges', [self::class, 'filterBlocksyBadges'], 50);
        add_action('woocommerce_before_shop_loop_item_title', [self::class, 'renderBadges'], 9);
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

    private static function isBlocksyProductCard(): bool
    {
        return function_exists('blocksy_get_theme_mod');
    }
}
