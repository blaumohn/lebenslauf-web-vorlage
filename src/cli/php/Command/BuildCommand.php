<?php

namespace App\Cli\Command;

use App\Cli\Config\AppConfigValidator;
use App\Cli\Cv\CvBuildService;
use App\Cli\Cv\CvUploadService;
use PipelineConfigSpec\PipelineConfigService;
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
        $this->addArgument('pipeline', InputArgument::REQUIRED, 'Pipeline-Name')
            ->addArgument('task', InputArgument::OPTIONAL, 'Subtask (cv, css, upload, all)')
            ->addArgument('arg1', InputArgument::OPTIONAL, 'CV-Profil (bei upload)')
            ->addArgument('arg2', InputArgument::OPTIONAL, 'JSON-Pfad (bei upload)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }

        $task = strtolower(trim((string) $input->getArgument('task')));
        if ($task === '' || $task === 'all') {
            return $this->runAll($pipeline, $input, $output);
        }
        if ($task === 'css') {
            return $this->runCssOnly($output);
        }
        if ($task === 'cv') {
            return $this->runCvOnly($pipeline, $output);
        }
        if ($task === 'upload') {
            return $this->runCvUpload($pipeline, $input, $output);
        }

        $output->writeln('<error>Usage: build <PIPELINE> [cv|css|upload|all] [ARGS]</error>');
        return Command::FAILURE;
    }

    private function runAll(string $pipeline, InputInterface $input, OutputInterface $output): int
    {
        $exitCode = $this->runCssBuild($output);
        if ($exitCode !== 0) {
            return $exitCode;
        }
        return $this->runCvOnly($pipeline, $output);
    }

    private function runCssOnly(OutputInterface $output): int
    {
        return $this->runCssBuild($output);
    }

    private function runCvOnly(string $pipeline, OutputInterface $output): int
    {
        $pipelineSpec = $this->configService();
        $buildValues = $this->resolveBuildValues($pipelineSpec, $pipeline, $output);
        if ($buildValues === null) {
            return Command::FAILURE;
        }
        if (!$this->compileRuntimeConfig($pipelineSpec, $pipeline, $output)) {
            return Command::FAILURE;
        }
        if (!$this->runCvBuild($pipelineSpec, $buildValues, $output)) {
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    private function runCvUpload(string $pipeline, InputInterface $input, OutputInterface $output): int
    {
        $cvProfile = trim((string) $input->getArgument('arg1'));
        $jsonPath = trim((string) $input->getArgument('arg2'));
        if ($cvProfile === '' || $jsonPath === '') {
            $output->writeln('<error>Usage: build <PIPELINE> upload <CV_PROFIL> <JSON></error>');
            return Command::FAILURE;
        }

        $pipelineSpec = $this->configService();
        $buildValues = $this->resolveBuildValues($pipelineSpec, $pipeline, $output);
        if ($buildValues === null) {
            return Command::FAILURE;
        }
        $config = $this->configValues($buildValues);
        $service = new CvUploadService($config);

        try {
            $service->upload($cvProfile, $jsonPath, $output);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function runCvBuild(
        PipelineConfigService $pipelineSpec,
        array $values,
        OutputInterface $output
    ): bool
    {
        $config = $this->configValues($values);
        $builder = new CvBuildService($config);

        try {
            $builder->build($output);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return false;
        }
        return true;
    }

    private function resolveBuildValues(
        PipelineConfigService $pipelineSpec,
        string $pipeline,
        OutputInterface $output
    ): ?array {
        try {
            return $pipelineSpec->values($pipeline, 'build');
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return null;
        }
    }

    private function compileRuntimeConfig(
        PipelineConfigService $pipelineSpec,
        string $pipeline,
        OutputInterface $output
    ): bool {
        try {
            $runtimeValues = $pipelineSpec->values($pipeline, 'runtime');
            $this->validateAppConfig($runtimeValues);
            $pipelineSpec->compile($pipeline, 'runtime');
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return false;
        }
        return true;
    }

    private function validateAppConfig(array $values): void
    {
        $errors = (new AppConfigValidator())->validate($values);
        if ($errors === []) {
            return;
        }
        throw new \RuntimeException(
            "Config-Validierung fehlgeschlagen:\n- " . implode("\n- ", $errors)
        );
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
