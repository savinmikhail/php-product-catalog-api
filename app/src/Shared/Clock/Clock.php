<?php

declare(strict_types=1);

namespace App\Shared\Clock;

interface Clock
{
    public function now(): int;
}
