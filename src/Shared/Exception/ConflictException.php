<?php

declare(strict_types=1);

namespace App\Shared\Exception;

final class ConflictException extends ApiException
{
    public function __construct(string $message = 'Resource already exists')
    {
        parent::__construct('conflict', 409, $message);
    }
}
