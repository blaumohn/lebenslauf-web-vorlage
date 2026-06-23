<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use Symfony\Component\Console\Output\OutputInterface;

final class SiteBuildService
{
    /** @param ContentRendererInterface[] $renderers */
    public function __construct(private readonly array $renderers) {}

    public static function create(ConfigValues $config, string $rootPath): self
    {
        return new self([
            new SiteHeaderRenderer($config, $rootPath),
            new SiteFooterRenderer($config, $rootPath),
            new CvContentRenderer($config, $rootPath),
            new BlogContentRenderer($config, $rootPath),
            new HomeContentRenderer($config, $rootPath),
            new ContactContentRenderer($config, $rootPath),
        ]);
    }

    public function build(OutputInterface $output): void
    {
        foreach ($this->renderers as $renderer) {
            $renderer->render($output);
        }
    }

    public function contentSections(): array
    {
        $keys = [];
        foreach ($this->renderers as $renderer) {
            $key = $renderer->sectionKey();
            if ($key !== null) {
                $keys[] = $key;
            }
        }
        return $keys;
    }
}
