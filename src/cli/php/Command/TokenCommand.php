<?php

namespace App\Cli\Command;

use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(name: 'token', description: 'Token-Tools (rotate).')]
final class TokenCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'rotate')
            ->addArgument('profile', InputArgument::OPTIONAL, 'Token-Profil')
            ->addArgument('count', InputArgument::OPTIONAL, 'Anzahl Token', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = strtolower(trim((string) $input->getArgument('action')));
        if ($action !== 'rotate') {
            $output->writeln('<error>Usage: token rotate <PROFIL> [COUNT]</error>');
            return Command::FAILURE;
        }

        $profile = trim((string) $input->getArgument('profile'));
        if ($profile === '') {
            $output->writeln('<error>Usage: token rotate <PROFIL> [COUNT]</error>');
            return Command::FAILURE;
        }

        $count = max(1, (int) $input->getArgument('count'));
        $service = $this->buildTokenService();
        $tokens = $service->generateTokens($count);
        $service->rotate($profile, $tokens);

        $output->writeln("New tokens for {$profile}:");
        foreach ($tokens as $token) {
            $output->writeln($token);
        }

        return Command::SUCCESS;
    }

    private function buildTokenService(): TokenService
    {
        $rootPath = $this->rootPath();
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner(Path::join($rootPath, 'var', 'state', 'locks'));
        $writer = new RuntimeAtomicWriter();
        return new TokenService($storage, $lockRunner, $writer, Path::join($rootPath, 'var', 'state', 'tokens'));
    }
}
