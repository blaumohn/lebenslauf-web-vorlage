<?php

namespace App\Http\Admin\Deploy;

use App\Http\Admin\AdminTask;
use App\Http\Admin\AdminTaskHandler;

final class DeploySwitchTaskHandler implements AdminTaskHandler
{
    public function __construct(
        private readonly DeploySwitcher $switcher,
    ) {}

    public function canHandle(string $type): bool
    {
        return $type === 'deploy_switch';
    }

    public function handle(AdminTask $task, string $entryPath): void
    {
        $preparedPath = $entryPath . '/' . $task->get('prepared_state');
        if ($preparedPath === $entryPath . '/') {
            throw new \RuntimeException("deploy_switch fehlt prepared_state");
        }
        $state = PreparedDeployState::fromFile($preparedPath);
        $this->switcher->switchTo($state);
    }
}
