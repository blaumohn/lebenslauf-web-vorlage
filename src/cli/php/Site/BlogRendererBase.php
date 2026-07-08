<?php

namespace App\Cli\Site;

use Symfony\Component\Filesystem\Path;

abstract class BlogRendererBase extends BaseContentRenderer
{
    protected function blogDir(): string
    {
        return Path::join($this->resolveContentBase(), 'blog');
    }

    protected function discoverSortedPosts(string $blogDir): array
    {
        $posts = array_values($this->discoverYamlFiles($blogDir, ['blog.yaml']));
        usort($posts, static fn(array $a, array $b) => strcmp($b['datum'], $a['datum']));
        return $posts;
    }

    protected function htmlPath(): string
    {
        return Path::join($this->rootPath, 'var', 'cache', 'html', 'blog');
    }
}
