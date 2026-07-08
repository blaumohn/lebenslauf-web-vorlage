<?php

namespace App\Http\Task;

use App\Http\Mail\MailMessage;
use App\Http\Mail\MailService;
use App\Http\Runtime\RuntimeAtomicWriter;
use Psr\Log\LoggerInterface;

final class TaskRunner
{
    private const TASK_DIR = 'var/tasks';
    private const RESULT_DIR = 'var/tasks/results';

    /** @param TaskHandler[] $handlers */
    public function __construct(
        private readonly array $handlers,
        private readonly string $appRoot,
        private readonly MailService $mailService,
        private readonly LoggerInterface $logger,
        private readonly RuntimeAtomicWriter $writer,
    ) {}

    public function runPending(): int
    {
        $taskDir = $this->appRoot . '/' . self::TASK_DIR;
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
        [$result, $taskId] = $this->executeTask($filePath, $taskName);
        if (is_file($filePath)) {
            unlink($filePath);
        }
        $this->writeResult($taskId, $result);
        $this->tryNotifyResult($result, $taskName);
    }

    /** @return array{0: TaskResult, 1: string} */
    private function executeTask(string $filePath, string $taskName): array
    {
        $taskId = '';
        try {
            $task = QueuedTaskFile::load($filePath);
            $taskId = $task->get('task_id');
            $result = $this->resolveHandler($task->type())->handle($task, $this->appRoot);
            return [$result, $taskId];
        } catch (\Throwable $e) {
            $this->logger->error("Task fehlgeschlagen: {$taskName}", ['exception' => $e]);
            return [TaskResult::fail($e->getMessage()), $taskId];
        }
    }

    private function writeResult(string $taskId, TaskResult $result): void
    {
        if ($taskId === '') {
            return;
        }
        $content = $result->success
            ? ($result->body === '' ? 'ok' : "ok\n" . $result->body)
            : 'error: ' . $result->body;
        $path = $this->appRoot . '/' . self::RESULT_DIR . '/' . $taskId . '.result';
        $this->writer->writeText($path, $content, 0644);
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

    private function tryNotifyResult(TaskResult $result, string $taskName): void
    {
        try {
            $this->notifyResult($result, $taskName);
        } catch (\Throwable $e) {
            $this->logger->error("Mailversand fehlgeschlagen: {$taskName}", ['exception' => $e]);
        }
    }

    private function notifyResult(TaskResult $result, string $taskName): void
    {
        $title = $result->success
            ? "Task abgeschlossen: {$taskName}"
            : "Task fehlgeschlagen: {$taskName}";
        $mailBody = $result->mailExtra === '' ? $result->body : $result->body . "\n\n" . $result->mailExtra;
        $this->mailService->send(new MailMessage(module: 'Task', title: $title, body: $mailBody));
    }
}
