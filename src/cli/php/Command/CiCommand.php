<?php

namespace App\Cli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ci', description: 'Führt die CI-Pipeline aus.')]
final class CiCommand extends BasePipelineCommand
{
    use PythonRunnerAware;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->pythonRunner()->runScript('src/cli/py/ci/runner.py', [], [$this->pipelineName()]);
    }
}
