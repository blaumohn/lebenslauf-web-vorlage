<?php

namespace App\Cli\Command;

use App\Cli\ConfigValues;
use PipelineConfigSpec\PipelineConfigService;
use App\Http\Captcha\CaptchaService;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Storage\FileStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(name: 'captcha', description: 'CAPTCHA-Tools (cleanup).')]
final class CaptchaCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('pipeline', InputArgument::REQUIRED, 'Pipeline-Name')
            ->addArgument('action', InputArgument::OPTIONAL, 'cleanup', 'cleanup');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = strtolower(trim((string) $input->getArgument('action')));
        if ($action !== 'cleanup') {
            $output->writeln('<error>Usage: captcha <PIPELINE> [cleanup]</error>');
            return Command::FAILURE;
        }

        $pipeline = $this->requirePipeline($input, $output);
        if ($pipeline === null) {
            return Command::FAILURE;
        }
        $pipelineSpec = $this->configService();
        $values = $this->resolveValues($pipelineSpec, $pipeline, $output);
        if ($values === null) {
            return Command::FAILURE;
        }

        try {
            $config = $this->configValues($values);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $service = $this->buildCaptchaService($config);
        $deleted = $service->cleanupExpired();
        $output->writeln("Deleted {$deleted} expired CAPTCHA files.");
        return Command::SUCCESS;
    }

    private function resolveValues(
        PipelineConfigService $pipelineSpec,
        string $pipeline,
        OutputInterface $output
    ): ?array {
        try {
            return $pipelineSpec->values($pipeline, 'runtime');
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return null;
        }
    }

    private function buildCaptchaService(ConfigValues $config): CaptchaService
    {
        $rootPath = $this->rootPath();
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner(Path::join($rootPath, 'var', 'state', 'locks'));
        $writer = new RuntimeAtomicWriter();
        return new CaptchaService(
            $storage,
            $lockRunner,
            $writer,
            Path::join($rootPath, 'var', 'tmp', 'captcha'),
            $config->getInt('CAPTCHA_TTL_SECONDS', 600)
        );
    }
}
