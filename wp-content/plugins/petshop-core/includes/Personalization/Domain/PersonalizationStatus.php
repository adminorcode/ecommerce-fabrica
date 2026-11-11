<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Domain;

defined('ABSPATH') || exit;

/**
 * Machine states for personalization lifecycle (Plano 012 §10).
 */
enum PersonalizationStatus: string
{
    case Draft = 'draft';
    case Cart = 'cart';
    case AwaitingPayment = 'awaiting_payment';
    case Review = 'review';
    case Approved = 'approved';
    case InProduction = 'in_production';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Draft => [self::Cart, self::Cancelled],
            self::Cart => [self::AwaitingPayment, self::Draft, self::Cancelled],
            self::AwaitingPayment => [self::Review, self::Cancelled],
            self::Review => [self::Approved, self::Cancelled],
            self::Approved => [self::InProduction, self::Cancelled],
            self::InProduction => [self::Completed, self::Cancelled],
            self::Completed => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    public function isActiveQueue(): bool
    {
        return in_array($this, [
            self::Review,
            self::Approved,
            self::InProduction,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Rascunho', 'petshop-core'),
            self::Cart => __('No carrinho', 'petshop-core'),
            self::AwaitingPayment => __('Aguardando pagamento', 'petshop-core'),
            self::Review => __('Para revisar', 'petshop-core'),
            self::Approved => __('Aprovado', 'petshop-core'),
            self::InProduction => __('Em produção', 'petshop-core'),
            self::Completed => __('Concluído', 'petshop-core'),
            self::Cancelled => __('Cancelado', 'petshop-core'),
        };
    }
}
