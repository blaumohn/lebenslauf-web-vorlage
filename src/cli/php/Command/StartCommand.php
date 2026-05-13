<?php

namespace App\Cli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'start', description: 'Startet die lokale Entwicklungsumgebung.')]
final class StartCommand extends BasePipelineCommand
{
    use PythonRunnerAware;

    private const SCRIPT = 'src/cli/py/dev/dev.py';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $values = $this->getValuesByPhase(['build'], $output);
        if ($values === null) {
            return Command::FAILURE;
        }
        return $this->pythonRunner()->runScript(self::SCRIPT, $values, []);
    }
}
