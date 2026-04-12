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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }
        if (strtolower($pipeline) !== 'dev') {
            $output->writeln('<error>run ist nur fuer die Pipeline dev erlaubt.</error>');
            return Command::FAILURE;
        }
        $overrides = $this->requireOverrides($input, $output);
        if ($overrides === null) {
            return Command::FAILURE;
        }
        $config = $this->resolvePipelineConfig($pipeline, $this->commandPhase(), $overrides, $output);
        if ($config === null) {
            return Command::FAILURE;
        }

        $runner = new PythonRunner($this->rootPath());
        $args = $this->devArgs($input, $pipeline);
        return $runner->runScript(
            $config,
            'src/cli/py/dev/dev.py',
            $args,
            $input->isInteractive()
        );
    }

    private function devArgs(InputInterface $input, string $pipeline): array
    {
        $args = ['--pipeline', $pipeline];
        if ($input->getOption('build')) {
            $args[] = '--build';
        }
        return $args;
    }

}
