<?php

declare(strict_types=1);

namespace App\Modules\InnValidation\Infrastructure\DaData;

use App\Shared\Error\InnValidationTimeoutException;
use App\Shared\Error\InnValidationUnavailableException;
use App\Shared\Infrastructure\Http\HttpResponse;
use App\Shared\Infrastructure\Http\HttpTransport;
use App\Shared\Infrastructure\Http\HttpTransportException;
use JsonException;

final class DadataHttpClient
{
    public function __construct(
        private readonly HttpTransport $transport,
        private readonly string $endpoint,
        private readonly string $token,
        private readonly int $timeoutSeconds = 3,
    ) {
    }

    public function existsByInn(string $inn): bool
    {
        if ($this->token === '') {
            throw new InnValidationUnavailableException('DaData token is not configured');
        }

        try {
            $response = $this->transport->post(
                $this->endpoint,
                [
                    'Authorization' => 'Token ' . $this->token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                json_encode(['query' => $inn], JSON_THROW_ON_ERROR),
                $this->timeoutSeconds,
            );
        } catch (HttpTransportException $exception) {
            if ($exception->timedOut) {
                throw new InnValidationTimeoutException();
            }

            throw new InnValidationUnavailableException('DaData is unavailable');
        }

        if ($response->status === 408 || $response->status === 504) {
            throw new InnValidationTimeoutException();
        }
        if ($response->status < 200 || $response->status >= 300) {
            throw new InnValidationUnavailableException('DaData returned HTTP ' . $response->status);
        }

        return $this->responseContainsInn($response, $inn);
    }

    private function responseContainsInn(HttpResponse $response, string $inn): bool
    {
        try {
            $payload = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InnValidationUnavailableException('DaData returned an invalid response');
        }

        if (!is_array($payload) || !isset($payload['suggestions']) || !is_array($payload['suggestions'])) {
            throw new InnValidationUnavailableException('DaData returned an invalid response');
        }

        foreach ($payload['suggestions'] as $suggestion) {
            if (!is_array($suggestion) || !is_array($suggestion['data'] ?? null)) {
                continue;
            }
            if ((string) ($suggestion['data']['inn'] ?? '') === $inn) {
                return true;
            }
        }

        return false;
    }
}
