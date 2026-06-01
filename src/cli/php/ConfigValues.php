<?php

namespace App\Cli;

final class ConfigValues
{
    private array $values;

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function get(string $key): string
    {
        if (!array_key_exists($key, $this->values)) {
            throw new \RuntimeException("Config-Schlüssel fehlt: {$key}");
        }
        return (string) $this->values[$key];
    }

    public function all(): array
    {
        return $this->values;
    }
}
