<?php

namespace App\Cli\Command;

use App\Cli\ConfigValues;
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
final class CaptchaCommand extends BasePipelineCommand
{
    protected function commandPhase(): string
    {
        return 'runtime';
    }

    protected function configurePipelineCommand(): void
    {
        $this->addArgument('action', InputArgument::OPTIONAL, 'cleanup', 'cleanup');
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
        $overrides = $this->requireOverrides($input, $output);
        if ($overrides === null) {
            return Command::FAILURE;
        }
        $config = $this->resolvePipelineConfig($pipeline, $this->commandPhase(), $overrides, $output);
        if ($config === null) {
            return Command::FAILURE;
        }

        $service = $this->buildCaptchaService($config);
        $deleted = $service->cleanupExpired();
        $output->writeln("Deleted {$deleted} expired CAPTCHA files.");
        return Command::SUCCESS;
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
