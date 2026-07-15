<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

final class SiteValidateService
{
    private const SHARED_DEFINITION_SCHEMAS = ['common.schema.json'];

    /** @param ContentValidatorInterface[] $validators */
    public function __construct(private readonly array $validators, private readonly string $rootPath) {}

    public static function create(ConfigValues $config, string $rootPath): self
    {
        return new self([
            new SiteFooterRenderer($config, $rootPath),
            new LangSelectRenderer($config, $rootPath),
            new LabelsValidator($config, $rootPath),
            new ErrorRenderer(
                $config,
                $rootPath,
                Path::join($rootPath, 'src', 'resources', 'not-found.yaml'),
                'not-found',
                'Error (not-found)'
            ),
            new ErrorRenderer(
                $config,
                $rootPath,
                Path::join($rootPath, 'src', 'resources', 'token', 'token-invalid.yaml'),
                'token-invalid',
                'Error (token-invalid)'
            ),
            new CvContentRenderer($config, $rootPath),
            new BlogIndexRenderer($config, $rootPath),
            new BlogPostRenderer($config, $rootPath),
            new HomeContentRenderer($config, $rootPath),
            new ContactContentRenderer($config, $rootPath),
        ], $rootPath);
    }

    public function validate(OutputInterface $output): bool
    {
        $allValid = $this->validateSchemaCoverage($output);
        foreach ($this->validators as $validator) {
            if (!$validator->validateContent($output)) {
                $allValid = false;
            }
        }
        return $allValid;
    }

    private function validateSchemaCoverage(OutputInterface $output): bool
    {
        $declared = [];
        foreach ($this->validators as $validator) {
            foreach ($validator->schemaNames() as $name) {
                $declared[$name] = true;
            }
        }

        $schemaDir = Path::join($this->rootPath, 'src', 'resources', 'build', 'schemas');
        $allValid = true;
        foreach (glob(Path::join($schemaDir, '*.json')) ?: [] as $schemaPath) {
            $name = basename($schemaPath);
            if (in_array($name, self::SHARED_DEFINITION_SCHEMAS, true)) {
                continue;
            }
            if (!isset($declared[$name])) {
                $output->writeln("<error>Schema ohne Validator-Bindung: {$name}</error>");
                $allValid = false;
            }
        }
        return $allValid;
    }
}
