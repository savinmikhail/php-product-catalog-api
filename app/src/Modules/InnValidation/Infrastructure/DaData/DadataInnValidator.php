<?php

declare(strict_types=1);

namespace App\Modules\InnValidation\Infrastructure\DaData;

use App\Modules\InnValidation\Port\InnValidator;
use App\Modules\InnValidation\Port\ValidationCache;
use App\Shared\Error\ValidationException;

final class DadataInnValidator implements InnValidator
{
    public function __construct(
        private readonly DadataHttpClient $client,
        private readonly ValidationCache $cache,
        private readonly int $cacheTtlSeconds = 3600,
    ) {
    }

    public function validate(string $inn): void
    {
        $cached = $this->cache->get($inn);
        if ($cached !== null) {
            if (!$cached) {
                $this->throwInvalidInn();
            }

            return;
        }

        $isValid = $this->client->existsByInn($inn);
        $this->cache->put($inn, $isValid, $this->cacheTtlSeconds);
        if (!$isValid) {
            $this->throwInvalidInn();
        }
    }

    private function throwInvalidInn(): never
    {
        throw new ValidationException(['inn' => 'INN was not found in DaData']);
    }
}
