<?php

namespace App\Http;

use Symfony\Component\Filesystem\Path;

final class ConfigCompiled
{
    private string $rootPath;
    private array $values;
    private array $pipelinePhase;

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
        $this->values = $this->loadValues($path, $data);
        $this->pipelinePhase = $this->loadPipelinePhase($path, $data);
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function entryPath(): string
    {
        return dirname($this->rootPath);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $this->values)) {
            return $default;
        }
        return $this->values[$key];
    }

    public function pipeline(): string
    {
        return (string) ($this->pipelinePhase['pipeline'] ?? '');
    }

    public function phase(): string
    {
        return (string) ($this->pipelinePhase['phase'] ?? '');
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

    private function loadValues(string $configPath, array $data): array
    {
        $values = $data['values'] ?? null;
        if (!is_array($values)) {
            throw new \RuntimeException("Compiled config values ungueltig: {$configPath}");
        }
        return $values;
    }

    private function loadPipelinePhase(string $configPath, array $data): array
    {
        $pipelinePhase = $data['pipeline_phase'] ?? null;
        if (!is_array($pipelinePhase)) {
            throw new \RuntimeException("Compiled config pipeline_phase ungueltig: {$configPath}");
        }

        $pipeline = $pipelinePhase['pipeline'] ?? null;
        $phase = $pipelinePhase['phase'] ?? null;
        if (!is_string($pipeline) || trim($pipeline) === '') {
            throw new \RuntimeException("Compiled config pipeline_phase.pipeline fehlt: {$configPath}");
        }
        if (!is_string($phase) || trim($phase) === '') {
            throw new \RuntimeException("Compiled config pipeline_phase.phase fehlt: {$configPath}");
        }

        return [
            'pipeline' => trim($pipeline),
            'phase' => trim($phase),
        ];
    }
}
