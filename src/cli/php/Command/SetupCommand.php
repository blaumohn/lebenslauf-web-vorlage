<?php

namespace App\Cli\Command;

use App\Cli\PythonResolver;
use PipelineConfigSpec\PipelineConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'setup', description: 'Richtet die Entwicklungsumgebung ein.')]
final class SetupCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('pipeline', InputArgument::REQUIRED, 'Pipeline-Name')
            ->addOption('reset-sample-content', null, InputOption::VALUE_NONE, 'Sample-Inhalte nach .local kopieren')
            ->addOption('skip-python', null, InputOption::VALUE_NONE, 'Python-Setup ueberspringen')
            ->addOption('python-cache-dir', null, InputOption::VALUE_REQUIRED, 'Cache-Verzeichnis fuer Pip')
            ->addOption('npm-cache-dir', null, InputOption::VALUE_REQUIRED, 'Cache-Verzeichnis fuer NPM');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }
        $configValues = $this->resolveSetupConfigValues($pipeline, $output);
        if ($input->getOption('reset-sample-content')) {
            $profile = $this->resolveDefaultProfile($configValues);
            if (!$this->resetSampleContent($profile)) {
                return Command::FAILURE;
            }
        }
        if (!$input->getOption('skip-python')) {
            $resolver = new PythonResolver($this->rootPath(), $configValues);
            if (!$this->ensureVenv($resolver, $input, $output)) {
                return Command::FAILURE;
            }
            if (!$this->installPythonDeps($input, $output)) {
                return Command::FAILURE;
            }
        }
        if (!$this->installNodeDependencies($input, $output)) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
    private function resetSampleContent(string $profile): bool
    {
        $target = $this->demoTargetPath($profile);
        $source = $this->demoSourcePath();
        $this->copyFile($source, $target);
        return true;
    }

    private function resolveDefaultProfile(array $configValues): string
    {
        $value = trim((string) ($configValues['LEBENSLAUF_PUBLIC_PROFILE'] ?? ''));
        return $value !== '' ? $value : 'default';
    }

    private function demoSourcePath(): string
    {
        return Path::join(
            $this->rootPath(),
            'src',
            'resources',
            'fixtures',
            'lebenslauf',
            'daten-gueltig.yaml'
        );
    }

    private function demoTargetPath(string $profile): string
    {
        $filename = 'daten-' . $profile . '.yaml';
        return Path::join($this->rootPath(), '.local', 'lebenslauf', $filename);
    }

    private function copyFile(string $source, string $target): void
    {
        if (!is_file($source)) {
            throw new \RuntimeException("Datei fehlt: {$source}");
        }
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        copy($source, $target);
    }

    private function ensureVenv(PythonResolver $resolver, InputInterface $input, OutputInterface $output): bool
    {
        if ($resolver->createVenv('.venv', $input->isInteractive())) {
            return true;
        }
        $output->writeln('<error>Python 3 fehlt. Bitte installieren.</error>');
        return false;
    }

    private function resolveSetupConfigValues(
        string $pipeline,
        OutputInterface $output
    ): array {
        $pipelineSpec = $this->configService();
        return $this->loadConfigValues($pipelineSpec, $pipeline, $output);
    }

    private function loadConfigValues(
        PipelineConfigService $pipelineSpec,
        string $pipeline,
        OutputInterface $output
    ): array {
        try {
            return $pipelineSpec->values($pipeline, 'setup');
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return [];
        }
    }

    private function installPythonDeps(InputInterface $input, OutputInterface $output): bool
    {
        $requirements = $this->requirementsPath();
        if (!is_file($requirements)) {
            return true;
        }
        $python = $this->venvPythonPath();
        if ($python === null) {
            $output->writeln('<error>Python-Venv fehlt. Bitte setup erneut ausfuehren.</error>');
            return false;
        }
        $command = [$python, '-m', 'pip', 'install', '-r', $requirements];
        $cacheDir = $this->pythonCacheDir($input);
        if ($cacheDir !== null) {
            $command[] = '--cache-dir';
            $command[] = $cacheDir;
        }
        return $this->runCommand($command, $output, $input->isInteractive());
    }

    private function requirementsPath(): string
    {
        return Path::join($this->rootPath(), 'requirements.txt');
    }

    private function venvPythonPath(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = [
                Path::join($this->rootPath(), '.venv', 'Scripts', 'python.exe'),
                Path::join($this->rootPath(), '.venv', 'Scripts', 'python3.exe'),
            ];
        } else {
            $candidates = [
                Path::join($this->rootPath(), '.venv', 'bin', 'python3'),
                Path::join($this->rootPath(), '.venv', 'bin', 'python'),
            ];
        }
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function installNodeDependencies(InputInterface $input, OutputInterface $output): bool
    {
        $command = ['npm', 'install'];
        $cacheDir = $this->npmCacheDir($input);
        if ($cacheDir !== null) {
            $command[] = '--cache';
            $command[] = $cacheDir;
        }
        return $this->runCommand($command, $output, $input->isInteractive());
    }

    private function pythonCacheDir(InputInterface $input): ?string
    {
        $value = trim((string) $input->getOption('python-cache-dir'));
        return $value !== '' ? $value : null;
    }

    private function npmCacheDir(InputInterface $input): ?string
    {
        $value = trim((string) $input->getOption('npm-cache-dir'));
        return $value !== '' ? $value : null;
    }

    private function runCommand(array $command, OutputInterface $output, bool $interactive): bool
    {
        $process = new Process($command, $this->rootPath());
        if ($interactive && Process::isTtySupported()) {
            $process->setTty(true);
        }
        $process->run(function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });
        if (!$process->isSuccessful()) {
            $output->write($process->getErrorOutput());
        }
        return $process->isSuccessful();
    }
}
