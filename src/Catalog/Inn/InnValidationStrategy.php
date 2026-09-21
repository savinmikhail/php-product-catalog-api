<?php

declare(strict_types=1);

namespace App\Catalog\Inn;

interface InnValidationStrategy
{
    /** @throws \App\Shared\Exception\ApiException when the INN cannot be validated */
    public function validate(string $inn): void;
}
