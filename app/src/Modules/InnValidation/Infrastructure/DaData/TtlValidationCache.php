<?php

declare(strict_types=1);

namespace App\Modules\InnValidation\Infrastructure\DaData;

use App\Modules\InnValidation\Port\ValidationCache;
use App\Shared\Clock\Clock;

final class TtlValidationCache implements ValidationCache
{
    /** @var array<string, array{is_valid: bool, expires_at: int}> */
    private array $entries = [];

    public function __construct(private readonly Clock $clock)
    {
    }

    public function get(string $inn): ?bool
    {
        $entry = $this->entries[$inn] ?? null;
        if ($entry === null) {
            return null;
        }
        if ($entry['expires_at'] <= $this->clock->now()) {
            unset($this->entries[$inn]);

            return null;
        }

        return $entry['is_valid'];
    }

    public function put(string $inn, bool $isValid, int $ttlSeconds): void
    {
        $this->entries[$inn] = [
            'is_valid' => $isValid,
            'expires_at' => $this->clock->now() + max(0, $ttlSeconds),
        ];
    }
}
