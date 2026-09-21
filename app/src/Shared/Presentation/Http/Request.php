<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public array $body,
    ) {
    }

    public static function fromGlobals(): self
    {
        $raw = file_get_contents('php://input');
        $decoded = $raw === false || $raw === '' ? [] : json_decode($raw, true);

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            $_GET,
            is_array($decoded) ? $decoded : [],
        );
    }
}
