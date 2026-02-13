<?php

namespace App\Http\Security;

final class IpSaltActionPlan
{
    private IpSaltResetExecutor $resetExecutor;
    /** @var array<string, string> */
    private array $handlers;

    public function __construct(IpSaltResetExecutor $resetExecutor)
    {
        $this->resetExecutor = $resetExecutor;
        $this->handlers = $this->buildHandlers();
    }

    public function execute(TriggerReason $reason, IpSaltState $state): IpSaltState
    {
        $handler = $this->resolveHandler($reason);
        $next = $this->{$handler}($state);
        return $next;
    }

    /** @return array<string, string> */
    private function buildHandlers(): array
    {
        return [
            TriggerReason::CLEAN->value => 'handleClean',
            TriggerReason::MISSING->value => 'handleResetPath',
            TriggerReason::INVALID->value => 'handleResetPath',
            TriggerReason::MISMATCH->value => 'handleResetPath',
            TriggerReason::EXPLICIT_RESET->value => 'handleResetPath',
        ];
    }

    private function resolveHandler(TriggerReason $reason): string
    {
        $handler = $this->handlers[$reason->value] ?? null;
        if (is_string($handler) && $handler !== '') {
            return $handler;
        }
        throw new \RuntimeException("Kein Action-Handler fuer Trigger {$reason->value}.");
    }

    private function handleClean(IpSaltState $state): IpSaltState
    {
        return $state;
    }

    private function handleResetPath(IpSaltState $state): IpSaltState
    {
        $inProgress = $this->resetExecutor->markInProgress($state);
        $ready = $this->resetExecutor->rotateAndClear($inProgress);
        return $ready;
    }
}
