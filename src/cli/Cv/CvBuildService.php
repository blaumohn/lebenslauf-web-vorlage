<?php

namespace App\Cli\Cv;

use App\Content\ContentConfig;
use EnvPipelineSpec\Env\Env;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class CvBuildService
{
    private string $rootPath;
    private Env $env;
    private ContentConfig $content;
    private ContentSourceResolver $resolver;
    private CvUploadService $uploader;

    public function __construct(Env $env)
    {
        $this->env = $env;
        $this->rootPath = $env->rootPath();
        $this->content = new ContentConfig($this->rootPath);
        $this->resolver = new ContentSourceResolver($env);
        $this->uploader = new CvUploadService($env);
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

    public function inputFiles(): array
    {
        $files = [];
        $files[] = $this->content->path();
        $files[] = $this->labelsPath();
        $files[] = $this->schemaPath();
        $files = array_merge($files, $this->yamlFiles());
        $files = array_merge($files, $this->templateFiles());
        return $this->uniquePaths($files);
    }

    private function resolveTargets(): array
    {
        $defaultProfile = $this->defaultProfile();
        return $this->resolver->resolveTargets($defaultProfile);
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

    private function defaultProfile(): string
    {
        return $this->content->cvProfile();
    }

    private function yamlFiles(): array
    {
        $defaultProfile = $this->defaultProfile();
        $yamlPath = $this->resolver->yamlPath();
        $targets = $this->resolver->collectYamlTargets($yamlPath, $defaultProfile);
        $files = [];
        foreach ($targets as $target) {
            $files[] = (string) ($target['yaml'] ?? '');
        }
        return $files;
    }

    private function templateFiles(): array
    {
        $dir = Path::join($this->rootPath, 'src', 'resources', 'templates');
        if (!is_dir($dir)) {
            return [];
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.twig')) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    private function labelsPath(): string
    {
        return Path::join($this->rootPath, 'src', 'resources', 'labels.json');
    }

    private function schemaPath(): string
    {
        return Path::join($this->rootPath, 'schemas', 'lebenslauf.schema.json');
    }

    private function uniquePaths(array $paths): array
    {
        $filtered = [];
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                $filtered[] = $path;
            }
        }
        return array_values(array_unique($filtered));
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
