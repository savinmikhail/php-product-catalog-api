<?php

declare(strict_types=1);

namespace App\Shared\Exception;

final class NotFoundException extends ApiException
{
    public function __construct(string $message = 'Resource not found')
    {
        parent::__construct('not_found', 404, $message);
    }
}
