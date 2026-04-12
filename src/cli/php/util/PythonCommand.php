<?php

namespace App\Cli\Util;

use App\Cli\Command\BasePipelineCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'python', description: 'Fuehrt ein Python-Skript ueber den CLI-Runner aus.')]
final class PythonCommand extends BasePipelineCommand
{
    protected function commandPhase(): string
    {
        return 'python';
    }

    protected function configurePipelineCommand(): void
    {
        $this->addArgument('script', InputArgument::REQUIRED, 'Relativer Pfad zum Skript.')
            ->addArgument('args', InputArgument::IS_ARRAY, 'Argumente fuer das Skript');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }
        $overrides = $this->requireOverrides($input, $output);
        if ($overrides === null) {
            return Command::FAILURE;
        }
        $script = $this->resolveScript($input, $output);
        if ($script === null) {
            return Command::FAILURE;
        }
        $config = $this->resolvePipelineConfig($pipeline, $this->commandPhase(), $overrides, $output);
        if ($config === null) {
            return Command::FAILURE;
        }

        $runner = new PythonRunner($this->rootPath());
        return $runner->runScript(
            $config,
            $script,
            $this->scriptArgs($input),
            $input->isInteractive()
        );
    }

    private function resolveScript(InputInterface $input, OutputInterface $output): ?string
    {
        $value = $input->getArgument('script');
        if (!is_string($value)) {
            $output->writeln('<error>Script-Pfad fehlt.</error>');
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            $output->writeln('<error>Script-Pfad fehlt.</error>');
            return null;
        }
        return $value;
    }

    private function scriptArgs(InputInterface $input): array
    {
        $args = $input->getArgument('args');
        if (!is_array($args)) {
            return [];
        }
        $args = array_map('strval', $args);
        $filtered = array_filter($args, [$this, 'isNonEmptyString']);
        return array_values($filtered);
    }

    private function isNonEmptyString(string $value): bool
    {
        return trim($value) !== '';
    }
}
