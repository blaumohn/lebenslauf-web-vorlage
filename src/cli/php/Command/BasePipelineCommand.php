<?php

namespace App\Cli\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BasePipelineCommand extends BaseCommand
{
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

    protected function configurePipelineCommand(): void
    {
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
