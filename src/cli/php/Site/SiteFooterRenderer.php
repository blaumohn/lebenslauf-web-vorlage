<?php

namespace App\Cli\Site;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class SiteFooterRenderer extends BaseContentRenderer
{
    public const SCHEMA = 'site.schema.json';

    public function render(OutputInterface $output): void
    {
        $footer = $this->loadFooterData($output);
        $twig = $this->buildTwig();
        $storage = $this->buildStorage();
        $langs = $this->resolveLangs();

        foreach ($langs as $lang) {
            $vars = $this->resolveVars($footer, $lang);
            $siteHtml = $twig->render('components/site/footer.html.twig', $vars);
            $cvVars = $vars;
            $cvVars['footer1'] = null;
            $cvHtml = $twig->render('components/site/footer.html.twig', $cvVars);
            $storage->saveFooterFragmentForLang($lang, $siteHtml);
            $storage->saveCvFooterFragmentForLang($lang, $cvHtml);
            $output->writeln("Footer-Fragment generiert ({$lang}).");
        }
    }

    public function validateContent(OutputInterface $output): bool
    {
        $path = $this->siteYamlPath();
        if (!is_file($path)) {
            $output->writeln("Site: YAML fehlt ({$path}) — übersprungen.");
            return true;
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            $output->writeln('<error>Site: kein gültiges YAML-Mapping.</error>');
            return false;
        }
        if ($this->checkValid($data, self::SCHEMA, $output)) {
            $output->writeln('Site: OK');
            return true;
        }
        $output->writeln('<error>Site: ungültig.</error>');
        return false;
    }

    private function loadFooterData(OutputInterface $output): array
    {
        $path = $this->siteYamlPath();
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException("Ungültiges site.yaml: {$path}");
        }
        $this->assertValid($data, self::SCHEMA, $output);
        return $data['footer'];
    }

    private function resolveVars(array $footer, string $lang): array
    {
        return [
            'footer1' => $this->resolveFooterField($footer['footer1'] ?? null, $lang, 'footer1'),
            'footer2' => $this->resolveFooterField($footer['footer2'] ?? null, $lang, 'footer2'),
            'source_url' => (string) ($footer['source_url'] ?? ''),
        ];
    }

    private function resolveFooterField(mixed $value, string $lang, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            return (string) $value;
        }
        if (!isset($value[$lang])) {
            throw new \RuntimeException("site.yaml: {$field}.{$lang} fehlt");
        }
        return (string) $value[$lang];
    }

    private function siteYamlPath(): string
    {
        return Path::join($this->resolveContentBase(), 'site', 'site.yaml');
    }
}
