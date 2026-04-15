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
            'override',
            null,
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Config-Ueberschreibung als KEY=VALUE'
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

        $overrides = $this->requireOverrides($input, $output);
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

    protected function requireOverrides(InputInterface $input, OutputInterface $output): ?array
    {
        $rawOverrides = $input->getOption('override');
        if (!is_array($rawOverrides)) {
            return [];
        }

        $overrides = [];
        foreach ($rawOverrides as $rawOverride) {
            $pair = $this->parseOverride($rawOverride);
            if ($pair === null) {
                $output->writeln('<error>Override muss als KEY=VALUE angegeben werden.</error>');
                return null;
            }
            [$key, $value] = $pair;
            $overrides[$key] = $value;
        }

        return $overrides;
    }

    private function parseOverride(mixed $rawOverride): ?array
    {
        if (!is_string($rawOverride)) {
            return null;
        }

        $separator = strpos($rawOverride, '=');
        if ($separator === false) {
            return null;
        }

        $key = trim(substr($rawOverride, 0, $separator));
        if ($key === '') {
            return null;
        }

        $value = substr($rawOverride, $separator + 1);
        return [$key, $value];
    }
}
