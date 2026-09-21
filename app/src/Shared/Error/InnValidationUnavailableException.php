<?php

declare(strict_types=1);

namespace App\Shared\Error;

final class InnValidationUnavailableException extends ApiException
{
    public function __construct(string $message = 'DaData is unavailable')
    {
        parent::__construct('inn_validation_unavailable', 503, $message);
    }
}
