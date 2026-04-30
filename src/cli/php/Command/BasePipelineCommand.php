<?php

namespace App\Cli\Command;

use App\Cli\ConfigValues;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BasePipelineCommand extends BaseCommand
{
    private ?string $pipelineName = null;
    private array $overrides = [];
    private ?ConfigValues $config = null;

    final protected function configure(): void
    {
        $this->addArgument('pipeline', InputArgument::REQUIRED, 'Pipeline-Name');
        $this->addOption(
            'overrides',
            null,
            InputOption::VALUE_OPTIONAL,
            'Config-Überschreibungen als JSON (phase.gruppe.var oder pipeline.phase.gruppe.var)'
        );
        $this->configurePipelineCommand();
    }

    abstract protected function commandPhase(): string;

    abstract protected function runPipelineCommand(InputInterface $input, OutputInterface $output): int;

    protected function configurePipelineCommand(): void
    {
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }

        $overrides = $this->parseOverrides($input, $output);
        if ($overrides === null) {
            return Command::FAILURE;
        }

        $config = $this->resolvePipelineConfig($pipeline, $this->commandPhase(), $overrides, $output);
        if ($config === null) {
            return Command::FAILURE;
        }

        $this->pipelineName = $pipeline;
        $this->overrides = $overrides;
        $this->config = $config;
        return $this->runPipelineCommand($input, $output);
    }

    protected function pipelineName(): string
    {
        if ($this->pipelineName === null) {
            throw new \LogicException('Pipeline wurde noch nicht aufgeloest.');
        }
        return $this->pipelineName;
    }

    protected function commandOverrides(): array
    {
        return $this->overrides;
    }

    protected function commandConfig(): ConfigValues
    {
        if ($this->config === null) {
            throw new \LogicException('Command-Config wurde noch nicht aufgeloest.');
        }
        return $this->config;
    }

}
