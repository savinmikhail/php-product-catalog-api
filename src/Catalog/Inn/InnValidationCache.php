<?php

declare(strict_types=1);

namespace App\Catalog\Inn;

interface InnValidationCache
{
    public function get(string $inn): ?bool;

    public function put(string $inn, bool $isValid, int $ttlSeconds): void;
}
