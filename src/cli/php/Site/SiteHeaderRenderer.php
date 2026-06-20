<?php

namespace App\Cli\Site;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class SiteHeaderRenderer extends BaseContentRenderer
{
    public function render(OutputInterface $output): void
    {
        $siteName = $this->loadSiteName();
        $nav = $this->loadNav();
        $twig = $this->buildTwig();
        $storage = $this->buildStorage();
        $langs = $this->resolveLangs();
        $primary = $langs[0];

        foreach ($langs as $lang) {
            $navItems = $this->resolveNavItems($nav, $lang);
            $html = $twig->render('components/site/header.html.twig', [
                'site_name' => $siteName,
                'nav_items' => $navItems,
            ]);
            $storage->saveHeaderFragmentForLang($lang, $html);
            if ($lang === $primary) {
                $storage->saveHeaderFragment($html);
            }
            $output->writeln("Header-Fragment generiert ({$lang}).");
        }
    }

    private function loadSiteName(): string
    {
        $path = $this->siteYamlPath();
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException("Ungültiges site.yaml: {$path}");
        }
        $name = $data['name_kurz'] ?? null;
        if ($name === null) {
            throw new \RuntimeException("site.yaml: name_kurz fehlt");
        }
        return (string) $name;
    }

    private function loadNav(): array
    {
        $path = Path::join($this->rootPath, 'src', 'resources', 'nav.yaml');
        if (!is_file($path)) {
            throw new \RuntimeException("nav.yaml nicht gefunden: {$path}");
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException("Ungültiges nav.yaml: {$path}");
        }
        return $data;
    }

    private function resolveNavItems(array $nav, string $lang): array
    {
        $routes = ['home' => '/', 'cv' => '/cv', 'contact' => '/contact'];
        $items = [];
        foreach ($routes as $key => $href) {
            $entry = $nav[$key] ?? [];
            $items[] = ['href' => $href, 'label' => $this->resolveLabel($entry, $lang)];
        }
        return $items;
    }

    private function resolveLabel(mixed $entry, string $lang): string
    {
        if (!is_array($entry)) {
            return (string) $entry;
        }
        if (!isset($entry[$lang])) {
            throw new \RuntimeException("nav.yaml: Label fehlt für Sprache '{$lang}'");
        }
        return (string) $entry[$lang];
    }

    private function siteYamlPath(): string
    {
        return Path::join($this->resolveContentBase(), 'site', 'site.yaml');
    }
}
