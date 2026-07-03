<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use App\Http\SiteHtmlCache;
use App\Http\SchemaValidator;
use App\Http\Storage\FileStorage;
use App\Http\Templating\TwigFactory;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

abstract class BaseContentRenderer implements ContentRendererInterface
{
    public function __construct(protected ConfigValues $config, protected string $rootPath) {}

    public function sectionKey(): ?string
    {
        return null;
    }

    protected function resolveLangs(): array
    {
        $parts = preg_split('/\s*,\s*/', $this->config->get('CONTENT_LANGS'));
        if ($parts === false) {
            throw new \RuntimeException('Konfiguration ungültig: CONTENT_LANGS');
        }

        $langs = $this->normalizeLangs($parts);
        if ($langs === []) {
            throw new \RuntimeException('Konfiguration ungültig: CONTENT_LANGS');
        }

        return $langs;
    }

    protected function normalizeLangs(array $parts): array
    {
        $langs = [];
        foreach ($parts as $part) {
            $value = strtolower(trim((string) $part));
            if ($value !== '') {
                $langs[] = $value;
            }
        }
        return array_values(array_unique($langs));
    }

    protected function pickLang(array $data, string $lang): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = $this->resolveIntlString($value, $lang);
        }
        return $result;
    }

    protected function resolveIntlString(mixed $value, string $lang): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (isset($value[$lang])) {
            return $value[$lang];
        }
        $first = reset($value);
        return $first !== false ? $first : '';
    }

    protected function resolveContentBase(): string
    {
        $value = $this->config->get('CONTENT_PATH');
        if ($value === '') {
            throw new \RuntimeException('Konfiguration fehlt: CONTENT_PATH');
        }
        return Path::isAbsolute($value) ? $value : Path::join($this->rootPath, $value);
    }

    protected function resolveBasePath(): string
    {
        $value = $this->config->get('APP_BASE_PATH');
        if ($value === '' || $value === '/') {
            return '';
        }
        return '/' . trim($value, '/');
    }

    protected function buildTwig(): Environment
    {
        $twig = TwigFactory::create(Path::join($this->rootPath, 'src', 'resources', 'templates'));
        TwigFactory::configure($twig, $this->resolveBasePath());
        return $twig;
    }

    protected function buildStorage(): SiteHtmlCache
    {
        return new SiteHtmlCache(new FileStorage(), Path::join($this->rootPath, 'var', 'cache', 'html'));
    }

    protected function loadHeaderFragment(string $lang): string
    {
        return $this->buildStorage()->getHeaderFragmentForLang($lang)
            ?? throw new \RuntimeException("Header-Fragment fehlt ({$lang}). SiteHeaderRenderer muss zuerst laufen.");
    }

    protected function loadFooterFragment(string $lang): string
    {
        return $this->buildStorage()->getFooterFragmentForLang($lang)
            ?? throw new \RuntimeException("Footer-Fragment fehlt ({$lang}). SiteFooterRenderer muss zuerst laufen.");
    }

    protected function siteYamlPath(): string
    {
        return Path::join($this->resolveContentBase(), 'site', 'site.yaml');
    }

    protected function loadSiteYaml(): array
    {
        $path = $this->siteYamlPath();
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException("Ungültiges site.yaml: {$path}");
        }
        return $data;
    }

    protected function loadSiteNameKurz(): string
    {
        $name = $this->loadSiteYaml()['name_kurz'] ?? null;
        if ($name === null || $name === '') {
            throw new \RuntimeException("site.yaml: name_kurz fehlt");
        }
        return (string) $name;
    }

    public function validateContent(OutputInterface $output): bool
    {
        return true;
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
