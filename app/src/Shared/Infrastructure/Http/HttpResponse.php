<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final readonly class HttpResponse
{
    public function __construct(
        public int $status,
        public string $body,
    ) {
    }
}
