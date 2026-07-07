<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use App\Http\Storage\FileStorage;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;

final class BlogPostRenderer extends BaseContentRenderer
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

    public function schemaName(): string
    {
        return self::SCHEMA;
    }

    public function render(OutputInterface $output): void
    {
        $dataPath = $this->dataPath();
        if (!is_dir($dataPath)) {
            $output->writeln("Blog: Verzeichnis nicht gefunden ({$dataPath}), übersprungen.");
            return;
        }
        $posts = $this->discoverSortedPosts($dataPath);
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
        $valid = true;
        foreach ($this->postFiles($dataPath) as $entry) {
            $filePath = Path::join($dataPath, $entry);
            if (!$this->validateYamlFile($filePath, self::SCHEMA, "Blog: {$entry}", $output)) {
                $valid = false;
            }
        }
        return $valid;
    }

    private function discoverSortedPosts(string $dataPath): array
    {
        $posts = array_values($this->discoverYamlFiles($dataPath, ['blog.yaml']));
        usort($posts, static fn(array $a, array $b) => strcmp($b['datum'], $a['datum']));
        return $posts;
    }

    private function renderForLang(array $posts, string $lang, OutputInterface $output): void
    {
        $base = [
            'lang'        => $lang,
            'site_header' => $this->loadHeaderFragment($lang),
            'site_footer' => $this->loadFooterFragment($lang),
        ];
        foreach ($posts as $post) {
            if ($post['status'] !== 'published') {
                continue;
            }
            $resolved = $this->pickLang($post, $lang);
            $resolved['inhalt'] = $this->encodeCodeBlocks((string) $resolved['inhalt']);
            $slug = (string) $post['slug'];
            $html = $this->twig->render('blog_post.html.twig', [
                'post'  => $resolved,
                'title' => $resolved['titel'],
            ] + $base);
            $this->storage->writeText(Path::join($this->htmlPath(), $lang, $slug . '.html'), $html);
            $output->writeln("Blog-Post gerendert: {$slug} ({$lang}).");
        }
    }

    private function postFiles(string $dataPath): array
    {
        $entries = scandir($dataPath);
        if ($entries === false) {
            return [];
        }
        return array_values(array_filter(
            $entries,
            static fn(string $entry) => str_ends_with($entry, '.yaml') && $entry !== 'blog.yaml'
        ));
    }

    private function dataPath(): string
    {
        return Path::join($this->resolveContentBase(), 'blog');
    }

    private function encodeCodeBlocks(string $html): string
    {
        return (string) preg_replace_callback(
            '/<code>(.*?)<\/code>/s',
            function (array $m): string {
                $decoded = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return '<code>' . htmlspecialchars($decoded, ENT_NOQUOTES | ENT_HTML5, 'UTF-8') . '</code>';
            },
            $html
        );
    }

    private function htmlPath(): string
    {
        return Path::join($this->rootPath, 'var', 'cache', 'html', 'blog');
    }
}
