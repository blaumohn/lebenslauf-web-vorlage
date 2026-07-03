<?php

namespace App\Cli\Site;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

final class SiteFooterRenderer extends BaseContentRenderer
{
    public const SCHEMA = 'site.schema.json';

    public function render(OutputInterface $output): void
    {
        $data = $this->loadSiteData($output);
        $footer = $data['footer'];
        $nameKurz = (string) ($data['name_kurz'] ?? '');
        $twig = $this->buildTwig();
        $storage = $this->buildStorage();
        $langs = $this->resolveLangs();

        foreach ($langs as $lang) {
            $vars = $this->resolveVars($footer, $nameKurz, $lang);
            $siteHtml = $twig->render('components/site/footer.html.twig', $vars);
            $cvVars = $vars;
            $cvVars['privacy_note'] = null;
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

    private function loadSiteData(OutputInterface $output): array
    {
        $data = $this->loadSiteYaml();
        $this->assertValid($data, self::SCHEMA, $output);
        return $data;
    }

    private function resolveVars(array $footer, string $nameKurz, string $lang): array
    {
        return [
            'privacy_note' => $this->resolveFooterField($footer['privacy_note'] ?? null, $lang, 'privacy_note'),
            'attribution' => $nameKurz,
            'built_with' => $this->resolveFooterField($footer['built_with'] ?? null, $lang, 'built_with'),
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
}
