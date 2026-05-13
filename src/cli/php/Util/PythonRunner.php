<?php

namespace App\Cli\Util;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

final class PythonRunner
{
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
    }

    public function runScript(
        string $script,
        array $pipelineValues = [],
        array $args = []
    ): int {
        $command = $this->resolveCommand();
        if ($command === null) {
            fwrite(STDERR, "Python-Venv fehlt. Bitte zuerst setup ausführen.\n");
            return 1;
        }
        $scriptPath = Path::join($this->rootPath, $script);
        $cmd = array_merge($command, [$scriptPath], $args);
        $env = $this->buildEnv($pipelineValues);
        $process = new Process($cmd, $this->rootPath, $env);
        $process->setTimeout(null);
        if (Process::isTtySupported()) {
            $process->setTty(true);
            $process->run();
        } else {
            $process->run(fn ($type, $buffer) => fwrite(
                $type === Process::ERR ? STDERR : STDOUT,
                $buffer
            ));
        }
        return (int) $process->getExitCode();
    }

    private function resolveCommand(): ?array
    {
        $venv = Path::join($this->rootPath, '.venv', 'bin', 'python');
        return is_file($venv) ? [$venv] : null;
    }

    private function buildEnv(array $pipelineValues): array
    {
        $extras = ['PIPELINE_CFG_JSON' => $this->configJson($pipelineValues)];
        $pythonPath = $this->buildPythonPath();
        if ($pythonPath !== '') {
            $extras['PYTHONPATH'] = $pythonPath;
        }
        return array_merge($_ENV, $extras);
    }

    private function buildPythonPath(): string
    {
        $src = Path::join($this->rootPath, 'src');
        $scripts = Path::join($this->rootPath, 'scripts');
        $base = $src . PATH_SEPARATOR . $scripts;
        $existing = getenv('PYTHONPATH');
        if ($existing === false || $existing === '') {
            return $base;
        }
        return $base . PATH_SEPARATOR . $existing;
    }

    private function configJson(array $values): string
    {
        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
