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
    public const SCHEMA = 'blog-post.schema.json';

    private Environment $twig;
    private FileStorage $storage;

    public function __construct(ConfigValues $config, string $rootPath)
    {
        parent::__construct($config, $rootPath);
        $this->twig = $this->buildTwig();
        $this->storage = new FileStorage();
    }

    public function sectionKey(): ?string
    {
        return 'blog';
    }

    public function render(OutputInterface $output): void
    {
        $dataPath = $this->dataPath();
        if (!is_dir($dataPath)) {
            $output->writeln("Blog: Verzeichnis nicht gefunden ({$dataPath}), übersprungen.");
            return;
        }
        $posts = $this->discoverPosts($dataPath, $output);
        if ($posts === []) {
            $output->writeln("Blog: keine YAML-Dateien gefunden in {$dataPath}.");
            return;
        }
        foreach ($this->resolveLangs() as $lang) {
            $this->renderForLang($posts, $lang, $output);
        }
    }

    public function validateContent(OutputInterface $output): bool
    {
        $dataPath = $this->dataPath();
        if (!is_dir($dataPath)) {
            $output->writeln("Blog: Verzeichnis fehlt ({$dataPath}) — übersprungen.");
            return true;
        }
        $entries = scandir($dataPath) ?: [];
        $valid = true;
        foreach ($entries as $entry) {
            if (!str_ends_with($entry, '.yaml')) {
                continue;
            }
            $filePath = Path::join($dataPath, $entry);
            $data = Yaml::parseFile($filePath);
            if (!is_array($data)) {
                $output->writeln("<error>Blog: {$entry}: kein gültiges YAML-Mapping.</error>");
                $valid = false;
                continue;
            }
            if ($this->checkValid($data, self::SCHEMA, $output)) {
                $output->writeln("Blog: {$entry}: OK");
            } else {
                $output->writeln("<error>Blog: {$entry}: ungültig.</error>");
                $valid = false;
            }
        }
        return $valid;
    }

    private function discoverPosts(string $dataPath, OutputInterface $output): array
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
            if (!is_array($data)) {
                continue;
            }
            $this->assertValid($data, self::SCHEMA, $output);
            $posts[] = $data;
        }
        return $posts;
    }

    private function renderForLang(array $posts, string $lang, OutputInterface $output): void
    {
        $base = [
            'lang'        => $lang,
            'site_header' => $this->loadHeaderFragment($lang),
            'site_footer' => $this->loadFooterFragment($lang),
        ];
        $published = [];
        foreach ($posts as $post) {
            if (($post['status'] ?? '') !== 'published') {
                continue;
            }
            $resolved = $this->pickLang($post, $lang);
            $slug = (string) ($post['slug'] ?? '');
            $html = $this->twig->render('blog_post.html.twig', ['post' => $resolved] + $base);
            $this->storage->writeText(Path::join($this->htmlPath(), $lang, $slug . '.html'), $html);
            $published[] = $resolved;
            $output->writeln("Blog-Post gerendert: {$slug} ({$lang}).");
        }
        $html = $this->twig->render('blog_index.html.twig', ['posts' => $published] + $base);
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
