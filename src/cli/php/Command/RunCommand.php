<?php

namespace App\Cli\Command;

use App\Cli\Util\PythonRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'run', description: 'Startet den lokalen Dev-Server.')]
final class RunCommand extends BasePipelineCommand
{
    protected function commandPhase(): string
    {
        return 'python';
    }

    protected function configurePipelineCommand(): void
    {
        $this->addOption('build', null, InputOption::VALUE_NONE, 'Vor dem Start cv build ausfuehren');
    }

    protected function runPipelineCommand(InputInterface $input, OutputInterface $output): int
    {
        if (strtolower($this->pipelineName()) !== 'dev') {
            $output->writeln('<error>run ist nur fuer die Pipeline dev erlaubt.</error>');
            return Command::FAILURE;
        }

        $runner = new PythonRunner($this->rootPath());
        $args = $this->devArgs($input);
        return $runner->runScript(
            $this->commandConfig(),
            'src/cli/py/dev/dev.py',
            $args,
            $input->isInteractive()
        );
    }

    private function devArgs(InputInterface $input): array
    {
        $args = ['--pipeline', $this->pipelineName()];
        if ($input->getOption('build')) {
            $args[] = '--build';
        }
        return $args;
    }

}
