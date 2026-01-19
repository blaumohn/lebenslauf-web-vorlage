<?php

namespace App\Cli\Command;

use App\Cli\PythonRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'run', description: 'Startet den lokalen Dev-Server.')]
final class RunCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('profile', InputArgument::OPTIONAL, 'APP_ENV (optional)')
            ->addOption('build', null, InputOption::VALUE_NONE, 'Vor dem Start cv build ausführen')
            ->addOption('demo', null, InputOption::VALUE_NONE, 'Demo-Daten aus den Fixtures verwenden')
            ->addOption('mail-stdout', null, InputOption::VALUE_NONE, 'Mail-Ausgabe nach STDOUT');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->applyAppEnvFromArg($input);
        $runner = new PythonRunner($this->rootPath());
        $args = $this->devArgs($input);
        return $runner->run('src/cli/tools/dev.py', $args, $input->isInteractive());
    }

    private function devArgs(InputInterface $input): array
    {
        $args = [];
        if ($input->getOption('build')) {
            $args[] = '--build';
        }
        if ($input->getOption('demo')) {
            $args[] = '--demo';
        }
        if ($input->getOption('mail-stdout')) {
            $args[] = '--mail-stdout';
        }
        return $args;
    }
}
