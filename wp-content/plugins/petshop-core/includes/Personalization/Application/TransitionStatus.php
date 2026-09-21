<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Application;

use Petshop\Core\Personalization\Domain\InvalidStatusTransition;
use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Domain\PersonalizationStatus;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;

defined('ABSPATH') || exit;

/**
 * Applies a status transition, persisting the new state and its history entry.
 */
final class TransitionStatus
{
    public static function apply(
        Personalization $personalization,
        PersonalizationStatus $target,
        ?int $actorUserId = null,
        string $note = ''
    ): Personalization {
        $from = $personalization->status;
        $updated = $personalization->withStatus($target);

        PersonalizationRepository::persistStatus($updated, $from, $actorUserId, $note);

        /**
         * Fires after a personalization changed state.
         *
         * @param Personalization       $updated New aggregate state.
         * @param PersonalizationStatus $from    Previous state.
         */
        do_action('petshop_personalization_status_changed', $updated, $from);

        return $updated;
    }

    /**
     * Idempotent variant used by WooCommerce event listeners and webhooks.
     *
     * Returns null when the transition is not allowed or already applied.
     */
    public static function applyIfPossible(
        Personalization $personalization,
        PersonalizationStatus $target,
        ?int $actorUserId = null,
        string $note = ''
    ): ?Personalization {
        if ($personalization->status === $target) {
            return null;
        }

        if (!$personalization->status->canTransitionTo($target)) {
            return null;
        }

        try {
            return self::apply($personalization, $target, $actorUserId, $note);
        } catch (InvalidStatusTransition) {
            return null;
        }
    }
}
