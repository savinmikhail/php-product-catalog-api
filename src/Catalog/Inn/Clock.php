<?php

declare(strict_types=1);

namespace App\Catalog\Inn;

interface Clock
{
    public function now(): int;
}
