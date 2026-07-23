<?php

namespace App\Cli\Site;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

final class LabelsValidator extends BaseSchemaValidator
{
    public const SCHEMA = 'labels.schema.json';

    public function validateContent(OutputInterface $output): bool
    {
        try {
            $labels = LabelCatalog::fromJsonFile($this->labelsPath())->labels();
        } catch (\Throwable $e) {
            $output->writeln("<error>Labels: {$e->getMessage()}</error>");
            return false;
        }
        if (!$this->checkValid($labels, self::SCHEMA, $output)) {
            $output->writeln('<error>Labels: ungültig.</error>');
            return false;
        }
        $output->writeln('Labels: OK');
        return true;
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
