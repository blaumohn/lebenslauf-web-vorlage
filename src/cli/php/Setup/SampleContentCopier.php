<?php

namespace App\Cli\Setup;

use Symfony\Component\Filesystem\Path;

final class SampleContentCopier
{
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
    }

    public function copy(): string
    {
        $source = $this->sourcePath();
        $target = $this->targetPath();
        $this->assertSourceExists($source);
        $this->assertTargetMissing($target);
        $this->copyDir($source, $target);
        return $target;
    }

    public function sourcePath(): string
    {
        return Path::join($this->rootPath, 'src', 'resources', 'fixtures');
    }

    public function targetPath(): string
    {
        return Path::join($this->rootPath, '.local', 'content');
    }

    private function assertSourceExists(string $source): void
    {
        if (!is_dir($source)) {
            throw new \RuntimeException("Fixtures-Verzeichnis fehlt: {$source}");
        }
    }

    private function assertTargetMissing(string $target): void
    {
        if (is_dir($target)) {
            throw new \RuntimeException("Sample-Ziel existiert bereits: {$target}");
        }
    }

    private function copyDir(string $source, string $target): void
    {
        if (!mkdir($target, 0775, true) && !is_dir($target)) {
            throw new \RuntimeException("Verzeichnis konnte nicht angelegt werden: {$target}");
        }
        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $src = $source . DIRECTORY_SEPARATOR . $item;
            $dst = $target . DIRECTORY_SEPARATOR . $item;
            if (is_dir($src)) {
                $this->copyDir($src, $dst);
            } elseif (!copy($src, $dst)) {
                throw new \RuntimeException("Kopieren fehlgeschlagen: {$dst}");
            }
        }
    }
}
