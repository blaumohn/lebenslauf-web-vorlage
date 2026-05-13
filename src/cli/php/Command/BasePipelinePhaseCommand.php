<?php

namespace App\Cli\Command;

use App\Cli\ConfigValues;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BasePipelinePhaseCommand extends BasePipelineCommand
{
    private ?ConfigValues $config = null;

    abstract protected function commandPhase(): string;

    abstract protected function runPipelineCommand(InputInterface $input, OutputInterface $output): int;

    protected function configurePipelineCommand(): void
    {
    }

    final protected function configure(): void
    {
        parent::configure();
        $this->configurePipelineCommand();
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->resolvePipelineConfig($this->commandPhase(), $output);
        if ($config === null) {
            return Command::FAILURE;
        }
        $this->config = $config;
        return $this->runPipelineCommand($input, $output);
    }

    protected function commandConfig(): ConfigValues
    {
        if ($this->config === null) {
            throw new \LogicException('Command-Config wurde noch nicht aufgeloest.');
        }
        return $this->config;
    }
}
