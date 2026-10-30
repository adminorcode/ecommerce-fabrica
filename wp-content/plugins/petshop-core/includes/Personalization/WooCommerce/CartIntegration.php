<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\WooCommerce;

use Petshop\Core\Personalization\Application\AttachToCart;
use Petshop\Core\Personalization\Application\SnapshotOrderItem;
use Petshop\Core\Personalization\Application\TransitionStatus;
use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Domain\PersonalizationStatus;
use Petshop\Core\Personalization\Http\DownloadController;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;
use Petshop\Core\Personalization\Infrastructure\ProductSettings;
use Petshop\Core\Personalization\Infrastructure\SessionIdentity;

defined('ABSPATH') || exit;

/**
 * Cart line binding: one art per line, never merged, never rebuilt from the product.
 */
final class CartIntegration
{
    public const CART_KEY = 'petshop_personalization';

    public static function bootstrap(): void
    {
        add_filter('woocommerce_add_to_cart_validation', [self::class, 'validateAddToCart'], 10, 4);
        add_filter('woocommerce_add_cart_item_data', [self::class, 'addCartItemData'], 10, 3);
        add_filter('woocommerce_get_item_data', [self::class, 'renderItemData'], 10, 2);
        add_filter('woocommerce_cart_item_thumbnail', [self::class, 'renderThumbnail'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'copyToOrderItem'], 10, 3);
        add_action('woocommerce_cart_item_removed', [self::class, 'releaseRemovedItem'], 10, 2);
        add_action('woocommerce_cart_loaded_from_session', [self::class, 'rebindSessionPersonalizations'], 20);
    }

