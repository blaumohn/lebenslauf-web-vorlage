<?php

namespace App\Cli;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

final class PythonResolver
{
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
    }

    public function createVenv(string $path, bool $interactive = false): bool
    {
        $target = Path::join($this->rootPath, ltrim($path, DIRECTORY_SEPARATOR));
        if (is_dir($target)) {
            return true;
        }
        $python = $this->findSystemPython();
        if ($python === null) {
            return false;
        }
        $process = new Process([$python, '-m', 'venv', $target], $this->rootPath);
        if ($interactive && Process::isTtySupported()) {
            $process->setTty(true);
        }
        $process->run();
        if (!$process->isSuccessful()) {
            fwrite(STDERR, $process->getErrorOutput());
        }
        return $process->isSuccessful();
    }

    private function findSystemPython(): ?string
    {
        foreach (['python3', 'python'] as $candidate) {
            $path = trim((string) $this->which($candidate));
            if ($path !== '' && $this->isPython3($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function isPython3(string $binary): bool
    {
        $cmd = escapeshellarg($binary) . ' -c ' . escapeshellarg('import sys; print(sys.version_info[0])');
        return trim((string) shell_exec($cmd)) === '3';
    }

    private function which(string $binary): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return (string) shell_exec("where {$binary}");
        }
        return (string) shell_exec("command -v {$binary}");
    }
}
