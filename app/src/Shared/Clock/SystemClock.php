<?php

declare(strict_types=1);

namespace App\Shared\Clock;

final class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
