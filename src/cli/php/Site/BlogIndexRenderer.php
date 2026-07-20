<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use App\Http\Storage\FileStorage;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;

final class BlogIndexRenderer extends BlogRendererBase
{
    public const SCHEMA = 'blog.schema.json';

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
        return null;
    }

    public function schemaNames(): array
    {
        return [self::SCHEMA];
    }

    public function render(OutputInterface $output): void
    {
        $blogDir = $this->blogDir();
        if (!is_dir($blogDir)) {
            return;
        }
        $introData = $this->loadYamlFile($this->introPath());
        $posts = $this->discoverSortedPosts($blogDir);
        foreach ($this->resolveLangs() as $lang) {
            $intro = $introData === null ? null : self::resolveBlogIntro($introData['intro'] ?? null, $lang);
            $introHtml = $intro === null ? null : $this->renderMarkdown(
                $this->twig,
                $intro,
                $lang,
                "blog.index.{$lang}.intro"
            );
            $html = $this->twig->render('blog_index.html.twig', [
                'lang'        => $lang,
                'title'       => 'Blog',
                'site_header' => $this->loadHeaderFragment($lang),
                'site_footer' => $this->loadFooterFragment($lang),
                'intro_html'  => $introHtml,
                'posts'       => $this->publishedForLang($posts, $lang),
            ]);
            $this->storage->writeText(Path::join($this->htmlPath(), $lang, 'index.html'), $html);
        }
    }

    public function validateContent(OutputInterface $output): bool
    {
        return $this->validateYamlFile($this->introPath(), self::SCHEMA, 'Blog: blog.yaml', $output);
    }

    public static function resolveBlogIntro(mixed $intro, string $lang): ?string
    {
        if ($intro === null) {
            return null;
        }
        if (!is_array($intro)) {
            return (string) $intro;
        }
        if (!isset($intro[$lang])) {
            throw new \RuntimeException("blog.yaml: intro.{$lang} fehlt");
        }
        return (string) $intro[$lang];
    }

    private function publishedForLang(array $posts, string $lang): array
    {
        $published = [];
        foreach ($posts as $post) {
            if ($post['status'] !== 'published') {
                continue;
            }
            $published[] = $this->pickLang($post, $lang);
        }
        return $published;
    }

    private function introPath(): string
    {
        return Path::join($this->blogDir(), 'blog.yaml');
    }
}
