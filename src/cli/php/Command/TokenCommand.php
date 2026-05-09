<?php

namespace App\Cli\Command;

use App\Http\Cv\CvStorage;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\TokenRotationService;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(name: 'token', description: 'Token-Tools (rotate).')]
final class TokenCommand extends Command
{
    use RootPathAware;
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
        $count = max(1, (int) $input->getArgument('count'));

        try {
            $tokens = $this->buildHandler()->rotate($profile, $count);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $this->printTokens($output, $profile, $tokens);
        return Command::SUCCESS;
    }

    private function printTokens(OutputInterface $output, string $profile, array $tokens): void
    {
        $output->writeln("Neue Token für {$profile}:");
        foreach ($tokens as $token) {
            $output->writeln($token);
        }
    }

    private function buildHandler(): TokenRotationService
    {
        $root = $this->rootPath();
        $storage = new FileStorage();
        $cvStorage = new CvStorage($storage, Path::join($root, 'var', 'cache', 'html'));
        $lockRunner = new RuntimeLockRunner(Path::join($root, 'var', 'state', 'locks'));
        $writer = new RuntimeAtomicWriter();
        $tokenService = new TokenService($storage, $lockRunner, $writer, Path::join($root, 'var', 'state', 'tokens'));
        return new TokenRotationService($cvStorage, $tokenService);
    }
}
