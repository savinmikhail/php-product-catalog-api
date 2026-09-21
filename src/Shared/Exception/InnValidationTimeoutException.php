<?php

declare(strict_types=1);

namespace App\Shared\Exception;

final class InnValidationTimeoutException extends ApiException
{
    public function __construct(string $message = 'DaData request timed out')
    {
        parent::__construct('inn_validation_timeout', 504, $message);
    }
}
