<?php

namespace App\Cli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ci', description: 'Führt die CI-Pipeline aus.')]
final class CiCommand extends BaseCliCommand
{
    use PythonRunnerAware;

    private const SCRIPT = 'src/cli/py/ci/runner.py';

    protected function configure(): void
    {
        $this->addArgument('pipeline', InputArgument::REQUIRED, 'Pipeline-Name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pipeline = trim((string) $input->getArgument('pipeline'));
        return $this->pythonRunner()->runScript(self::SCRIPT, [], [$pipeline]);
    }
}
