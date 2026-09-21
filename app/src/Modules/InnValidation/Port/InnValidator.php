<?php

declare(strict_types=1);

namespace App\Modules\InnValidation\Port;

interface InnValidator
{
    /** @throws \App\Shared\Error\ApiException when the INN cannot be validated */
    public function validate(string $inn): void;
}
