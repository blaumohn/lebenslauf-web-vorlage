<?php

namespace App\Cli\Cv;

use App\Cli\ConfigValues;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class CvBuildService
{
    private string $rootPath;
    private ConfigValues $config;
    private ContentSourceResolver $resolver;
    private CvUploadService $uploader;

    public function __construct(ConfigValues $config, string $appRoot)
    {
        $this->config = $config;
        $this->rootPath = rtrim($appRoot, DIRECTORY_SEPARATOR);
        $this->resolver = new ContentSourceResolver($config, $appRoot);
        $this->uploader = new CvUploadService($config, $appRoot);
    }

    public function build(OutputInterface $output): void
    {
        $targets = $this->resolveTargets();
        $jsonPath = $this->resolver->jsonPath();
        $this->ensureDir(dirname($jsonPath));

        foreach ($targets as $target) {
            $this->buildTarget($target, $jsonPath, $output);
        }
    }

    private function resolveTargets(): array
    {
        $publicProfile = $this->publicProfile();
        return $this->resolver->resolveTargets($publicProfile);
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

    private function publicProfile(): string
    {
        $value = trim($this->config->get('LEBENSLAUF_PUBLIC_PROFILE'));
        return $value === '' ? 'default' : $value;
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
