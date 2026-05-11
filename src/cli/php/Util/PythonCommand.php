<?php

namespace App\Cli\Util;

use App\Cli\Command\BasePipelineCommand;
use App\Cli\Command\PythonRunnerAware;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'python', description: 'Führt ein Python-Skript mit gezielten Pipeline-Phasen aus.')]
final class PythonCommand extends BasePipelineCommand
{
    use PythonRunnerAware;

    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('pipeline', InputArgument::REQUIRED, 'Pipeline-Name')
            ->addArgument('script', InputArgument::REQUIRED, 'Relativer Pfad zum Skript.')
            ->addArgument('args', InputArgument::IS_ARRAY, 'Argumente für das Skript')
            ->addOption('phases', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Pipeline-Phasen (z. B. --phases runtime --phases build)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $script = $this->resolveScript($input, $output);
        if ($script === null) {
            return Command::FAILURE;
        }

        $phases = $input->getOption('phases');
        if (!is_array($phases) || $phases === []) {
            $output->writeln('<error>--phases fehlt. Beispiel: --phases runtime</error>');
            return Command::FAILURE;
        }

        $values = $this->buildPhaseValues($phases, $output);
        if ($values === null) {
            return Command::FAILURE;
        }

        return $this->pythonRunner()->runScript($script, $values, $this->scriptArgs($input));
    }

    private function resolveScript(InputInterface $input, OutputInterface $output): ?string
    {
        $value = $input->getArgument('script');
        if (!is_string($value) || trim($value) === '') {
            $output->writeln('<error>Script-Pfad fehlt.</error>');
            return null;
        }
        return trim($value);
    }

    private function scriptArgs(InputInterface $input): array
    {
        $args = $input->getArgument('args');
        if (!is_array($args)) {
            return [];
        }
        $args = array_map('strval', $args);
        return array_values(array_filter($args, fn (string $v) => trim($v) !== ''));
    }
}