    /**
     * @param array<string, mixed>|null $cartItemData
     */
    public static function validateAddToCart(bool $passed, int $productId, int $quantity = 1, mixed $variationId = 0): bool
    {
        unset($quantity, $variationId);

        if (!$passed || !ProductSettings::isEnabledFor($productId)) {
            return $passed;
        }

        $personalization = self::requestedPersonalization();
        if (!$personalization instanceof Personalization) {
            wc_add_notice(__('Personalize o produto antes de adicionar ao carrinho.', 'petshop-core'), 'error');

            return false;
        }

        if ($personalization->productId !== $productId) {
            wc_add_notice(__('A arte selecionada pertence a outro produto.', 'petshop-core'), 'error');

            return false;
        }

        if (!AttachToCart::isUsableInCart($personalization) || !AttachToCart::isOwnedByCurrentVisitor($personalization)) {
            wc_add_notice(__('A arte personalizada expirou. Refaça a personalização.', 'petshop-core'), 'error');

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $cartItemData
     * @return array<string, mixed>
     */
    public static function addCartItemData(array $cartItemData, int $productId, mixed $variationId = 0): array
    {
        unset($variationId);

        if (!ProductSettings::isEnabledFor($productId)) {
            return $cartItemData;
        }

        $personalization = self::requestedPersonalization();
        if (!$personalization instanceof Personalization || $personalization->productId !== $productId) {
            return $cartItemData;
        }

        try {
            $attached = AttachToCart::handle($personalization);
        } catch (\Throwable $error) {
            error_log('Petshop personalization cart bind falhou: ' . $error->getMessage());

            return $cartItemData;
        }

        $cartItemData[self::CART_KEY] = [
            'public_id' => $attached->publicId,
            'summary' => $attached->textSummary,
            'hash' => $attached->snapshotHash,
            'schema' => $attached->designSchemaVersion,
        ];

        // Distinct arts must never collapse into a single cart line.
        $cartItemData['unique_key'] = $attached->publicId;

        return $cartItemData;
    }

    /**
     * @param list<array<string, mixed>> $itemData
     * @param array<string, mixed> $cartItem
     * @return list<array<string, mixed>>
     */
    public static function renderItemData(array $itemData, array $cartItem): array
    {
        $meta = self::cartItemMeta($cartItem);
        if ($meta === null) {
            return $itemData;
        }

        $itemData[] = [
            'key' => __('Personalização', 'petshop-core'),
            'value' => $meta['summary'],
            'display' => esc_html($meta['summary']),
        ];

        return $itemData;
    }

    /**
     * @param array<string, mixed> $cartItem
     */
    public static function renderThumbnail(string $thumbnail, array $cartItem): string
    {
        $meta = self::cartItemMeta($cartItem);
        if ($meta === null) {
            return $thumbnail;
        }

        $personalization = PersonalizationRepository::findByPublicId($meta['public_id']);
        if (!$personalization instanceof Personalization) {
            return $thumbnail;
        }

        return sprintf(
            '<img src="%s" alt="%s" class="petshop-personalization-thumb" loading="lazy" decoding="async">',
            esc_url(DownloadController::previewUrl($personalization->publicId)),
            esc_attr__('Prévia da personalização', 'petshop-core')
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function copyToOrderItem(\WC_Order_Item_Product $item, string $cartItemKey, array $values): void
    {
        unset($cartItemKey);

        $meta = self::cartItemMeta($values);
        if ($meta === null) {
            return;
        }

        $item->update_meta_data(SnapshotOrderItem::META_PUBLIC_ID, $meta['public_id']);
        $item->update_meta_data(SnapshotOrderItem::META_SUMMARY, $meta['summary']);
        $item->update_meta_data(SnapshotOrderItem::META_HASH, $meta['hash']);
        $item->update_meta_data(SnapshotOrderItem::META_SCHEMA, (string) $meta['schema']);
    }

    /**
     * @param array<string, mixed> $cartItem
     */
    public static function releaseRemovedItem(string $cartItemKey, mixed $cart): void
    {
        unset($cartItemKey);

        if (!$cart instanceof \WC_Cart) {
            return;
        }

        foreach ($cart->get_removed_cart_contents() as $removed) {
            $meta = self::cartItemMeta(is_array($removed) ? $removed : []);
            if ($meta === null) {
                continue;
            }

            $personalization = PersonalizationRepository::findByPublicId($meta['public_id']);
            if ($personalization instanceof Personalization && $personalization->status === PersonalizationStatus::Cart) {
                TransitionStatus::applyIfPossible(
                    $personalization,
                    PersonalizationStatus::Draft,
                    get_current_user_id() ?: null,
                    'Item removido do carrinho.'
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $cartItem
     * @return array{public_id: string, summary: string, hash: string, schema: int}|null
     */
    public static function cartItemMeta(array $cartItem): ?array
    {
        $meta = $cartItem[self::CART_KEY] ?? null;
        if (!is_array($meta)) {
            return null;
        }

        $publicId = (string) ($meta['public_id'] ?? '');
        if (!PersonalizationRepository::isPublicId($publicId)) {
            return null;
        }

        return [
            'public_id' => $publicId,
            'summary' => (string) ($meta['summary'] ?? ''),
            'hash' => (string) ($meta['hash'] ?? ''),
            'schema' => (int) ($meta['schema'] ?? Personalization::DESIGN_SCHEMA_VERSION),
        ];
    }

    /**
     * After login WooCommerce rotates the session customer id. Rebind cart arts
     * so preview URLs and ownership checks keep working.
     */
    public static function rebindSessionPersonalizations(\WC_Cart $cart): void
    {
        $userId = get_current_user_id();
        $sessionHash = SessionIdentity::hash();

        foreach ($cart->get_cart() as $item) {
            $meta = self::cartItemMeta(is_array($item) ? $item : []);
            if ($meta === null) {
                continue;
            }

            $personalization = PersonalizationRepository::findByPublicId($meta['public_id']);
            if (!$personalization instanceof Personalization || $personalization->orderId !== null) {
                continue;
            }

            try {
                PersonalizationRepository::attachToCart(
                    $personalization,
                    $sessionHash,
                    $userId > 0 ? $userId : null
                );
            } catch (\Throwable $error) {
                error_log('Petshop personalization rebind falhou: ' . $error->getMessage());
            }
        }
    }

    private static function requestedPersonalization(): ?Personalization
    {
        $raw = $_REQUEST[EditorSurface::FIELD_NAME] ?? '';
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $publicId = sanitize_text_field(wp_unslash($raw));

        return PersonalizationRepository::findByPublicId($publicId);
    }
}
