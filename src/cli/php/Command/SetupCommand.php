<?php

namespace App\Cli\Command;

use App\Cli\PythonResolver;
use App\Cli\Setup\SampleContentCopier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'setup', description: 'Richtet die Entwicklungsumgebung ein.')]
final class SetupCommand extends BasePipelinePhaseCommand
{
    private const ACTION_SAMPLE_CONTENT = 'sample-content';

    protected function commandPhase(): string
    {
        return 'setup';
    }

    protected function configurePipelineCommand(): void
    {
        $this->addArgument('action', InputArgument::OPTIONAL, 'Einzelne Setup-Aktion, z. B. sample-content')
            ->addOption('with-sample-content', null, InputOption::VALUE_NONE, 'Sample-Inhalt zusätzlich nach .local kopieren')
            ->addOption('skip-python', null, InputOption::VALUE_NONE, 'Python-Setup ueberspringen')
            ->addOption('python-cache-dir', null, InputOption::VALUE_REQUIRED, 'Cache-Verzeichnis fuer Pip')
            ->addOption('npm-cache-dir', null, InputOption::VALUE_REQUIRED, 'Cache-Verzeichnis fuer NPM');
    }

    protected function runPipelineCommand(InputInterface $input, OutputInterface $output): int
    {
        $configValues = $this->commandConfig()->all();
        $action = (string) $input->getArgument('action');

        if ($action === self::ACTION_SAMPLE_CONTENT) {
            return $this->copySampleContent($configValues, $output)
                ? Command::SUCCESS
                : Command::FAILURE;
        }
        if ($input->getOption('with-sample-content')) {
            if (!$this->copySampleContent($configValues, $output)) {
                return Command::FAILURE;
            }
        }
        if (!$this->runSetupSteps($input, $output)) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function runSetupSteps(InputInterface $input, OutputInterface $output): bool
    {
        $configValues = $this->commandConfig()->all();
        if (!$input->getOption('skip-python')) {
            $resolver = new PythonResolver($this->rootPath(), $configValues);
            if (!$this->ensureVenv($resolver, $input, $output)) {
                return false;
            }
            if (!$this->installPythonDeps($input, $output)) {
                return false;
            }
        }
        return $this->installNodeDependencies($input, $output);
    }

    private function copySampleContent(array $configValues, OutputInterface $output): bool
    {
        try {
            $profile = $this->requirePublicProfile($configValues);
            $target = $this->sampleContentCopier()->copy($profile);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return false;
        }
        $output->writeln('<info>Sample-Inhalt kopiert: ' . $target . '</info>');
        return true;
    }

    private function requirePublicProfile(array $configValues): string
    {
        $profile = trim((string) ($configValues['LEBENSLAUF_PUBLIC_PROFILE'] ?? ''));
        if ($profile === '') {
            throw new \RuntimeException('LEBENSLAUF_PUBLIC_PROFILE fehlt fuer den Sample-Inhalt.');
        }
        return $profile;
    }

    private function ensureVenv(PythonResolver $resolver, InputInterface $input, OutputInterface $output): bool
    {
        if ($resolver->createVenv('.venv', $input->isInteractive())) {
            return true;
        }
        $output->writeln('<error>Python 3 fehlt. Bitte installieren.</error>');
        return false;
    }

    private function sampleContentCopier(): SampleContentCopier
    {
        return new SampleContentCopier($this->rootPath());
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
