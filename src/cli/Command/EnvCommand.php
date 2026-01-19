<?php

namespace App\Cli\Command;

use EnvPipelineSpec\Env\EnvCompiler;
use EnvPipelineSpec\Env\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'env', description: 'ENV-Tools (get, show, lint, compile).')]
final class EnvCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'get, show, lint oder compile')
            ->addArgument('arg1', InputArgument::OPTIONAL, 'KEY')
            ->addArgument('arg2', InputArgument::OPTIONAL, 'TARGET (bei compile)')
            ->addOption('app-env', null, InputOption::VALUE_REQUIRED, 'APP_ENV für die Ausführung setzen')
            ->addOption('pipeline', null, InputOption::VALUE_REQUIRED, 'Pipeline-Name')
            ->addOption('phase', null, InputOption::VALUE_REQUIRED, 'Phase-Name')
            ->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Profil');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = strtolower(trim((string) $input->getArgument('action')));
        if ($action === 'get') {
            return $this->handleGet($input, $output);
        }
        if ($action === 'show') {
            return $this->handleShow($input, $output);
        }
        if ($action === 'lint') {
            return $this->handleLint($input, $output);
        }
        if ($action === 'compile') {
            return $this->handleCompile($input, $output);
        }

        $output->writeln('<error>Usage: env get <KEY> | env show | env lint | env compile [TARGET]</error>');
        return Command::FAILURE;
    }

    private function handleGet(InputInterface $input, OutputInterface $output): int
    {
        $key = trim((string) $input->getArgument('arg1'));
        if ($key === '') {
            $output->writeln('<error>Usage: env get <KEY></error>');
            return Command::FAILURE;
        }

        $compiler = new EnvCompiler($this->rootPath());
        $context = $this->resolveContext($compiler, $input, 'runtime');
        try {
            $snapshot = $compiler->validate($context, $input->isInteractive());
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $value = (string) ($snapshot->values()[$key] ?? '');
        $output->write($value);
        return Command::SUCCESS;
    }

    private function handleShow(InputInterface $input, OutputInterface $output): int
    {
        $compiler = new EnvCompiler($this->rootPath());
        $context = $this->resolveContext($compiler, $input, 'runtime');
        try {
            $snapshot = $compiler->validate($context, $input->isInteractive());
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln("Pipeline: {$context->pipeline()}");
        $output->writeln("Phase: {$context->phase()}");
        $output->writeln("Profile: " . ($context->profile() ?? '-'));
        $output->writeln('Env-Dateien:');
        foreach ($snapshot->loadedFiles() as $file) {
            $output->writeln('- ' . $file);
        }
        $output->writeln('Werte:');
        foreach ($snapshot->values() as $key => $value) {
            $output->writeln($key . '=' . $value);
        }
        return Command::SUCCESS;
    }

    private function handleLint(InputInterface $input, OutputInterface $output): int
    {
        $compiler = new EnvCompiler($this->rootPath());
        $context = $this->resolveContext($compiler, $input, 'runtime');
        try {
            $compiler->validate($context, $input->isInteractive());
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln('Env OK.');
        return Command::SUCCESS;
    }

    private function handleCompile(InputInterface $input, OutputInterface $output): int
    {
        $target = trim((string) $input->getArgument('arg2'));
        $targetPath = $target === '' ? null : $this->resolvePath($target);

        $compiler = new EnvCompiler($this->rootPath());
        try {
            $context = $this->resolveContext($compiler, $input, 'runtime');
            $path = $compiler->compile($context, $input->isInteractive(), $targetPath);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln("Compiled env written: {$path}");
        return Command::SUCCESS;
    }

    private function resolveContext(EnvCompiler $compiler, InputInterface $input, string $phase): Context
    {
        $appEnv = trim((string) $input->getOption('app-env'));
        if ($appEnv !== '') {
            $this->setAppEnv($appEnv);
        }

        return $compiler->resolveContext(
            [
                'pipeline' => 'dev',
                'phase' => $phase,
                'profile' => null,
            ],
            [
                'pipeline' => $input->getOption('pipeline'),
                'phase' => $input->getOption('phase'),
                'profile' => $input->getOption('profile'),
            ]
        );
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return $path;
        }
        if ($path[0] === DIRECTORY_SEPARATOR) {
            return $path;
        }
        if ((bool) preg_match('/^[A-Za-z]:\\\\/', $path)) {
            return $path;
        }
        return $this->rootPath() . DIRECTORY_SEPARATOR . $path;
    }
}
