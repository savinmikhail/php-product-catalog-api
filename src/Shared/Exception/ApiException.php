<?php

declare(strict_types=1);

namespace App\Shared\Exception;

use RuntimeException;

class ApiException extends RuntimeException
{
    public function __construct(
        private readonly string $codeName,
        private readonly int $httpStatus,
        string $message,
        private readonly array $details = [],
    )
    {
        parent::__construct($message);
    }

    public function codeName(): string
    {
        return $this->codeName;
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    public function details(): array
    {
        return $this->details;
    }
}
