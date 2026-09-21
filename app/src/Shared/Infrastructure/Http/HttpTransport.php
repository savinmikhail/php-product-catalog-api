<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

interface HttpTransport
{
    /** @param array<string, string> $headers */
    public function post(string $url, array $headers, string $body, int $timeoutSeconds): HttpResponse;
}
