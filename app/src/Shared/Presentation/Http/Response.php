<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

final readonly class Response
{
    public function __construct(public ?array $payload, public int $status = 200)
    {
    }

    public function send(): never
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        if ($this->status !== 204) {
            echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        exit;
    }
}
