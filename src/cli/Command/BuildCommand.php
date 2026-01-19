<?php

namespace App\Cli\Command;

use App\Cli\Cv\CvBuildService;
use EnvPipelineSpec\Env\Env;
use EnvPipelineSpec\Env\EnvCompiler;
use EnvPipelineSpec\Env\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'build', description: 'Erstellt CSS und Lebenslauf-HTML.')]
final class BuildCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('profile', InputArgument::OPTIONAL, 'APP_ENV (optional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->prepareBuildEnv($input);
        $exitCode = $this->runCssBuild($output);
        if ($exitCode !== 0) {
            return $exitCode;
        }

        $compiler = new EnvCompiler($this->rootPath());
        $buildContext = $this->compileBuildEnv($compiler, $input, $output);
        if ($buildContext === null) {
            return Command::FAILURE;
        }
        if (!$this->compileRuntimeEnv($compiler, $buildContext, $input, $output)) {
            return Command::FAILURE;
        }

        if (!$this->runCvBuild($input, $output)) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function prepareBuildEnv(InputInterface $input): void
    {
        $this->applyAppEnvFromArg($input);
        $this->setPhaseEnv('build');
    }

    private function compileBuildEnv(
        EnvCompiler $compiler,
        InputInterface $input,
        OutputInterface $output
    ): ?Context {
        $buildContext = $this->resolveContext($compiler, 'build');
        if (!$this->validateEnv($compiler, $buildContext, $input, $output)) {
            return null;
        }
        return $buildContext;
    }

    private function compileRuntimeEnv(
        EnvCompiler $compiler,
        Context $buildContext,
        InputInterface $input,
        OutputInterface $output
    ): bool {
        $runtimeContext = new Context($buildContext->pipeline(), 'runtime', $buildContext->profile());
        return $this->compileEnv($compiler, $runtimeContext, $input, $output);
    }

    private function runCvBuild(InputInterface $input, OutputInterface $output): bool
    {
        $env = new Env($this->rootPath());
        $builder = new CvBuildService($env);

        try {
            $builder->build($output);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return false;
        }
        return true;
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

    private function compileEnv(
        EnvCompiler $compiler,
        Context $context,
        InputInterface $input,
        OutputInterface $output
    ): bool {
        try {
            $compiler->compile($context, $input->isInteractive());
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return false;
        }
        return true;
    }

    private function runCssBuild(OutputInterface $output): int
    {
        $process = new Process(['npm', 'run', 'build:css'], $this->rootPath());
        $process->run(function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });
        if ($process->isSuccessful()) {
            return Command::SUCCESS;
        }
        $output->writeln('<error>CSS build failed.</error>');
        return $process->getExitCode() ?? Command::FAILURE;
    }
}
