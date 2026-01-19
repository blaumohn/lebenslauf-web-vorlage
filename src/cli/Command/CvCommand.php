<?php

namespace App\Cli\Command;

use App\Cli\Cv\CvBuildService;
use App\Cli\Cv\CvUploadService;
use EnvPipelineSpec\Env\EnvCompiler;
use EnvPipelineSpec\Env\Context;
use EnvPipelineSpec\Env\Env;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cv', description: 'CV-Tools (build, upload).')]
final class CvCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'build oder upload')
            ->addArgument('profile', InputArgument::OPTIONAL, 'Profilname (Upload) oder APP_ENV (Build)')
            ->addArgument('json', InputArgument::OPTIONAL, 'JSON-Pfad (bei upload)')
            ->addOption('app-env', null, InputOption::VALUE_REQUIRED, 'APP_ENV für die Ausführung setzen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = strtolower(trim((string) $input->getArgument('action')));
        if ($action === 'build') {
            return $this->handleBuild($input, $output);
        }
        if ($action === 'upload') {
            return $this->handleUpload($input, $output);
        }

        $output->writeln('<error>Usage: cv build [APP_ENV] | cv upload <PROFIL> <JSON></error>');
        return Command::FAILURE;
    }

    private function handleBuild(InputInterface $input, OutputInterface $output): int
    {
        $this->applyAppEnvFromArg($input);
        $this->setPhaseEnv('build');
        $compiler = new EnvCompiler($this->rootPath());
        $context = $this->resolveContext($compiler, 'build');
        if (!$this->validateEnv($compiler, $context, $input, $output)) {
            return Command::FAILURE;
        }
        $env = new Env($this->rootPath());
        $builder = new CvBuildService($env);

        try {
            $builder->build($output);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function handleUpload(InputInterface $input, OutputInterface $output): int
    {
        $appEnv = trim((string) $input->getOption('app-env'));
        if ($appEnv !== '') {
            $this->setAppEnv($appEnv);
        }

        $profile = trim((string) $input->getArgument('profile'));
        $jsonPath = trim((string) $input->getArgument('json'));
        if ($profile === '' || $jsonPath === '') {
            $output->writeln('<error>Usage: cv upload <PROFIL> <JSON></error>');
            return Command::FAILURE;
        }

        $this->setPhaseEnv('build');
        $compiler = new EnvCompiler($this->rootPath());
        $context = $this->resolveContext($compiler, 'build');
        if (!$this->validateEnv($compiler, $context, $input, $output)) {
            return Command::FAILURE;
        }
        $env = new Env($this->rootPath());
        $service = new CvUploadService($env);

        try {
            $service->upload($profile, $jsonPath, $output);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function resolveContext(EnvCompiler $compiler, string $phase): Context
    {
        return $compiler->resolveContext([
            'pipeline' => 'dev',
            'phase' => $phase,
            'profile' => null,
        ]);
    }

    private function validateEnv(
        EnvCompiler $compiler,
        Context $context,
        InputInterface $input,
        OutputInterface $output
    ): bool {
        try {
            $compiler->validate($context, $input->isInteractive());
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return false;
        }
        return true;
    }
}
