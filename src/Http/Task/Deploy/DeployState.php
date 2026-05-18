<?php

namespace App\Http\Task\Deploy;

use App\Http\Task\Task;

final class DeployState
{
    public function __construct(
        private readonly string $deployId,
        private readonly \DeployRuntimeState $runtime,
    ) {
        if ($deployId === '') {
            throw new \RuntimeException('Deploy-ID fehlt.');
        }
    }

    public static function fromTask(Task $task): self
    {
        return new self(
            $task->get('run_id'),
            \DeployRuntimeState::fromLabels(
                $task->get('app'),
                $task->get('vendor'),
            ),
        );
    }

    public static function fromParams(
        string $deployId,
        string $app,
        string $vendor,
    ): self {
        return new self(
            $deployId,
            \DeployRuntimeState::fromLabels($app, $vendor),
        );
    }

    public function deployId(): string
    {
        return $this->deployId;
    }

    public function appLabel(): string
    {
        return $this->runtime->appLabel();
    }

    public function vendorLabel(): string
    {
        return $this->runtime->vendorLabel();
    }

    public function appRunMarkerPath(): string
    {
        return $this->runtime->appDir() . '/.deploy-run';
    }

    public function vendorRunMarkerPath(): string
    {
        return $this->runtime->vendorDir() . '/.deploy-run';
    }

    public function validatePreparedSlots(string $entryPath): void
    {
        $appMarker = $this->appRunMarkerPath();
        $vendorMarker = $this->vendorRunMarkerPath();

        $this->validatePreparedSlot($entryPath, $appMarker);
        $this->validatePreparedSlot($entryPath, $vendorMarker);
    }

    public function toIni(): string
    {
        return $this->runtime->toIni();
    }

    private function validatePreparedSlot(
        string $entryPath,
        string $markerPath,
    ): void {
        $marker = $this->readRunMarker($entryPath, $markerPath);
        if ($marker === $this->deployId) {
            return;
        }
        $dir = dirname($markerPath);
        throw new \RuntimeException(
            "run_id stimmt nicht überein: {$dir}"
        );
    }

    private function readRunMarker(
        string $entryPath,
        string $markerPath,
    ): string {
        $path = $entryPath . '/' . $markerPath;
        if (is_file($path)) {
            return trim((string) file_get_contents($path));
        }
        $dir = dirname($markerPath);
        throw new \RuntimeException("run_id fehlt: {$dir}");
    }
}
