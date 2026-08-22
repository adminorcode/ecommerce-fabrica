<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Application;

use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Domain\PersonalizationStatus;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;
use Petshop\Core\Personalization\Infrastructure\SessionIdentity;

defined('ABSPATH') || exit;

/**
 * Binds a draft to the current cart session (idempotent).
 */
final class AttachToCart
{
    public static function handle(Personalization $personalization): Personalization
    {
        if (!self::isOwnedByCurrentVisitor($personalization)) {
            throw new \RuntimeException(__('Esta personalização não pertence à sessão atual.', 'petshop-core'));
        }

        $userId = get_current_user_id();
        $attached = PersonalizationRepository::attachToCart($personalization, SessionIdentity::hash(), $userId > 0 ? $userId : null);

        if ($attached->status === PersonalizationStatus::Cart) {
            return $attached;
        }

        $moved = TransitionStatus::applyIfPossible(
            $attached,
            PersonalizationStatus::Cart,
            $userId > 0 ? $userId : null,
            'Item adicionado ao carrinho.'
        );

        return $moved ?? $attached;
    }

    public static function isOwnedByCurrentVisitor(Personalization $personalization): bool
    {
        $userId = get_current_user_id();
        if ($personalization->userId !== null && $personalization->userId > 0) {
            return $personalization->userId === $userId;
        }

        if (SessionIdentity::matchesCurrentSession($personalization->cartHash)) {
            return true;
        }

        // Guest art later present in the logged-in cart after WooCommerce merges sessions.
        if ($userId > 0 && self::isBoundInCurrentCart($personalization->publicId)) {
            return true;
        }

        // A draft created before the WooCommerce session existed has no owner yet.
        return $personalization->cartHash === null && $personalization->status === PersonalizationStatus::Draft;
    }

    public static function isUsableInCart(Personalization $personalization): bool
    {
        return in_array($personalization->status, [PersonalizationStatus::Draft, PersonalizationStatus::Cart], true)
            && $personalization->orderId === null;
    }

    private static function isBoundInCurrentCart(string $publicId): bool
    {
        if (!PersonalizationRepository::isPublicId($publicId) || !function_exists('WC') || !WC()->cart instanceof \WC_Cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $item) {
            if (!is_array($item)) {
                continue;
            }
            $meta = $item['petshop_personalization'] ?? null;
            if (!is_array($meta)) {
                continue;
            }
            $candidate = (string) ($meta['public_id'] ?? '');
            if ($candidate !== '' && hash_equals($candidate, $publicId)) {
                return true;
            }
        }

        return false;
    }
}
