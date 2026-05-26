<?php

namespace App\Http\Task\Deploy;

use App\Http\Task\QueuedTask;
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

    public function handle(QueuedTask $task, string $entryPath): TaskResult
    {
        $target = SlotSwitchCommand::fromQueuedTask($task, $entryPath);
        $target->validatePreparedSlots($entryPath);
        $this->switcher->switchTo($target, $task->get('task_id'));
        return TaskResult::ok($this->formatResult($target));
    }

    private function formatResult(SlotSwitchCommand $command): string
    {
        return "app={$command->appLabel()} "
            . "vendor={$command->vendorLabel()} "
            . "pipeline_run_id={$command->pipelineRunId()}";
    }
}
