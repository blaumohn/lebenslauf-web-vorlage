<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

final class SiteValidateService
{
    /** @param ContentRendererInterface[] $renderers */
    public function __construct(private readonly array $renderers, private readonly string $rootPath) {}

    public static function create(ConfigValues $config, string $rootPath): self
    {
        return new self([
            new SiteFooterRenderer($config, $rootPath),
            new LangSelectRenderer($config, $rootPath),
            new CvContentRenderer($config, $rootPath),
            new BlogContentRenderer($config, $rootPath),
            new HomeContentRenderer($config, $rootPath),
            new ContactContentRenderer($config, $rootPath),
        ], $rootPath);
    }

    public function validate(OutputInterface $output): bool
    {
        $allValid = $this->validateSchemaCoverage($output);
        foreach ($this->renderers as $renderer) {
            if (!$renderer->validateContent($output)) {
                $allValid = false;
            }
        }
        return $allValid;
    }

    /**
     * Stellt sicher, dass jedes Schema in schemas/ von mindestens einem
     * Renderer referenziert wird — verhindert, dass ein neues Schema ohne
     * Validierungs-Anbindung im Gerüst liegen bleibt.
     */
    private function validateSchemaCoverage(OutputInterface $output): bool
    {
        $schemaDir = Path::join($this->rootPath, 'src', 'resources', 'build', 'schemas');
        $rendererSource = '';
        foreach (glob(Path::join(__DIR__, '*.php')) ?: [] as $file) {
            $rendererSource .= (string) file_get_contents($file);
        }

        $allValid = true;
        foreach (glob(Path::join($schemaDir, '*.json')) ?: [] as $schemaPath) {
            $name = basename($schemaPath);
            if (!str_contains($rendererSource, $name)) {
                $output->writeln("<error>Schema ohne Renderer-Bindung: {$name}</error>");
                $allValid = false;
            }
        }
        return $allValid;
    }
}
