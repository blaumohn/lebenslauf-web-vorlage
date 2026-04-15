<?php

namespace App\Cli\Util;

use App\Cli\ConfigValues;
use App\Cli\PythonResolver;
use Symfony\Component\Process\Process;

final class PythonRunner
{
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
    }

    public function runScript(
        ConfigValues $config,
        string $script,
        array $args = [],
        bool $interactive = false
    ): int {
        $resolver = new PythonResolver($this->rootPath, $config->all());
        $command = $resolver->findPythonCommand();
        if ($command === null) {
            fwrite(STDERR, "PYTHON_CMD fehlt fuer den Python-Runner.\n");
            return 1;
        }

        $scriptPath = $resolver->scriptPath($script);
        $cmd = array_merge($command, [$scriptPath], $args);
        $env = $this->buildEnv($resolver, $config);
        $process = new Process($cmd, $this->rootPath, $env);
        if ($interactive && Process::isTtySupported()) {
            $process->setTty(true);
        }
        $process->run();
        if (!$process->isSuccessful()) {
            fwrite(STDERR, $process->getErrorOutput());
        }
        return (int) $process->getExitCode();
    }

    private function buildEnv(PythonResolver $resolver, ConfigValues $config): array
    {
        $paths = array_merge(
            $this->configPaths($resolver, $config),
            $this->existingPaths($resolver)
        );
        $paths = $this->uniquePaths($paths);
        if ($paths === []) {
            return $_ENV;
        }
        $pythonPath = implode(PATH_SEPARATOR, $paths);
        return array_merge($_ENV, ['PYTHONPATH' => $pythonPath]);
    }

    private function configPaths(PythonResolver $resolver, ConfigValues $config): array
    {
        $value = trim((string) $config->get('PYTHON_PATHS', ''));
        if ($value === '') {
            return [];
        }
        $parts = explode(PATH_SEPARATOR, $value);
        return $this->normalizePaths($resolver, $parts);
    }

    private function existingPaths(PythonResolver $resolver): array
    {
        $value = getenv('PYTHONPATH');
        if ($value === false || trim($value) === '') {
            return [];
        }
        $parts = explode(PATH_SEPARATOR, $value);
        return $this->normalizePaths($resolver, $parts);
    }

    private function normalizePaths(PythonResolver $resolver, array $paths): array
    {
        $normalized = [];
        foreach ($paths as $path) {
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }
            $normalized[] = $this->resolvePath($resolver, $path);
        }
        return $normalized;
    }

    private function resolvePath(PythonResolver $resolver, string $path): string
    {
        if ($path === '') {
            return $path;
        }
        if ($path[0] === '/' || $path[0] === '\\') {
            return $path;
        }
        if (str_contains($path, ':/')) {
            return $path;
        }
        return $resolver->scriptPath($path);
    }

    private function uniquePaths(array $paths): array
    {
        $seen = [];
        $unique = [];
        foreach ($paths as $path) {
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $unique[] = $path;
        }
        return $unique;
    }
}
