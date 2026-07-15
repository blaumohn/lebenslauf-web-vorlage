<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

final class LangSelectRenderer extends BaseContentRenderer
{
    public const SCHEMA = 'lang-select.schema.json';

    private Environment $twig;

    public function __construct(ConfigValues $config, string $rootPath)
    {
        parent::__construct($config, $rootPath);
        $this->twig = $this->buildTwig();
    }

    public function schemaNames(): array
    {
        return [self::SCHEMA];
    }

    public function validateContent(OutputInterface $output): bool
    {
        return $this->validateYamlFile($this->dataPath(), self::SCHEMA, 'Lang-Select', $output);
    }

    public function render(OutputInterface $output): void
    {
        $path = $this->dataPath();
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException("Ungültiges lang-select.yaml: {$path}");
        }
        $this->assertValid($data, self::SCHEMA, $output);

        $langs = $this->resolveLangs();
        $translations = $this->buildTranslations($data, $langs);
        $titles = array_column($translations, 'title');
        $html = $this->twig->render('lang-select.html.twig', [
            'supported_langs' => $langs,
            'translations' => $translations,
            'title' => implode(' / ', $titles),
        ]);
        $this->buildStorage()->saveLangSelectHtml($html);
        $output->writeln('Lang-Select gerendert.');
    }

    private function buildTranslations(array $data, array $langs): array
    {
        $keys = ['title', 'heading', 'description', 'link'];
        $translations = [];
        foreach ($langs as $lang) {
            foreach ($keys as $key) {
                $value = $data[$key][$lang] ?? null;
                if ($value === null) {
                    throw new \RuntimeException("lang-select.yaml: '{$key}.{$lang}' fehlt");
                }
                $translations[$lang][$key] = (string) $value;
            }
        }
        return $translations;
    }

    private function dataPath(): string
    {
        return Path::join($this->rootPath, 'src', 'resources', 'lang-select', 'lang-select.yaml');
    }
}
