<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Infrastructure;

defined('ABSPATH') || exit;

/**
 * Derives a stable, non-reversible identifier for the current WooCommerce session.
 *
 * The raw session cookie/customer id never reaches the database.
 */
final class SessionIdentity
{
    public static function hash(): ?string
    {
        if (!function_exists('WC')) {
            return null;
        }

        $wc = WC();
        if (!is_object($wc) || !isset($wc->session) || !is_object($wc->session)) {
            return null;
        }

        $customerId = (string) $wc->session->get_customer_id();
        if ($customerId === '') {
            return null;
        }

        return hash_hmac('sha256', $customerId, wp_salt('auth'));
    }

    public static function matchesCurrentSession(?string $cartHash): bool
    {
        if ($cartHash === null || $cartHash === '') {
            return false;
        }

        $current = self::hash();

        return $current !== null && hash_equals($cartHash, $current);
    }
}
