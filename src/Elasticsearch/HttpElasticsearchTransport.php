<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Shared\Exception\IndexingException;

final class HttpElasticsearchTransport implements ElasticsearchTransport
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds = 5,
    ) {
    }

    public function request(string $method, string $path, ?array $body = null): ElasticsearchResponse
    {
        $encodedBody = $body === null
            ? ''
            : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $error = null;
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        try {
            $result = file_get_contents(
                rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/'),
                false,
                stream_context_create([
                    'http' => [
                        'method' => $method,
                        'header' => "Content-Type: application/json\r\n",
                        'content' => $encodedBody,
                        'ignore_errors' => true,
                        'timeout' => $this->timeoutSeconds,
                    ],
                ]),
            );
        } finally {
            restore_error_handler();
        }

        if ($result === false) {
            throw new IndexingException('Elasticsearch request failed' . ($error === null ? '' : ': ' . $error));
        }

        $statusCode = $this->responseStatusCode();
        $decoded = json_decode($result, true);

        return new ElasticsearchResponse($statusCode, is_array($decoded) ? $decoded : []);
    }

    private function responseStatusCode(): int
    {
        $headers = $http_response_header ?? [];
        if ($headers !== [] && preg_match('/\s(\d{3})\s/', $headers[0], $matches) === 1) {
            return (int) $matches[1];
        }

        throw new IndexingException('Elasticsearch response did not contain an HTTP status');
    }
}
