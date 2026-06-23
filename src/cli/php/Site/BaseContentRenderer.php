<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use App\Http\SiteHtmlCache;
use App\Http\SchemaValidator;
use App\Http\Storage\FileStorage;
use App\Http\Templating\TwigFactory;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
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
        return $this->normalizeLangs(preg_split('/\s*,\s*/', $this->config->get('CONTENT_LANGS')) ?: []);
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

    protected function assertValid(mixed $data, string $schemaName, OutputInterface $output): void
    {
        $schemaPath = Path::join($this->rootPath, 'src', 'resources', 'build', 'schemas', $schemaName);
        $errors = (new SchemaValidator($schemaPath))->validate($data);
        if ($errors === []) {
            return;
        }
        $output->writeln("<error>{$schemaName}: Schema-Validierung fehlgeschlagen:</error>");
        foreach ($errors as $error) {
            $output->writeln("- {$error}");
        }
        throw new \RuntimeException("{$schemaName}: Schema-Validierung fehlgeschlagen.");
    }
}
