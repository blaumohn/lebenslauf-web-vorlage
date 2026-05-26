<?php

namespace App\Http\Task\Deploy;

use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;

final class DeploySwitcher
{
    public function __construct(
        private readonly RuntimeAtomicWriter $writer,
        private readonly RuntimeLockRunner $lockRunner,
        private readonly string $entryPath,
    ) {
    }

    public function switchTo(SlotSwitchCommand $command, string $taskId): void
    {
        $reader = new CurrentSlotReader($this->entryPath);
        $slotBefore = $reader->readCurrent();
        $historyWriter = new DeployHistoryWriter($this->writer, $this->entryPath);
        $operation = $this->buildSwitchOperation($command, $taskId, $slotBefore, $historyWriter);
        $this->lockRunner->runWithLock('deploy-switch', $operation);
    }

    private function buildSwitchOperation(
        SlotSwitchCommand $command,
        string $taskId,
        ?SlotSnapshot $slotBefore,
        DeployHistoryWriter $historyWriter,
    ): \Closure {
        return function () use ($command, $taskId, $slotBefore, $historyWriter): void {
            $this->writer->writeText(
                $this->entryPath . '/.htaccess',
                $command->toHtaccess(),
                0644,
            );
            $historyWriter->record(
                DeployEvent::fromSwitch($command, $taskId, $slotBefore),
            );
        };
    }
}
