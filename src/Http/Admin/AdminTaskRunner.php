<?php

namespace App\Http\Admin;

final class AdminTaskRunner
{
    private const TASK_DIR = 'var/admin/tasks';

    /** @param AdminTaskHandler[] $handlers */
    public function __construct(
        private readonly array $handlers,
        private readonly string $entryPath,
    ) {}

    public function runPending(): int
    {
        $taskDir = $this->entryPath . '/' . self::TASK_DIR;
        $files = $this->pendingFiles($taskDir);
        foreach ($files as $file) {
            $this->processTask($file);
        }
        return count($files);
    }

    private function pendingFiles(string $taskDir): array
    {
        if (!is_dir($taskDir)) {
            return [];
        }
        $files = glob($taskDir . '/*.ini') ?: [];
        sort($files);
        return $files;
    }

    private function processTask(string $filePath): void
    {
        $task = AdminTask::fromFile($filePath);
        $handler = $this->resolveHandler($task->type());
        $handler->handle($task, $this->entryPath);
        @unlink($filePath);
    }

    private function resolveHandler(string $type): AdminTaskHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($type)) {
                return $handler;
            }
        }
        throw new \RuntimeException("Kein Handler für Admin-Task-Typ: {$type}");
    }
}
