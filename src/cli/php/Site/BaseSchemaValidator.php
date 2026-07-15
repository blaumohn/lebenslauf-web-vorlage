<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use App\Http\SchemaValidator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

abstract class BaseSchemaValidator implements ContentValidatorInterface
{
    public function __construct(protected ConfigValues $config, protected string $rootPath) {}

    public function validateContent(OutputInterface $output): bool
    {
        return true;
    }

    /** @return list<string> */
    public function schemaNames(): array
    {
        return [];
    }

    protected function resolveContentBase(): string
    {
        $value = $this->config->get('CONTENT_PATH');
        return Path::isAbsolute($value) ? $value : Path::join($this->rootPath, $value);
    }

    protected function loadYamlFile(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException("Ungültiges YAML: {$path}");
        }
        return $data;
    }

    protected function discoverYamlFiles(string $dir, array $excludeNames = []): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $entries = scandir($dir) ?: [];
        $result = [];
        foreach ($entries as $entry) {
            if (!str_ends_with($entry, '.yaml') || in_array($entry, $excludeNames, true)) {
                continue;
            }
            $data = $this->loadYamlFile(Path::join($dir, $entry));
            if ($data !== null) {
                $result[$entry] = $data;
            }
        }
        return $result;
    }

    protected function validateYamlFile(string $path, string $schemaName, string $label, OutputInterface $output): bool
    {
        if (!is_file($path)) {
            $output->writeln("{$label}: YAML fehlt ({$path}) — übersprungen.");
            return true;
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            $output->writeln("<error>{$label}: YAML-Fehler: {$e->getMessage()}</error>");
            return false;
        }
        if (!is_array($data)) {
            $output->writeln("<error>{$label}: kein gültiges YAML-Mapping.</error>");
            return false;
        }
        if ($this->checkValid($data, $schemaName, $output)) {
            $output->writeln("{$label}: OK");
            return true;
        }
        $output->writeln("<error>{$label}: ungültig.</error>");
        return false;
    }

    protected function assertValid(mixed $data, string $schemaName, OutputInterface $output): void
    {
        if ($this->checkValid($data, $schemaName, $output)) {
            return;
        }
        throw new \RuntimeException("{$schemaName}: Schema-Validierung fehlgeschlagen.");
    }

    protected function checkValid(mixed $data, string $schemaName, OutputInterface $output): bool
    {
        $schemaPath = Path::join($this->rootPath, 'src', 'resources', 'build', 'schemas', $schemaName);
        $errors = (new SchemaValidator($schemaPath))->validate($data);
        if ($errors === []) {
            return true;
        }
        $output->writeln("<error>{$schemaName}: Schema-Validierung fehlgeschlagen:</error>");
        foreach ($errors as $error) {
            $output->writeln("  - {$error}");
        }
        return false;
    }
}
