<?php

namespace App\Cli\Command;

use App\Cli\Site\SiteBuildService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'publish', description: 'Baut Site-HTML lokal und veröffentlicht es auf dem Server.')]
final class PublishCommand extends BasePipelineCommand
{
    use PythonRunnerAware;

    private const PUBLISH_SCRIPT = 'scripts/publish.py';

    protected function configure(): void
    {
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $buildConfig = $this->resolvePipelineConfig('build', $output);
        if ($buildConfig === null) {
            return Command::FAILURE;
        }
        if (!$this->runBuild($buildConfig, $output)) {
            return Command::FAILURE;
        }
        $deployValues = $this->getValuesByPhase(['deploy'], $output);
        if ($deployValues === null) {
            return Command::FAILURE;
        }
        return $this->pythonRunner()->runScript(self::PUBLISH_SCRIPT, $deployValues);
    }

    private function runBuild(\App\Cli\ConfigValues $config, OutputInterface $output): bool
    {
        $service = SiteBuildService::create($config, $this->appRoot());
        try {
            $service->build($output);
        } catch (\RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return false;
        }
        return true;
    }
}
