<?php

namespace App\Cli\Command;

use App\Http\Security\IpSaltService;
use App\Http\Security\RuntimeAtomicWriter;
use App\Http\Security\RuntimeLockRunner;
use App\Http\Storage\FileStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(name: 'ip-hash', description: 'IP-Hash-Tools (reset).')]
final class IpHashCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::OPTIONAL, 'reset', 'reset');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = strtolower(trim((string) $input->getArgument('action')));
        if ($action !== 'reset') {
            $output->writeln('<error>Usage: ip-hash [reset]</error>');
            return Command::FAILURE;
        }
        $service = $this->buildService();
        try {
            $service->resetSalt();
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln('IP-Hash-Salt rotiert und IP-State bereinigt.');
        return Command::SUCCESS;
    }

    private function buildService(): IpSaltService
    {
        $rootPath = $this->rootPath();
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner(Path::join($rootPath, 'var', 'state', 'locks'));
        $writer = new RuntimeAtomicWriter();
        return new IpSaltService(
            $storage,
            $lockRunner,
            $writer,
            Path::join($rootPath, 'var', 'state'),
            Path::join($rootPath, 'var', 'tmp', 'captcha'),
            Path::join($rootPath, 'var', 'tmp', 'ratelimit')
        );
    }
}
