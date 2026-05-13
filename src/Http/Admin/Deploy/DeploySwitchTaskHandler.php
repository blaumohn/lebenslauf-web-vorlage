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
        $app    = $task->get('app');
        $vendor = $task->get('vendor');
        $runId  = $task->get('run_id');
        if ($app === '' || $vendor === '' || $runId === '') {
            throw new \RuntimeException("deploy_switch fehlt app, vendor oder run_id");
        }
        $this->verifyRunMarkers($entryPath, $app, $vendor, $runId);
        $state = PreparedDeployState::fromParams($app, $vendor);
        $this->switcher->switchTo($state);
    }

    private function verifyRunMarkers(string $entryPath, string $app, string $vendor, string $runId): void
    {
        foreach ([$app, "vendor-{$vendor}"] as $slot) {
            $marker = trim((string) file_get_contents("{$entryPath}/{$slot}/.deploy-run"));
            if ($marker !== $runId) {
                throw new \RuntimeException("run_id stimmt nicht überein: {$slot}");
            }
        }
    }
}
