<?php

declare(strict_types=1);

namespace App\Shared\Exception;

final class ValidationException extends ApiException
{
    public function __construct(array $details)
    {
        parent::__construct('validation_error', 422, 'Validation failed', $details);
    }
}
