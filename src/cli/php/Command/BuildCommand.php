<?php

namespace App\Cli\Command;

use App\Cli\Site\SiteBuildService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'build', description: 'Erstellt CSS und Site-HTML.')]
final class BuildCommand extends BasePipelinePhaseCommand
{
    protected function commandPhase(): string
    {
        return 'build';
    }

    protected function configurePipelineCommand(): void
    {
        $this->addArgument('task', InputArgument::OPTIONAL, 'Subtask (config, site, css, all)');
    }

    protected function runPipelineCommand(InputInterface $input, OutputInterface $output): int
    {
        $task = strtolower(trim((string) $input->getArgument('task')));
        if ($task === '' || $task === 'all') {
            return $this->runAll($output);
        }
        if ($task === 'config') {
            return $this->runConfigOnly($output);
        }
        if ($task === 'css') {
            return $this->runCssBuild($output);
        }
        if ($task === 'site') {
            return $this->runSiteOnly($output);
        }

        $output->writeln('<error>Usage: build <PIPELINE> [config|site|css|all]</error>');
        return Command::FAILURE;
    }

    private function runAll(OutputInterface $output): int
    {
        $exitCode = $this->runCssBuild($output);
        if ($exitCode !== 0) {
            return $exitCode;
        }
        return $this->runSiteOnly($output);
    }

    private function runSiteOnly(OutputInterface $output): int
    {
        if (!$this->compileRuntimeConfig($output)) {
            return Command::FAILURE;
        }
        $service = SiteBuildService::create($this->commandConfig(), $this->appRoot());
        try {
            $service->build($output);
        } catch (\RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    private function runConfigOnly(OutputInterface $output): int
    {
        return $this->compileRuntimeConfig($output)
            ? Command::SUCCESS
            : Command::FAILURE;
    }

    private function compileRuntimeConfig(OutputInterface $output): bool
    {
        try {
            $this->pipelineCompile('runtime');
        } catch (\RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return false;
        }
        return true;
    }

    private function runCssBuild(OutputInterface $output): int
    {
        $process = new Process(['npm', 'run', 'build:css'], $this->appRoot());
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
