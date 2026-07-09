<?php

namespace App\Cli\Config;

use Symfony\Component\Filesystem\Path;

final class ConfigInitWriter
{
    public function __construct(private readonly string $rootPath)
    {
    }

    /** @param array<string, array<string, array{desc?: ?string, notes?: ?string, example?: ?string}>> $groupedMissing */
    public function write(string $pipeline, array $groupedMissing): string
    {
        $target = $this->targetPath($pipeline);
        $this->assertTargetMissing($pipeline, $target);
        $this->writeFile($target, $groupedMissing);
        return $target;
    }

    public function targetPath(string $pipeline): string
    {
        return Path::join($this->rootPath, '.local', $pipeline . '.yaml');
    }

    public function targetExists(string $pipeline): bool
    {
        $target = $this->targetPath($pipeline);
        return is_file($target) || is_link($target);
    }

    private function assertTargetMissing(string $pipeline, string $target): void
    {
        if ($this->targetExists($pipeline)) {
            throw new \RuntimeException("Config-Datei existiert bereits: {$target}");
        }
    }

    /** @param array<string, array<string, array{desc?: ?string, notes?: ?string, example?: ?string}>> $groupedMissing */
    private function writeFile(string $target, array $groupedMissing): void
    {
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Verzeichnis konnte nicht angelegt werden: {$dir}");
        }
        if (file_put_contents($target, $this->render($groupedMissing)) === false) {
            throw new \RuntimeException("Schreiben fehlgeschlagen: {$target}");
        }
    }

    /** @param array<string, array<string, array{desc?: ?string, notes?: ?string, example?: ?string}>> $groupedMissing */
    private function render(array $groupedMissing): string
    {
        $lines = [];
        foreach ($groupedMissing as $group => $vars) {
            $lines[] = $group . ':';
            foreach ($vars as $var => $meta) {
                array_push($lines, ...$this->metaCommentLines($meta));
                $lines[] = '  ' . $var . ': ""';
            }
        }
        return implode("\n", $lines) . "\n";
    }

    /** @param array{desc?: ?string, notes?: ?string, example?: ?string} $meta
     *  @return list<string> */
    private function metaCommentLines(array $meta): array
    {
        $lines = [];
        if (($meta['desc'] ?? null) !== null) {
            $lines[] = '  # ' . $meta['desc'];
        }
        if (($meta['example'] ?? null) !== null) {
            $lines[] = '  # Beispiel: ' . $meta['example'];
        }
        return $lines;
    }
}
