<?php

namespace App\Http;

use Symfony\Component\Filesystem\Path;

final class ConfigCompiled
{
    private string $rootPath;
    private array $data;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $path = Path::join($this->rootPath, 'var', 'config', 'config.php');
        if (!is_file($path)) {
            $hint = 'Bitte zuerst: php bin/cli build <pipeline>';
            throw new \RuntimeException("Compiled config fehlt: {$path}. {$hint}");
        }
        $data = require $path;
        if (!is_array($data)) {
            throw new \RuntimeException("Compiled config ungueltig: {$path}");
        }
        $this->data = $data;
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            return $default;
        }
        return $this->data[$key];
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default ? '1' : '0');
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public function getInt(string $key, int $default): int
    {
        $value = $this->get($key, null);
        if ($value === null || $value === '') {
            return $default;
        }
        return (int) $value;
    }

    public function requireString(string $key): string
    {
        $value = $this->get($key, null);
        if ($value === null || trim((string) $value) === '') {
            throw new \RuntimeException("Missing required config: {$key}");
        }
        return (string) $value;
    }

    public function requireInt(string $key): int
    {
        $value = $this->get($key, null);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Missing required config: {$key}");
        }
        return (int) $value;
    }

    public function requireBool(string $key): bool
    {
        $value = $this->get($key, null);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Missing required config: {$key}");
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public function basePath(): string
    {
        $value = trim((string) $this->get('APP_BASE_PATH'));
        if ($value === '' || $value === '/') {
            return '';
        }
        return '/' . trim($value, '/');
    }
}
