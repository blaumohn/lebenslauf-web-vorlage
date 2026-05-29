<?php

namespace App\Http\Task\Deploy;

use App\Http\Runtime\RuntimeAtomicWriter;

final class DeployHistoryWriter
{
    private const HISTORY_FILE = 'log/deploy-history.ndjson';

    public function __construct(
        private readonly RuntimeAtomicWriter $writer,
        private readonly string $deployRoot,
    ) {
    }

    public function record(DeployEvent $event): void
    {
        $entry = json_encode([
            'ts'              => $this->nowUtc(),
            'pipeline_run_id' => $event->pipelineRunId,
            'task_id'         => $event->taskId,
            'app_before'      => $event->appBefore,
            'vendor_before'   => $event->vendorBefore,
            'app_after'       => $event->appAfter,
            'vendor_after'    => $event->vendorAfter,
            'outcome'         => 'ok',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $path = $this->deployRoot . '/' . self::HISTORY_FILE;
        $this->writer->appendLine($path, $entry, 0644);
    }

    private function nowUtc(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format('Y-m-d\TH:i:s\Z');
    }
}
