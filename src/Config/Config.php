<?php

declare(strict_types=1);

namespace App\Config;

final readonly class Config
{
    public function __construct(private array $values)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public static function fromEnvironment(): self
    {
        $values = [];
        foreach ($_ENV + $_SERVER as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $values[$key] = (string) $value;
            }
        }

        return new self($values);
    }
}
