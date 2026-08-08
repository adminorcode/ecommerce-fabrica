<?php

declare(strict_types=1);

namespace Petshop\Core\Migration;

final class MigrationException extends \RuntimeException
{
    public function __construct(private readonly string $errorCode, string $detail)
    {
        parent::__construct($detail);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
