<?php

namespace App\Cli\Cv;

use App\Cli\ConfigValues;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class CvBuildService
{
    private string $rootPath;
    private ConfigValues $config;
    private CvUploadService $uploader;

    public function __construct(ConfigValues $config, string $appRoot)
    {
        $this->config = $config;
        $this->rootPath = rtrim($appRoot, DIRECTORY_SEPARATOR);
        $this->uploader = new CvUploadService($config, $appRoot);
    }

    public function build(OutputInterface $output): void
    {
        $targets = $this->resolveTargets();
        $jsonPath = $this->jsonPath();
        $this->ensureDir(dirname($jsonPath));

        foreach ($targets as $target) {
            $this->buildTarget($target, $jsonPath, $output);
        }
    }

    private function resolveTargets(): array
    {
        $dataPath = $this->dataPath();
        if (!is_dir($dataPath)) {
            throw new \RuntimeException("CV data directory not found: {$dataPath}");
        }

        $targets = $this->collectTargetsFromDir($dataPath);
        if ($targets === []) {
            throw new \RuntimeException("No daten-*.yaml files found in: {$dataPath}");
        }
        return $targets;
    }

    private function buildTarget(array $target, string $jsonPath, OutputInterface $output): void
    {
        $yamlPath = (string) ($target['yaml'] ?? '');
        $profile = (string) ($target['profile'] ?? '');
        $this->ensureFileExists($yamlPath, 'YAML');
        $this->runYamlToJson($yamlPath, $jsonPath);
        $this->uploader->upload($profile, $jsonPath, $output);
        $output->writeln("CV build completed: {$profile} ({$yamlPath})");
    }

    private function runYamlToJson(string $yamlPath, string $jsonPath): void
    {
        $data = $this->parseYaml($yamlPath);
        $json = $this->encodeJson($data);
        if (file_put_contents($jsonPath, $json) === false) {
            throw new \RuntimeException("JSON write failed: {$jsonPath}");
        }
    }

    private function parseYaml(string $path): array
    {
        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $error) {
            throw new \RuntimeException("YAML parse failed: {$path}", 0, $error);
        }
        if (!is_array($data)) {
            throw new \RuntimeException("YAML content is not a map: {$path}");
        }
        return $data;
    }

    private function encodeJson(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('JSON encode failed.');
        }
        return $json;
    }

    private function dataPath(): string
    {
        $value = $this->config->get('LEBENSLAUF_DATEN_PFAD');
        if ($value === '') {
            throw new \RuntimeException('Missing config: LEBENSLAUF_DATEN_PFAD');
        }
        if (Path::isAbsolute($value)) {
            return $value;
        }
        return Path::join($this->rootPath, $value);
    }

    private function collectTargetsFromDir(string $dataPath): array
    {
        $entries = scandir($dataPath);
        if ($entries === false) {
            return [];
        }

        $targets = [];
        foreach ($entries as $entry) {
            $target = $this->targetFromEntry($dataPath, $entry);
            if ($target !== null) {
                $targets[] = $target;
            }
        }
        return $targets;
    }

    private function targetFromEntry(string $dataPath, mixed $entry): ?array
    {
        if (!is_string($entry)) {
            return null;
        }
        if (!preg_match('/^daten[-.](.+)\\.yaml$/i', $entry, $matches)) {
            return null;
        }
        return [
            'profile' => $matches[1],
            'yaml' => Path::join($dataPath, $entry),
        ];
    }

    private function jsonPath(): string
    {
        return Path::join($this->rootPath, 'var', 'tmp', 'lebenslauf.json');
    }

    private function ensureDir(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException("JSON output directory missing and could not be created: {$path}");
        }
    }

    private function ensureFileExists(string $path, string $label): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException("{$label} not found: {$path}");
        }
    }
}
