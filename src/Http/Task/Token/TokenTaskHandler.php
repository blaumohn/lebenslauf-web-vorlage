<?php

namespace App\Http\Task\Token;

use App\Http\Security\TokenIssuanceService;
use App\Http\Task\QueuedTask;
use App\Http\Task\TaskHandler;
use App\Http\Task\TaskResult;

/**
 * Task-Handler für Token-Verwaltung, wiederverwendbar über Seitentypen hinweg:
 * eine Instanz pro Seitentyp, unterschieden über $resource (z. B. "cv"),
 * das die Task-Typen "{$resource}_token_add" usw. ergibt.
 */
final class TokenTaskHandler implements TaskHandler
{
    /** @param non-empty-string $resource */
    public function __construct(
        private readonly TokenIssuanceService $issuanceService,
        private readonly string $resource,
    ) {}

    public function canHandle(string $type): bool
    {
        return in_array($type, $this->types(), true);
    }

    public function handle(QueuedTask $task, string $appRoot): TaskResult
    {
        return match ($this->action($task->type())) {
            'add' => $this->handleAdd($task),
            'list' => $this->handleList($task),
            'revoke' => $this->handleRevoke($task),
            default => TaskResult::fail("Unbekannter Token-Task-Typ: {$task->type()}"),
        };
    }

    /** @return list<string> */
    private function types(): array
    {
        return array_map(
            fn (string $action): string => "{$this->resource}_token_{$action}",
            ['add', 'list', 'revoke']
        );
    }

    private function action(string $type): string
    {
        return str_starts_with($type, "{$this->resource}_token_")
            ? substr($type, strlen("{$this->resource}_token_"))
            : '';
    }

    private function handleAdd(QueuedTask $task): TaskResult
    {
        $profile = $task->get('profile');
        $count = max(1, (int) $task->get('count'));
        $label = $task->get('label') !== '' ? $task->get('label') : null;
        $expiresAtRaw = $task->get('expires_at');
        $expiresAt = $expiresAtRaw !== '' ? (int) $expiresAtRaw : null;

        $tokens = $this->issuanceService->add($profile, $count, $expiresAt, $label);
        return TaskResult::ok(implode("\n", $tokens));
    }

    private function handleList(QueuedTask $task): TaskResult
    {
        $entries = $this->issuanceService->list($task->get('profile'));
        $encoded = json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            return TaskResult::fail('Token-Liste konnte nicht serialisiert werden.');
        }
        return TaskResult::ok($encoded);
    }

    private function handleRevoke(QueuedTask $task): TaskResult
    {
        $profile = $task->get('profile');
        $identifier = $task->get('identifier');
        if ($identifier === 'all') {
            $this->issuanceService->revokeAll($profile);
            return TaskResult::ok('alle Freigaben entfernt');
        }

        $removed = $this->issuanceService->revoke($profile, $identifier);
        return TaskResult::ok("{$removed} Freigabe(n) entfernt");
    }
}
