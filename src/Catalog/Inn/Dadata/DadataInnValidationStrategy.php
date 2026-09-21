<?php

declare(strict_types=1);

namespace App\Catalog\Inn\Dadata;

use App\Catalog\Inn\InnValidationCache;
use App\Catalog\Inn\InnValidationStrategy;
use App\Shared\Exception\ValidationException;

final class DadataInnValidationStrategy implements InnValidationStrategy
{
    public function __construct(
        private readonly DadataHttpClient $client,
        private readonly InnValidationCache $cache,
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
