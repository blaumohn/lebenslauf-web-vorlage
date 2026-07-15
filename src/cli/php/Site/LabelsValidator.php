<?php

namespace App\Cli\Site;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

final class LabelsValidator extends BaseSchemaValidator
{
    public const SCHEMA = 'labels.schema.json';

    public function validateContent(OutputInterface $output): bool
    {
        return $this->validateYamlFile($this->labelsPath(), self::SCHEMA, 'Labels', $output);
    }

    public function schemaNames(): array
    {
        return [self::SCHEMA];
    }

    private function labelsPath(): string
    {
        return Path::join($this->rootPath, 'src', 'resources', 'build', 'labels.json');
    }
}
