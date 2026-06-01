<?php

namespace App\Cli\Command;

use App\Cli\Token\LocalTokenRotation;
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

        if (!$this->pipelineHasPhase('deploy')) {
            return $this->rotateLocally($input, $output);
        }

        $values = $this->getValuesByPhase(['deploy'], $output);
        if ($values === null) {
            return Command::FAILURE;
        }

        return $this->pythonRunner()->runScript(
            'src/cli/py/task/dispatch.py',
            $values,
            $this->buildRotateArgs($input)
        );
    }

    private function rotateLocally(InputInterface $input, OutputInterface $output): int
    {
        $profile = trim((string) $input->getArgument('profile'));
        if ($profile === '') {
            $output->writeln('<error>Profil ist erforderlich für lokale Rotation.</error>');
            return Command::FAILURE;
        }
        $count = max(1, (int) $input->getArgument('count'));
        try {
            $tokens = (new LocalTokenRotation())->rotate($this->appRoot(), $profile, $count);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        foreach ($tokens as $token) {
            $output->writeln($token);
        }
        return Command::SUCCESS;
    }

    private function buildRotateArgs(InputInterface $input): array
    {
        $args = ['cv_token_rotation'];
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
