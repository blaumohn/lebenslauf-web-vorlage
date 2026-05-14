<?php

namespace App\Cli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'config', description: 'Config-Tools (get, show, lint, compile).')]
final class ConfigCommand extends BasePipelineCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('action', InputArgument::REQUIRED, 'get, show, lint oder compile')
            ->addArgument('arg1', InputArgument::OPTIONAL, 'KEY')
            ->addArgument('arg2', InputArgument::OPTIONAL, 'TARGET (bei compile)')
            ->addOption('phase', null, InputOption::VALUE_REQUIRED, 'Phase in der Pipeline-Phase');
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
            return $this->handleLint($output);
        }
        if ($action === 'compile') {
            return $this->handleCompile($input, $output);
        }
        $output->writeln('<error>Usage: config <PIPELINE> <action> [ARGS]</error>');
        return Command::FAILURE;
    }

    private function handleGet(InputInterface $input, OutputInterface $output): int
    {
        $phase = $this->requirePhase($input, $output);
        if ($phase === null) {
            return Command::FAILURE;
        }
        $key = trim((string) $input->getArgument('arg1'));
        try {
            $values = $this->pipelineValues($phase);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }
        if ($key === '') {
            $output->write(json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }
        $output->write((string) ($values[$key] ?? ''));
        return Command::SUCCESS;
    }

    private function handleShow(InputInterface $input, OutputInterface $output): int
    {
        $phase = $this->requirePhase($input, $output);
        if ($phase === null) {
            return Command::FAILURE;
        }
        try {
            $report = $this->pipelineDescribe($phase);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $contextData = $report['context'] ?? [];
        $pipelineName = (string) ($contextData['pipeline'] ?? '');
        $phaseName = (string) ($contextData['phase'] ?? '');
        $output->writeln('Pipeline-Phase: ' . $this->contextLabel($pipelineName, $phaseName));
        $output->writeln("Pipeline: {$pipelineName}");
        $output->writeln("Phase: {$phaseName}");
        $output->writeln('Config-Dateien:');
        foreach (($report['files'] ?? []) as $file) {
            $output->writeln('- ' . $file);
        }
        $output->writeln('Werte:');
        foreach (($report['values'] ?? []) as $key => $value) {
            $output->writeln($key . '=' . $value);
        }
        return Command::SUCCESS;
    }

    private function handleLint(OutputInterface $output): int
    {
        try {
            $this->pipelineValidate();
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln('Config OK. Pipeline: ' . $this->pipelineName());
        return Command::SUCCESS;
    }

    private function handleCompile(InputInterface $input, OutputInterface $output): int
    {
        $phase = $this->requirePhase($input, $output);
        if ($phase === null) {
            return Command::FAILURE;
        }
        $target = trim((string) $input->getArgument('arg2'));
        $targetPath = $target === '' ? null : $this->resolvePath($target);
        $context = $this->contextLabel($this->pipelineName(), $phase);
        try {
            $path = $this->pipelineCompile($phase, $targetPath);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln("Pipeline-Phase: {$context}");
        $output->writeln("Compiled config written: {$path}");
        return Command::SUCCESS;
    }

    private function requirePhase(InputInterface $input, OutputInterface $output): ?string
    {
        $phase = $this->resolveOptionString($input, 'phase');
        if ($phase !== null) {
            return $phase;
        }
        $output->writeln('<error>--phase fehlt. Beispiel: --phase runtime</error>');
        return null;
    }

    private function resolveOptionString(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function contextLabel(string $pipeline, string $phase): string
    {
        return $pipeline . '/' . $phase;
    }

    private function resolvePath(string $path): string
    {
        if (Path::isAbsolute($path)) {
            return $path;
        }
        return Path::join($this->rootPath(), $path);
    }
}
