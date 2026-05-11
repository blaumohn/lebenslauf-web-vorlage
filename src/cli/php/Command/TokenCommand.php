<?php

namespace App\Cli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'token', description: 'Token-Tools (rotate).')]
final class TokenCommand extends BasePipelineCommand
{
    use PythonRunnerAware;

    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('action', InputArgument::REQUIRED, 'Aktion (rotate)')
            ->addArgument('profile', InputArgument::OPTIONAL, 'Token-Profil')
            ->addArgument('count', InputArgument::OPTIONAL, 'Anzahl Token', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = strtolower(trim((string) $input->getArgument('action')));
        if ($action !== 'rotate') {
            $output->writeln('<error>Usage: token <pipeline> rotate [PROFIL] [COUNT]</error>');
            return Command::FAILURE;
        }

        $phases = $this->pipelineHasPhase('deploy') ? ['deploy'] : [];
        $values = $phases !== [] ? $this->buildPhaseValues($phases, $output) : [];
        if ($values === null) {
            return Command::FAILURE;
        }

        return $this->pythonRunner()->runScript(
            'src/cli/py/admin/dispatch.py',
            $values,
            $this->buildRotateArgs($input)
        );
    }

    private function buildRotateArgs(InputInterface $input): array
    {
        $args = [];
        $profile = trim((string) $input->getArgument('profile'));
        if ($profile !== '') {
            $args[] = '--profile';
            $args[] = $profile;
        }
        $count = max(1, (int) $input->getArgument('count'));
        if ($count > 1) {
            $args[] = '--count';
            $args[] = (string) $count;
        }
        return $args;
    }
}
