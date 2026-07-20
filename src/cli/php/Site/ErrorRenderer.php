<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

final class ErrorRenderer extends BaseContentRenderer
{
    public const SCHEMA = 'error-entry.schema.json';

    private Environment $twig;

    public function __construct(ConfigValues $config, string $rootPath)
    {
        parent::__construct($config, $rootPath);
        $this->twig = $this->buildTwig();
    }

    /** @return array<array{resourcePath: string, cacheKey: string, label: string}> */
    public static function entries(string $rootPath): array
    {
        $baseDir = Path::join($rootPath, 'src', 'resources', 'error');
        return [
            [
                'resourcePath' => Path::join($baseDir, 'not-found.yaml'),
                'cacheKey' => 'not-found',
                'label' => 'Error (not-found)',
            ],
            [
                'resourcePath' => Path::join($baseDir, 'token-invalid.yaml'),
                'cacheKey' => 'token-invalid',
                'label' => 'Error (token-invalid)',
            ],
        ];
    }

    public function schemaNames(): array
    {
        return [self::SCHEMA];
    }

    public function validateContent(OutputInterface $output): bool
    {
        $allValid = true;
        foreach (self::entries($this->rootPath) as $entry) {
            if (!$this->validateYamlFile($entry['resourcePath'], self::SCHEMA, $entry['label'], $output)) {
                $allValid = false;
            }
        }
        return $allValid;
    }

    public function render(OutputInterface $output): void
    {
        foreach (self::entries($this->rootPath) as $entry) {
            $this->renderEntry($entry, $output);
        }
    }

    /** @param array{resourcePath: string, cacheKey: string, label: string} $entry */
    private function renderEntry(array $entry, OutputInterface $output): void
    {
        $data = Yaml::parseFile($entry['resourcePath']);
        if (!is_array($data)) {
            throw new \RuntimeException("Ungültiges {$entry['label']}-YAML: {$entry['resourcePath']}");
        }
        $this->assertValid($data, self::SCHEMA, $output);
        foreach ($this->resolveLangs() as $lang) {
            $this->renderForLang($data, $entry['cacheKey'], $entry['label'], $lang, $output);
        }
    }

    private function renderForLang(
        array $data,
        string $cacheKey,
        string $label,
        string $lang,
        OutputInterface $output
    ): void {
        $resolved = $this->pickLang($data, $lang);
        $messageHtml = $this->renderMarkdown(
            $this->twig,
            (string) $resolved['message'],
            $lang,
            "error.{$cacheKey}.{$lang}.message"
        );
        $html = $this->twig->render('error.html.twig', [
            'lang'        => $lang,
            'title'       => $resolved['title'],
            'message_html' => $messageHtml,
            'site_header' => $this->loadHeaderFragment($lang),
            'site_footer' => $this->loadFooterFragment($lang),
        ]);
        $this->buildStorage()->saveErrorFragmentForLang($cacheKey, $lang, $html);
        $output->writeln("{$label} gerendert ({$lang}).");
    }
}
