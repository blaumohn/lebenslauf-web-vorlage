<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use App\Http\Storage\FileStorage;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

final class BlogContentRenderer extends BaseContentRenderer
{
    private Environment $twig;
    private FileStorage $storage;

    public function __construct(ConfigValues $config, string $rootPath)
    {
        parent::__construct($config, $rootPath);
        $this->twig = $this->buildTwig();
        $this->storage = new FileStorage();
    }

    public function render(OutputInterface $output): void
    {
        $dataPath = $this->dataPath();
        if (!is_dir($dataPath)) {
            $output->writeln("Blog: Verzeichnis nicht gefunden ({$dataPath}), übersprungen.");
            return;
        }
        $posts = $this->discoverPosts($dataPath);
        if ($posts === []) {
            $output->writeln("Blog: keine YAML-Dateien gefunden in {$dataPath}.");
            return;
        }
        foreach ($this->resolveLangs() as $lang) {
            $this->renderForLang($posts, $lang, $output);
        }
    }

    private function discoverPosts(string $dataPath): array
    {
        $entries = scandir($dataPath);
        if ($entries === false) {
            return [];
        }
        $posts = [];
        foreach ($entries as $entry) {
            if (!str_ends_with($entry, '.yaml')) {
                continue;
            }
            $data = Yaml::parseFile(Path::join($dataPath, $entry));
            if (is_array($data)) {
                $posts[] = $data;
            }
        }
        return $posts;
    }

    private function renderForLang(array $posts, string $lang, OutputInterface $output): void
    {
        $published = [];
        foreach ($posts as $post) {
            if (($post['status'] ?? '') !== 'published') {
                continue;
            }
            $resolved = $this->pickLang($post, $lang);
            $slug = (string) ($post['slug'] ?? '');
            $html = $this->twig->render('blog_post.html.twig', ['post' => $resolved, 'lang' => $lang]);
            $this->storage->writeText(Path::join($this->htmlPath(), $lang, $slug . '.html'), $html);
            $published[] = $resolved;
            $output->writeln("Blog-Post gerendert: {$slug} ({$lang}).");
        }
        $this->renderIndex($published, $lang, $output);
    }

    private function renderIndex(array $posts, string $lang, OutputInterface $output): void
    {
        $html = $this->twig->render('blog_index.html.twig', ['posts' => $posts, 'lang' => $lang]);
        $this->storage->writeText(Path::join($this->htmlPath(), $lang, 'index.html'), $html);
        $output->writeln("Blog-Index gerendert ({$lang}).");
    }

    private function dataPath(): string
    {
        return Path::join($this->resolveContentBase(), 'blog');
    }

    private function htmlPath(): string
    {
        return Path::join($this->rootPath, 'var', 'cache', 'html', 'blog');
    }
}
