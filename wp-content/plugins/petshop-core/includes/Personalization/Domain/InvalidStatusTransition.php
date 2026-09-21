<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Domain;

defined('ABSPATH') || exit;

final class InvalidStatusTransition extends \RuntimeException
{
    public static function from(PersonalizationStatus $from, PersonalizationStatus $to): self
    {
        return new self(sprintf(
            'Transição inválida de personalização: %s → %s',
            $from->value,
            $to->value
        ));
    }
}
