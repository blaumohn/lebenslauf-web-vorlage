<?php

namespace App\Http\Task\Cv;

use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Task\QueuedTask;
use App\Http\Task\TaskHandler;
use App\Http\Task\TaskResult;
use Symfony\Component\Filesystem\Path;

final class CvPublishTaskHandler implements TaskHandler
{
    private const STAGING_PATH = 'var/tmp/html-publish';
    private const TARGET_PATH = 'var/cache/html';

    public function __construct(
        private readonly RuntimeAtomicWriter $writer,
    ) {}

    public function canHandle(string $type): bool
    {
        return $type === 'cv_publish';
    }

    public function handle(QueuedTask $task, string $appRoot): TaskResult
    {
        $staging = Path::join($appRoot, self::STAGING_PATH);
        $target = Path::join($appRoot, self::TARGET_PATH);
        $count = $this->publishHtmlFiles($staging, $target);
        $this->cleanupStaging($staging);
        return TaskResult::ok("HTML veröffentlicht: {$count} Datei(en)");
    }

    private function publishHtmlFiles(string $staging, string $target): int
    {
        $files = $this->findHtmlFiles($staging);
        if ($files === []) {
            throw new \RuntimeException("Keine HTML-Dateien in: {$staging}");
        }
        foreach ($files as $file) {
            $this->publishFile($file, Path::join($target, $this->relativePath($staging, $file)));
        }
        return count($files);
    }

    private function findHtmlFiles(string $staging): array
    {
        if (!is_dir($staging)) {
            return [];
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($staging, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'html') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        return $files;
    }

    private function relativePath(string $base, string $path): string
    {
        return substr($path, strlen(rtrim($base, DIRECTORY_SEPARATOR)) + 1);
    }

    private function publishFile(string $source, string $target): void
    {
        $content = file_get_contents($source);
        if ($content === false) {
            throw new \RuntimeException("HTML-Datei nicht lesbar: {$source}");
        }
        $this->writer->writeText($target, $content, 0644);
    }

    private function cleanupStaging(string $staging): void
    {
        if (!is_dir($staging)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($staging, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                @rmdir($entry->getPathname());
                continue;
            }
            @unlink($entry->getPathname());
        }
        @rmdir($staging);
    }
}
