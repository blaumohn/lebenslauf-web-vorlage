<?php

namespace App\Http\Task;

use App\Http\Mail\MailMessage;
use App\Http\Mail\MailService;
use Psr\Log\LoggerInterface;

final class TaskRunner
{
    private const TASK_DIR = 'var/tasks';

    /** @param TaskHandler[] $handlers */
    public function __construct(
        private readonly array $handlers,
        private readonly string $entryPath,
        private readonly MailService $mailService,
        private readonly LoggerInterface $logger,
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
        $taskName = basename($filePath, '.ini');
        $result = $this->executeTask($filePath, $taskName);
        if (is_file($filePath)) {
            unlink($filePath);
        }
        $this->tryNotifyResult($result, $taskName);
    }

    private function executeTask(string $filePath, string $taskName): TaskResult
    {
        try {
            $task = Task::fromFile($filePath);
            return $this->resolveHandler($task->type())->handle($task, $this->entryPath);
        } catch (\Throwable $e) {
            $this->logger->error("Task fehlgeschlagen: {$taskName}", ['exception' => $e]);
            return TaskResult::fail($e->getMessage());
        }
    }

    private function tryNotifyResult(TaskResult $result, string $taskName): void
    {
        try {
            $this->notifyResult($result, $taskName);
        } catch (\Throwable $e) {
            $this->logger->error("Mailversand fehlgeschlagen: {$taskName}", ['exception' => $e]);
        }
    }

    private function resolveHandler(string $type): TaskHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($type)) {
                return $handler;
            }
        }
        throw new \RuntimeException("Kein Handler für Task-Typ: {$type}");
    }

    private function notifyResult(TaskResult $result, string $taskName): void
    {
        $title = $result->success
            ? "Task abgeschlossen: {$taskName}"
            : "Task fehlgeschlagen: {$taskName}";
        $this->mailService->send(new MailMessage(module: 'Task', title: $title, body: $result->body));
    }
}
