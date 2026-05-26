<?php

namespace App\Http\Task\Deploy;

final class DeployEvent
{
    public function __construct(
        public readonly string $pipelineRunId,
        public readonly string $taskId,
        public readonly string $appBefore,
        public readonly string $vendorBefore,
        public readonly string $appAfter,
        public readonly string $vendorAfter,
    ) {
    }

    public static function fromSwitch(
        SlotSwitchCommand $command,
        string $taskId,
        ?SlotSnapshot $before,
    ): self {
        return new self(
            pipelineRunId: $command->pipelineRunId(),
            taskId:        $taskId,
            appBefore:     $before?->appLabel ?? '',
            vendorBefore:  $before?->vendorLabel ?? '',
            appAfter:      $command->appLabel(),
            vendorAfter:   $command->vendorLabel(),
        );
    }
}
