<?php

namespace App\Cli\Command;

use PipelineConfigSpec\PipelineConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'config', description: 'Config-Tools (get, show, lint, compile).')]
final class ConfigCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'get, show, lint oder compile')
            ->addArgument('pipeline', InputArgument::REQUIRED, 'Pipeline-Name')
            ->addArgument('arg1', InputArgument::OPTIONAL, 'KEY')
            ->addArgument('arg2', InputArgument::OPTIONAL, 'TARGET (bei compile)')
            ->addOption('phase', null, InputOption::VALUE_REQUIRED, 'Phase in der Pipeline-Phase')
            ->addOption('overrides', null, InputOption::VALUE_OPTIONAL, 'Config-Überschreibungen als JSON');
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

        $output->writeln('<error>Usage: config <action> <PIPELINE> [ARGS]</error>');
        return Command::FAILURE;
    }

    private function handleGet(InputInterface $input, OutputInterface $output): int
    {
        $key = trim((string) $input->getArgument('arg1'));
        if ($key === '') {
            $output->writeln('<error>Usage: config get <PIPELINE> <KEY></error>');
            return Command::FAILURE;
        }

        $overrides = $this->parseOverrides($input, $output);
        if ($overrides === null) {
            return Command::FAILURE;
        }

        $pipelineSpec = $this->configService();
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }
        $phase = $this->requirePhase($input, $output);
        if ($phase === null) {
            return Command::FAILURE;
        }
        try {
            $values = $pipelineSpec->values($pipeline, $phase, $overrides);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $value = (string) ($values[$key] ?? '');
        $output->write($value);
        return Command::SUCCESS;
    }

    private function handleShow(InputInterface $input, OutputInterface $output): int
    {
        $overrides = $this->parseOverrides($input, $output);
        if ($overrides === null) {
            return Command::FAILURE;
        }

        $pipelineSpec = $this->configService();
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }
        $phase = $this->requirePhase($input, $output);
        if ($phase === null) {
            return Command::FAILURE;
        }
        try {
            $report = $pipelineSpec->describe($pipeline, $phase, $overrides);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $contextData = $report['context'] ?? [];
        $pipelineName = (string) ($contextData['pipeline'] ?? '');
        $phaseName = (string) ($contextData['phase'] ?? '');
        $context = $this->contextLabel($pipelineName, $phaseName);
        $output->writeln("Pipeline-Phase: {$context}");
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

    private function handleLint(InputInterface $input, OutputInterface $output): int
    {
        $overrides = $this->parseOverrides($input, $output);
        if ($overrides === null) {
            return Command::FAILURE;
        }

        $pipelineSpec = $this->configService();
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }

        $requestedPhase = $this->resolveOptionString($input, 'phase');
        if ($requestedPhase !== null) {
            return $this->lintPhase($pipelineSpec, $pipeline, $requestedPhase, $output, $overrides);
        }

        return $this->lintAllPhases($pipelineSpec, $pipeline, $output, $overrides);
    }

    private function lintAllPhases(
        PipelineConfigService $pipelineSpec,
        string $pipeline,
        OutputInterface $output,
        array $overrides = []
    ): int {
        $phases = $this->defaultLintPhases();
        foreach ($phases as $phase) {
            $result = $this->lintPhase($pipelineSpec, $pipeline, $phase, $output, $overrides);
            if ($result !== Command::SUCCESS) {
                return $result;
            }
        }
        return Command::SUCCESS;
    }

    private function lintPhase(
        PipelineConfigService $pipelineSpec,
        string $pipeline,
        string $phase,
        OutputInterface $output,
        array $overrides = []
    ): int {
        $context = $this->contextLabel($pipeline, $phase);
        try {
            $values = $pipelineSpec->values($pipeline, $phase, $overrides);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln("Config OK. Pipeline-Phase: {$context}");
        return Command::SUCCESS;
    }

    private function defaultLintPhases(): array
    {
        return ['setup', 'build', 'runtime', 'deploy'];
    }

    private function handleCompile(InputInterface $input, OutputInterface $output): int
    {
        $overrides = $this->parseOverrides($input, $output);
        if ($overrides === null) {
            return Command::FAILURE;
        }

        $target = trim((string) $input->getArgument('arg2'));
        $targetPath = $target === '' ? null : $this->resolvePath($target);

        $pipelineSpec = $this->configService();
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }
        $phase = $this->requirePhase($input, $output);
        if ($phase === null) {
            return Command::FAILURE;
        }
        $context = $this->contextLabel($pipeline, $phase);
        try {
            $values = $pipelineSpec->values($pipeline, $phase, $overrides);
            $path = $pipelineSpec->compile($pipeline, $phase, $targetPath, $overrides);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln("Pipeline-Phase: {$context}");
        $output->writeln("Compiled config written: {$path}");
        return Command::SUCCESS;
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

    private function requirePhase(InputInterface $input, OutputInterface $output): ?string
    {
        $phase = $this->resolveOptionString($input, 'phase');
        if ($phase !== null) {
            return $phase;
        }
        $output->writeln('<error>--phase fehlt. Beispiel: --phase runtime</error>');
        return null;
    }

    private function contextLabel(string $pipeline, string $phase): string
    {
        $context = $pipeline . '/' . $phase;
        return $context;
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
