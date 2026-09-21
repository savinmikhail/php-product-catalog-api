<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use RuntimeException;

final class HttpTransportException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $timedOut = false)
    {
        parent::__construct($message);
    }
}
