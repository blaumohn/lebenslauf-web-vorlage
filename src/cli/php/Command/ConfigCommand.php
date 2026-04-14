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
            ->addOption('phase', null, InputOption::VALUE_REQUIRED, 'Phase-Name')
            ->setHelp(<<<'HELP'
Aktionen:

  get      Liest einen einzelnen Konfigurationsschlüssel aus.
             Pflicht: <arg1> = KEY
             Beispiel: config get myapp APP_BASE_PATH
             Beispiel: config get myapp APP_BASE_PATH --phase build

  show     Zeigt alle aufgelösten Werte der Pipeline/Phase an.
             Keine weiteren Argumente.
             Beispiel: config show myapp
             Beispiel: config show myapp --phase build

  lint     Prüft die Konfiguration auf Gültigkeit (Schema + Pflichtfelder).
             Keine weiteren Argumente.
             Beispiel: config lint myapp

  compile  Schreibt die aufgelöste Konfiguration als Datei heraus.
             Optional: <arg2> = TARGET (Pfad relativ zum Projekt-Root)
             Beispiel: config compile myapp
             Beispiel: config compile myapp --phase build output/.env

Standard-Phase wenn --phase fehlt: runtime
HELP);
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

        $output->writeln("<error>Unbekannte Aktion: {$action}. Gültige Werte: get, show, lint, compile</error>");
        $output->writeln('<comment>Hilfe: bin/cli config --help</comment>');
        return Command::FAILURE;
    }

    private function handleGet(InputInterface $input, OutputInterface $output): int
    {
        $key = trim((string) $input->getArgument('arg1'));
        if ($key === '') {
            $output->writeln('<error>Usage: config get <PIPELINE> <KEY></error>');
            return Command::FAILURE;
        }

        $pipelineSpec = $this->configService();
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }
        $phase = $this->resolvePhase($input, 'runtime');
        try {
            $values = $pipelineSpec->values($pipeline, $phase);
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
        $pipelineSpec = $this->configService();
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }
        $phase = $this->resolvePhase($input, 'runtime');
        try {
            $report = $pipelineSpec->describe($pipeline, $phase);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $contextData = $report['context'] ?? [];
        $pipelineName = (string) ($contextData['pipeline'] ?? '');
        $phaseName = (string) ($contextData['phase'] ?? '');
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
        $pipelineSpec = $this->configService();
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }
        $phase = $this->resolvePhase($input, 'runtime');
        try {
            $pipelineSpec->validate($pipeline, $phase);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln('Config OK.');
        return Command::SUCCESS;
    }

    private function handleCompile(InputInterface $input, OutputInterface $output): int
    {
        $target = trim((string) $input->getArgument('arg2'));
        $targetPath = $target === '' ? null : $this->resolvePath($target);

        $pipelineSpec = $this->configService();
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }
        $phase = $this->resolvePhase($input, 'runtime');
        try {
            $path = $pipelineSpec->compile($pipeline, $phase, $targetPath);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

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

    private function resolvePhase(InputInterface $input, string $fallback): string
    {
        $requested = $this->resolveOptionString($input, 'phase');
        return $requested ?? $fallback;
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
