<?php

namespace App\Http\Runtime;

final class RuntimeAtomicWriter
{
    public function writeText(string $path, string $content, int $mode = 0600): void
    {
        $dir = dirname($path);
        $this->ensureDir($dir);
        $tmpPath = $this->buildTmpPath($path);
        $this->writeTmpFile($tmpPath, $content);
        $this->applyMode($tmpPath, $mode);
        $this->moveIntoPlace($tmpPath, $path, $mode);
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (@mkdir($dir, 0775, true) || is_dir($dir)) {
            return;
        }
        throw new \RuntimeException("Verzeichnis konnte nicht angelegt werden: {$dir}");
    }

    private function buildTmpPath(string $path): string
    {
        $suffix = bin2hex(random_bytes(6));
        return $path . '.tmp.' . $suffix;
    }

    private function writeTmpFile(string $tmpPath, string $content): void
    {
        $written = file_put_contents($tmpPath, $content);
        if ($written !== false) {
            return;
        }
        throw new \RuntimeException("Temp-Datei konnte nicht geschrieben werden: {$tmpPath}");
    }

    private function moveIntoPlace(string $tmpPath, string $target, int $mode): void
    {
        if (@rename($tmpPath, $target)) {
            $this->applyMode($target, $mode);
            return;
        }
        $this->safeUnlink($tmpPath);
        throw new \RuntimeException("Datei konnte nicht atomar ersetzt werden: {$target}");
    }

    private function applyMode(string $path, int $mode): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return;
        }
        if (@chmod($path, $mode)) {
            return;
        }
        throw new \RuntimeException("Dateirechte konnten nicht gesetzt werden: {$path}");
    }

    public function appendLine(string $path, string $line, int $mode = 0600): void
    {
        $this->ensureDir(dirname($path));
        $written = file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException("Zeile konnte nicht angehängt werden: {$path}");
        }
        $this->applyMode($path, $mode);
    }

    private function safeUnlink(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
