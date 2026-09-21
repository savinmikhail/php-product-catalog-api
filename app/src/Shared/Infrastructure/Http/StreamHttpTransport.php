<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class StreamHttpTransport implements HttpTransport
{
    public function post(string $url, array $headers, string $body, int $timeoutSeconds): HttpResponse
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $this->formatHeaders($headers),
                'content' => $body,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        error_clear_last();
        $handle = @fopen($url, 'rb', false, $context);
        if ($handle === false) {
            $error = error_get_last();
            $message = is_array($error) && isset($error['message']) ? (string) $error['message'] : 'HTTP request failed';

            throw new HttpTransportException($message, $this->isTimeout($message));
        }

        $metadata = stream_get_meta_data($handle);
        $responseBody = @stream_get_contents($handle);
        fclose($handle);

        if (($metadata['timed_out'] ?? false) === true) {
            throw new HttpTransportException('HTTP request timed out', true);
        }
        if ($responseBody === false) {
            throw new HttpTransportException('HTTP response could not be read');
        }

        return new HttpResponse($this->statusCode($metadata['wrapper_data'] ?? []), $responseBody);
    }

    /** @param array<string, string> $headers */
    private function formatHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode("\r\n", $lines);
    }

    /** @param mixed $wrapperData */
    private function statusCode(mixed $wrapperData): int
    {
        if (!is_array($wrapperData)) {
            return 0;
        }

        $status = 0;
        foreach ($wrapperData as $header) {
            if (is_string($header) && preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return $status;
    }

    private function isTimeout(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'operation timed out');
    }
}
