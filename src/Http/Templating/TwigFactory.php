<?php

namespace App\Http\Templating;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class TwigFactory
{
    public static function create(string $templatesPath): Environment
    {
        $loader = new FilesystemLoader($templatesPath);
        return new Environment($loader, [
            'cache' => false,
            'autoescape' => 'html',
        ]);
    }

    public static function configure(Environment $twig, string $basePath): void
    {
        $normalized = self::normalizeBasePath($basePath);
        $twig->addFunction(new TwigFunction('path', fn(string $path) => self::prefixPath($normalized, $path)));
    }

    private static function normalizeBasePath(string $basePath): string
    {
        $trimmed = trim($basePath);
        if ($trimmed === '' || $trimmed === '/') {
            return '';
        }
        return '/' . trim($trimmed, '/');
    }

    private static function prefixPath(string $basePath, string $path): string
    {
        $path = $path === '' ? '/' : $path;
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        return $basePath === '' ? $path : $basePath . $path;
    }
}
