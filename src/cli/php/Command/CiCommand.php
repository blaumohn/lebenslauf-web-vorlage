<?php

namespace App\Cli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ci', description: 'Führt die CI-Pipeline aus.')]
final class CiCommand extends BasePipelineCommand
{
    use PythonRunnerAware;

    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('pipeline', InputArgument::REQUIRED, 'Pipeline-Name')
            ->addArgument('args', InputArgument::IS_ARRAY, 'Zusätzliche Argumente für das CI-Skript');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = array_merge([$this->pipelineName()], $this->resolveArgs($input));
        return $this->pythonRunner()->runScript('src/cli/py/ci/runner.py', [], $args);
    }

    private function resolveArgs(InputInterface $input): array
    {
        $args = $input->getArgument('args');
        if (!is_array($args)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $args), fn (string $v) => trim($v) !== ''));
    }
}
