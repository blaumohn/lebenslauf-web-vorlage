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

    public function copy(string $profile): string
    {
        $source = $this->sourcePath();
        $target = $this->targetPath($profile);
        $this->assertSourceExists($source);
        $this->assertTargetMissing($target);
        $this->ensureTargetDir($target);
        $this->copyFile($source, $target);
        return $target;
    }

    public function sourcePath(): string
    {
        return Path::join(
            $this->rootPath,
            'src',
            'resources',
            'fixtures',
            'lebenslauf',
            'daten-gueltig.yaml'
        );
    }

    public function targetPath(string $profile): string
    {
        $this->assertProfileName($profile);
        return Path::join($this->rootPath, '.local', 'lebenslauf', "daten-{$profile}.yaml");
    }

    private function assertProfileName(string $profile): void
    {
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $profile)) {
            throw new \RuntimeException("Profilname ist ungueltig: {$profile}");
        }
    }

    private function assertSourceExists(string $source): void
    {
        if (!is_file($source)) {
            throw new \RuntimeException("Datei fehlt: {$source}");
        }
    }

    private function assertTargetMissing(string $target): void
    {
        if (is_file($target)) {
            throw new \RuntimeException("Sample-Ziel existiert bereits: {$target}");
        }
    }

    private function ensureTargetDir(string $target): void
    {
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private function copyFile(string $source, string $target): void
    {
        if (!copy($source, $target)) {
            throw new \RuntimeException("Kopieren fehlgeschlagen: {$target}");
        }
    }
}
