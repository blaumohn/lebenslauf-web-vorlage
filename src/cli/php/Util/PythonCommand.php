<?php

namespace App\Cli\Util;

use App\Cli\Command\BasePipelinePhaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'python', description: 'Fuehrt ein Python-Skript ueber den CLI-Runner aus.')]
final class PythonCommand extends BasePipelinePhaseCommand
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

    protected function runPipelineCommand(InputInterface $input, OutputInterface $output): int
    {
        $script = $this->resolveScript($input, $output);
        if ($script === null) {
            return Command::FAILURE;
        }

        $runner = new PythonRunner($this->rootPath());
        return $runner->runScript(
            $this->commandConfig(),
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
