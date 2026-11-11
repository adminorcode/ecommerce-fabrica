<?php

declare(strict_types=1);

namespace Petshop\Core\Tests;

use Petshop\Core\Personalization\Domain\InvalidStatusTransition;
use Petshop\Core\Personalization\Domain\Personalization;
use Petshop\Core\Personalization\Domain\PersonalizationStatus;
use PHPUnit\Framework\TestCase;

final class PersonalizationStatusTest extends TestCase
{
    public function testHappyPathTransitionsAreAllowed(): void
    {
        $path = [
            PersonalizationStatus::Draft,
            PersonalizationStatus::Cart,
            PersonalizationStatus::AwaitingPayment,
            PersonalizationStatus::Review,
            PersonalizationStatus::Approved,
            PersonalizationStatus::InProduction,
            PersonalizationStatus::Completed,
        ];

        for ($index = 0; $index < count($path) - 1; $index++) {
            self::assertTrue(
                $path[$index]->canTransitionTo($path[$index + 1]),
                sprintf('%s deveria permitir %s', $path[$index]->value, $path[$index + 1]->value)
            );
        }
    }

    public function testFinalStatesHaveNoTargets(): void
    {
        self::assertSame([], PersonalizationStatus::Completed->allowedTargets());
        self::assertSame([], PersonalizationStatus::Cancelled->allowedTargets());
    }

    public function testSkippingStatesIsRejected(): void
    {
        self::assertFalse(PersonalizationStatus::Draft->canTransitionTo(PersonalizationStatus::Review));
        self::assertFalse(PersonalizationStatus::Cart->canTransitionTo(PersonalizationStatus::Completed));
        self::assertFalse(PersonalizationStatus::Completed->canTransitionTo(PersonalizationStatus::Cancelled));
    }

    public function testActiveQueueOnlyContainsProductionStates(): void
    {
        self::assertTrue(PersonalizationStatus::Review->isActiveQueue());
        self::assertTrue(PersonalizationStatus::Approved->isActiveQueue());
        self::assertTrue(PersonalizationStatus::InProduction->isActiveQueue());
        self::assertFalse(PersonalizationStatus::Draft->isActiveQueue());
        self::assertFalse(PersonalizationStatus::AwaitingPayment->isActiveQueue());
        self::assertFalse(PersonalizationStatus::Completed->isActiveQueue());
    }

    public function testAggregateIncrementsVersionAndStampsCompletion(): void
    {
        $personalization = $this->personalization(PersonalizationStatus::InProduction);
        $completed = $personalization->withStatus(PersonalizationStatus::Completed);

        self::assertSame(PersonalizationStatus::Completed, $completed->status);
        self::assertSame(4, $completed->statusVersion);
        self::assertNotNull($completed->completedAt);
        self::assertSame(PersonalizationStatus::InProduction, $personalization->status);
    }

    public function testAggregateRejectsInvalidTransition(): void
    {
        $this->expectException(InvalidStatusTransition::class);

        $this->personalization(PersonalizationStatus::Draft)->withStatus(PersonalizationStatus::Completed);
    }

    private function personalization(PersonalizationStatus $status): Personalization
    {
        return Personalization::fromRow([
            'id' => 10,
            'public_id' => '4b1d0f9a-1c2b-4e2f-8f7a-0d1c2b3a4e5f',
            'user_id' => null,
            'cart_hash' => null,
            'product_id' => 42,
            'variation_id' => null,
            'order_id' => null,
            'order_item_id' => null,
            'status' => $status->value,
            'status_version' => 3,
            'design_schema_version' => 1,
            'design_json' => '{"schema":1,"objects":[]}',
            'config_snapshot' => '{}',
            'text_summary' => 'Texto: Luna',
            'snapshot_hash' => str_repeat('a', 64),
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => '2026-08-01 10:00:00',
            'expires_at' => null,
            'completed_at' => null,
        ]);
    }
}
