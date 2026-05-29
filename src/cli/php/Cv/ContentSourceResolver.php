<?php

namespace App\Cli\Cv;

use App\Cli\ConfigValues;
use Symfony\Component\Filesystem\Path;

final class ContentSourceResolver
{
    private ConfigValues $config;
    private string $appRoot;

    public function __construct(ConfigValues $config, string $appRoot)
    {
        $this->config = $config;
        $this->appRoot = rtrim($appRoot, DIRECTORY_SEPARATOR);
    }

    public function dataDir(): string
    {
        return $this->resolvePath($this->config->get('LEBENSLAUF_DATEN_PFAD'));
    }

    public function yamlPath(): string
    {
        return $this->resolvePath($this->config->get('LEBENSLAUF_YAML_PFAD'));
    }

    public function jsonPath(): string
    {
        return $this->defaultJsonPath();
    }

    public function resolveTargets(string $defaultProfile): array
    {
        $yamlPath = $this->requireYamlPath();
        $targets = $this->collectYamlTargets($yamlPath, $defaultProfile);
        if ($targets !== []) {
            return $targets;
        }
        if (!$this->isYamlDir($yamlPath)) {
            return $this->requireYamlTargets($yamlPath, $defaultProfile);
        }
        return $this->requireYamlTargets($yamlPath, $defaultProfile);
    }

    public function requireYamlPath(): string
    {
        $yamlPath = $this->yamlPath();
        if ($yamlPath === '') {
            throw new \RuntimeException('Missing config: LEBENSLAUF_DATEN_PFAD or LEBENSLAUF_YAML_PFAD');
        }
        return $yamlPath;
    }

    public function requireYamlTargets(string $yamlPath, string $defaultProfile): array
    {
        $targets = $this->collectYamlTargets($yamlPath, $defaultProfile);
        if ($targets !== []) {
            return $targets;
        }
        if ($this->isYamlDir($yamlPath)) {
            throw new \RuntimeException("No daten-*.yaml files found in: {$yamlPath}");
        }
        throw new \RuntimeException("YAML path not found: {$yamlPath}");
    }

    public function collectYamlTargets(string $yamlPath, string $defaultProfile): array
    {
        if ($yamlPath === '') {
            return [];
        }

        if (is_dir($yamlPath)) {
            return $this->collectYamlTargetsFromDir($yamlPath);
        }

        if (is_file($yamlPath)) {
            return [[
                'profile' => $defaultProfile,
                'yaml' => $yamlPath,
            ]];
        }

        return [];
    }

    private function collectYamlTargetsFromDir(string $yamlPath): array
    {
        $entries = scandir($yamlPath);
        if ($entries === false) {
            return [];
        }
        $targets = [];
        foreach ($entries as $entry) {
            $target = $this->targetFromEntry($yamlPath, $entry);
            if ($target !== null) {
                $targets[] = $target;
            }
        }
        return $targets;
    }

    private function targetFromEntry(string $yamlPath, mixed $entry): ?array
    {
        if (!is_string($entry)) {
            return null;
        }
        if (!preg_match('/^daten[-.](.+)\\.yaml$/i', $entry, $matches)) {
            return null;
        }
        return [
            'profile' => $matches[1],
            'yaml' => Path::join($yamlPath, $entry),
        ];
    }

    private function defaultJsonPath(): string
    {
        return Path::join($this->appRoot, 'var', 'tmp', 'lebenslauf.json');
    }

    private function resolvePath(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        if (Path::isAbsolute($value)) {
            return $value;
        }
        return Path::join($this->appRoot, $value);
    }

    private function isYamlDir(string $yamlPath): bool
    {
        if ($yamlPath === $this->dataDir()) {
            return true;
        }
        return is_dir($yamlPath);
    }
}
