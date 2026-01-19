<?php

namespace App\Cli\Command;

use App\Cli\PythonRunner;
use EnvPipelineSpec\Env\EnvInitializer;
use App\Content\ContentConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'setup', description: 'Richtet die Entwicklungsumgebung ein.')]
final class SetupCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('profile', InputArgument::OPTIONAL, 'APP_ENV (optional)')
            ->addOption('create-data-templates', null, InputOption::VALUE_NONE, 'Demo-Inhalte nach .local kopieren');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->applyAppEnvFromArg($input);
        $this->setPhaseEnv('setup');
        $this->ensureLocalEnv($input);
        if ($input->getOption('create-data-templates')) {
            $this->createDataTemplates();
        }

        $runner = new PythonRunner($this->rootPath());
        if (!$this->ensureVenv($runner, $input, $output)) {
            return Command::FAILURE;
        }

        if (!$this->installNodeDependencies($input, $output)) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function ensureLocalEnv(InputInterface $input): void
    {
        $initializer = new EnvInitializer($this->rootPath(), '.env.template');
        $initializer->ensureLocalEnv($input->isInteractive());
    }

    private function createDataTemplates(): void
    {
        $this->ensureContentIni();
        $this->ensureDemoData();
    }

    private function ensureContentIni(): void
    {
        $target = $this->contentIniPath();
        if (is_file($target)) {
            return;
        }
        $source = $this->contentTemplatePath();
        $this->copyFile($source, $target);
    }

    private function ensureDemoData(): void
    {
        $profile = $this->resolveDefaultProfile();
        $target = $this->demoTargetPath($profile);
        if (is_file($target)) {
            return;
        }
        $source = $this->demoSourcePath();
        $this->copyFile($source, $target);
    }

    private function resolveDefaultProfile(): string
    {
        $config = new ContentConfig($this->rootPath());
        return $config->defaultProfile();
    }

    private function demoSourcePath(): string
    {
        return $this->joinPath('tests', 'fixtures', 'lebenslauf', 'daten-gueltig.yaml');
    }

    private function demoTargetPath(string $profile): string
    {
        return $this->joinPath('.local', 'lebenslauf', 'daten-' . $profile . '.yaml');
    }

    private function contentIniPath(): string
    {
        return $this->joinPath('.local', 'content.ini');
    }

    private function contentTemplatePath(): string
    {
        return $this->joinPath('config', 'content.template.ini');
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

    private function joinPath(string ...$parts): string
    {
        return implode(DIRECTORY_SEPARATOR, array_merge([$this->rootPath()], $parts));
    }

    private function ensureVenv(PythonRunner $runner, InputInterface $input, OutputInterface $output): bool
    {
        if ($runner->createVenv('.venv', $input->isInteractive())) {
            return true;
        }
        $output->writeln('<error>Python 3 fehlt. Bitte installieren.</error>');
        return false;
    }

    private function installNodeDependencies(InputInterface $input, OutputInterface $output): bool
    {
        return $this->runCommand(['npm', 'install'], $output, $input->isInteractive());
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
