<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use Symfony\Component\Console\Output\OutputInterface;

final class SiteValidateService
{
    /** @param ContentRendererInterface[] $renderers */
    public function __construct(private readonly array $renderers) {}

    public static function create(ConfigValues $config, string $rootPath): self
    {
        return new self([
            new SiteFooterRenderer($config, $rootPath),
            new CvContentRenderer($config, $rootPath),
            new BlogContentRenderer($config, $rootPath),
            new HomeContentRenderer($config, $rootPath),
            new ContactContentRenderer($config, $rootPath),
        ]);
    }

    public function validate(OutputInterface $output): bool
    {
        $allValid = true;
        foreach ($this->renderers as $renderer) {
            if (!$renderer->validateContent($output)) {
                $allValid = false;
            }
        }
        return $allValid;
    }
}
