<?php

namespace App\Http\Task\Deploy;

use App\Http\Task\Task;
use App\Http\Task\TaskHandler;
use App\Http\Task\TaskResult;

final class DeploySwitchTaskHandler implements TaskHandler
{
    public function __construct(
        private readonly DeploySwitcher $switcher,
    ) {
    }

    public function canHandle(string $type): bool
    {
        return $type === 'deploy_switch';
    }

    public function handle(Task $task, string $entryPath): TaskResult
    {
        $target = DeployState::fromTask($task);
        $target->validatePreparedSlots($entryPath);
        $this->switcher->switchTo($target);
        return TaskResult::ok($this->formatResult($target));
    }

    private function formatResult(DeployState $state): string
    {
        return "app={$state->appLabel()} "
            . "vendor={$state->vendorLabel()} "
            . "run_id={$state->deployId()}";
    }
}
