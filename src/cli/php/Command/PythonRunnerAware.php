<?php

namespace App\Cli\Command;

use App\Cli\Util\PythonRunner;
use Symfony\Component\Console\Output\OutputInterface;

trait PythonRunnerAware
{
    protected function buildPhaseValues(array $phases, OutputInterface $output): ?array
    {
        $result = [];
        foreach ($phases as $phase) {
            try {
                $result[$phase] = $this->pipelineValues($phase);
            } catch (\RuntimeException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return null;
            }
        }
        return $result;
    }

    protected function pythonRunner(): PythonRunner
    {
        return new PythonRunner($this->rootPath());
    }
}
