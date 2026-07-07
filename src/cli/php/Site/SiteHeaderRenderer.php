<?php

namespace App\Cli\Site;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class SiteHeaderRenderer extends BaseContentRenderer
{
    public function render(OutputInterface $output): void
    {
        $siteName = $this->loadSiteNameKurz();
        $nav = $this->loadNav();
        $twig = $this->buildTwig();
        $storage = $this->buildStorage();
        $langs = $this->resolveLangs();

        foreach ($langs as $lang) {
            $navItems = $this->resolveNavItems($nav, $lang);
            $langItems = $this->resolveLangItems($langs, $lang);
            $html = $twig->render('components/site/header.html.twig', [
                'site_name' => $siteName,
                'nav_items' => $navItems,
                'lang_items' => $langItems,
            ]);
            $storage->saveHeaderFragmentForLang($lang, $html);
            $output->writeln("Header-Fragment generiert ({$lang}).");
        }
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

    private function resolveLangItems(array $langs, string $currentLang): array
    {
        $items = [];
        foreach ($langs as $lang) {
            $items[] = [
                'code'    => strtoupper($lang),
                'href'    => $lang !== $currentLang ? '?lang=' . $lang : null,
                'current' => $lang === $currentLang,
            ];
        }
        return $items;
    }

    private function resolveNavItems(array $nav, string $lang): array
    {
        $routes = ['home' => '/', 'cv' => '/cv', 'blog' => '/blog', 'contact' => '/contact'];
        $items = [];
        foreach ($routes as $key => $href) {
            $entry = $nav[$key] ?? [];
            $items[] = ['href' => $href . '?lang=' . $lang, 'label' => $this->resolveLabel($entry, $lang)];
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
}
