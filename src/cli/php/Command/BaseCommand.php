<?php

namespace App\Cli\Command;

use App\Cli\ConfigValues;
use PipelineConfigSpec\PipelineConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    protected function rootPath(): string
    {
        return dirname(__DIR__, 4);
    }

    protected function configDir(): string
    {
        return 'src/resources/config';
    }

    protected function configService(): PipelineConfigService
    {
        $service = new PipelineConfigService($this->rootPath(), $this->configDir());
        return $service;
    }

    protected function configValues(array $values): ConfigValues
    {
        $config = new ConfigValues($this->rootPath(), $values);
        return $config;
    }

    protected function resolvePipelineConfig(
        string $pipeline,
        string $phase,
        array $overrides,
        OutputInterface $output
    ): ?ConfigValues {
        try {
            $values = $this->configService()->values($pipeline, $phase, $overrides);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return null;
        }
        return $this->configValues($values);
    }

    protected function requirePipeline(InputInterface $input, OutputInterface $output): ?string
    {
        $pipeline = $this->resolveStringArgument($input, 'pipeline');
        if ($pipeline !== null) {
            return $pipeline;
        }
        $output->writeln('<error>Pipeline fehlt. Beispiel: dev</error>');
        return null;
    }

    protected function requireOverrides(InputInterface $input, OutputInterface $output): ?array
    {
        $raw = $input->getOption('overrides');
        if ($raw === null || $raw === '') {
            return [];
        }
        if (!is_string($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $output->writeln('<error>--overrides muss ein gültiges JSON-Objekt sein.</error>');
            return null;
        }
        return $decoded;
    }

    private function resolveStringArgument(InputInterface $input, string $name): ?string
    {
        $value = $input->getArgument($name);
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
