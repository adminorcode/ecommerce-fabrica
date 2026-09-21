<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Infrastructure;

use Petshop\Core\Personalization\Application\TransitionStatus;
use Petshop\Core\Personalization\Domain\PersonalizationStatus;

defined('ABSPATH') || exit;

/**
 * Idempotent retention: abandon drafts, cancel stale carts, and soft-purge
 * files of cancelled/completed personalizations past their retention window.
 */
final class CleanupScheduler
{
    public const HOOK = 'petshop_personalization_cleanup';

    public static function bootstrap(): void
    {
        add_action(self::HOOK, [self::class, 'runScheduled']);
        add_action('init', [self::class, 'ensureScheduled'], 20);
    }

    public static function ensureScheduled(): void
    {
        if (wp_next_scheduled(self::HOOK) === false) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
        }
    }

    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        while (is_int($timestamp)) {
            wp_unschedule_event($timestamp, self::HOOK);
            $timestamp = wp_next_scheduled(self::HOOK);
        }
    }

    public static function runScheduled(): void
    {
        try {
            self::run(false);
        } catch (\Throwable $error) {
            error_log('Petshop personalization cleanup falhou: ' . $error->getMessage());
        }
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   draft_cutoff: string,
     *   cart_cutoff: string,
     *   cancelled_cutoff: string,
     *   completed_cutoff: string,
     *   expired_drafts: list<array{public_id: string, product_id: int, updated_at: string}>,
     *   stale_cart_items: list<array{public_id: string, product_id: int, updated_at: string}>,
     *   cancelled_file_candidates: list<array{public_id: string, product_id: int, updated_at: string}>,
     *   completed_file_candidates: list<array{public_id: string, product_id: int, updated_at: string}>,
     *   deleted: int,
     *   cancelled: int,
     *   files_purged: int,
     *   errors: list<string>
     * }
     */
    public static function run(bool $dryRun = true, int $limit = 200): array
    {
        $draftCutoff = RetentionPolicy::draftCutoffGmt();
        $cartCutoff = RetentionPolicy::cartCutoffGmt();
        $cancelledCutoff = RetentionPolicy::cancelledCutoffGmt();
        $completedCutoff = RetentionPolicy::completedCutoffGmt();

        $drafts = PersonalizationRepository::expiredByStatus(PersonalizationStatus::Draft, $draftCutoff, $limit);
        $cartItems = PersonalizationRepository::expiredByStatus(PersonalizationStatus::Cart, $cartCutoff, $limit);
        $cancelledFiles = PersonalizationRepository::withActiveFilesPastRetention(
            PersonalizationStatus::Cancelled,
            $cancelledCutoff,
            $limit
        );
        $completedFiles = PersonalizationRepository::withActiveFilesPastRetention(
            PersonalizationStatus::Completed,
            $completedCutoff,
            $limit
        );

        $report = [
            'dry_run' => $dryRun,
            'draft_cutoff' => $draftCutoff,
            'cart_cutoff' => $cartCutoff,
            'cancelled_cutoff' => $cancelledCutoff,
            'completed_cutoff' => $completedCutoff,
            'expired_drafts' => array_map([self::class, 'describe'], $drafts),
            'stale_cart_items' => array_map([self::class, 'describe'], $cartItems),
            'cancelled_file_candidates' => array_map([self::class, 'describe'], $cancelledFiles),
            'completed_file_candidates' => array_map([self::class, 'describe'], $completedFiles),
            'deleted' => 0,
            'cancelled' => 0,
            'files_purged' => 0,
            'errors' => [],
        ];

        if ($dryRun) {
            return $report;
        }

        foreach ($drafts as $draft) {
            try {
                PersonalizationRepository::purge($draft);
                $report['deleted']++;
            } catch (\Throwable $error) {
                $report['errors'][] = sprintf('draft %s: %s', $draft->publicId, $error->getMessage());
            }
        }

        foreach ($cartItems as $item) {
            try {
                TransitionStatus::apply(
                    $item,
                    PersonalizationStatus::Cancelled,
                    null,
                    'Carrinho abandonado além do prazo de retenção.'
                );
                $report['cancelled']++;
            } catch (\Throwable $error) {
                $report['errors'][] = sprintf('cart %s: %s', $item->publicId, $error->getMessage());
            }
        }

        foreach (array_merge($cancelledFiles, $completedFiles) as $item) {
            try {
                $report['files_purged'] += PersonalizationRepository::purgeFiles($item);
            } catch (\Throwable $error) {
                $report['errors'][] = sprintf('files %s: %s', $item->publicId, $error->getMessage());
            }
        }

        return $report;
    }

    /**
     * @return array{public_id: string, product_id: int, updated_at: string}
     */
    private static function describe(\Petshop\Core\Personalization\Domain\Personalization $personalization): array
    {
        return [
            'public_id' => $personalization->publicId,
            'product_id' => $personalization->productId,
            'updated_at' => $personalization->updatedAt,
        ];
    }
}
