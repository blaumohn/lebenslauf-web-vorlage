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

    public function switchTo(DeployState $state): void
    {
        $writeState = function () use ($state): void {
            $this->writer->writeText(
                $this->entryPath . '/.htaccess',
                $state->toHtaccess(),
                0644,
            );
        };
        $this->lockRunner->runWithLock('deploy-switch', $writeState);
    }
}
