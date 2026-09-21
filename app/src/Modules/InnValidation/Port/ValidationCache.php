<?php

declare(strict_types=1);

namespace App\Modules\InnValidation\Port;

interface ValidationCache
{
    public function get(string $inn): ?bool;

    public function put(string $inn, bool $isValid, int $ttlSeconds): void;
}
