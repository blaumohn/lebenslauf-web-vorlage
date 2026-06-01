<?php

namespace App\Http;

use Symfony\Component\Filesystem\Path;

final class ConfigCompiled
{
    private string $appRoot;
    private array $values;
    private array $pipelinePhase;

    public function __construct(string $appRoot)
    {
        $this->appRoot = rtrim($appRoot, DIRECTORY_SEPARATOR);
        $path = Path::join($this->appRoot, 'var', 'config', 'config.php');
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

    public function get(string $key): string
    {
        if (!array_key_exists($key, $this->values)) {
            throw new \RuntimeException("Config-Schlüssel fehlt: {$key}");
        }
        return (string) $this->values[$key];
    }

public function pipeline(): string
    {
        return (string) ($this->pipelinePhase['pipeline'] ?? '');
    }

    public function phase(): string
    {
        return (string) ($this->pipelinePhase['phase'] ?? '');
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
