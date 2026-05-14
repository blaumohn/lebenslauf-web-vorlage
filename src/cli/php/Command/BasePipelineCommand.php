<?php

namespace App\Cli\Command;

use App\Cli\ConfigValues;
use PipelineConfigSpec\PipelineConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BasePipelineCommand extends Command
{
    use RootPathAware;

    private ?string $pipelineName = null;
    private array $overrides = [];

    protected function configure(): void
    {
        $this->addArgument('pipeline', InputArgument::REQUIRED, 'Pipeline-Name')
            ->addOption('overrides', null, InputOption::VALUE_REQUIRED, 'Config-Overrides als flaches JSON ({"KEY":"WERT"})');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $pipeline = $this->resolveStringArg($input, 'pipeline');
        if ($pipeline === null) {
            throw new \InvalidArgumentException('Pipeline fehlt. Beispiel: dev');
        }
        $this->pipelineName = $pipeline;

        if (!$input->hasOption('overrides')) {
            return;
        }
        $raw = $input->getOption('overrides');
        if ($raw === null) {
            return;
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('--overrides: ungültiges JSON');
        }
        $this->overrides = $decoded;
    }

    protected function pipelineName(): string
    {
        if ($this->pipelineName === null) {
            throw new \LogicException('Pipeline wurde noch nicht aufgeloest.');
        }
        return $this->pipelineName;
    }

    protected function pipelineValues(string $phase): array
    {
        return $this->configService()->values($this->pipelineName(), $phase, $this->overrides);
    }

    protected function pipelineHasPhase(string $phase): bool
    {
        return $this->configService()->cliVarsForPhase($this->pipelineName(), $phase) !== [];
    }

    protected function pipelineDescribe(string $phase): array
    {
        return $this->configService()->describe($this->pipelineName(), $phase, $this->overrides);
    }

    protected function pipelineCompile(string $phase, ?string $targetPath = null): string
    {
        return $this->configService()->compile($this->pipelineName(), $phase, $targetPath, $this->overrides);
    }

    protected function pipelineValidate(): void
    {
        $this->configService()->validate($this->pipelineName(), $this->overrides);
    }

    protected function getValuesByPhase(array $phases, OutputInterface $output): ?array
    {
        $result = [];
        foreach ($phases as $phase) {
            try {
                $result[$phase] = $this->pipelineValues($phase);
            } catch (\RuntimeException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return null;
            }
        }
        return $result;
    }

    protected function resolvePipelineConfig(string $phase, OutputInterface $output): ?ConfigValues
    {
        try {
            $values = $this->pipelineValues($phase);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return null;
        }
        return $this->configValues($values);
    }

    private function configDir(): string
    {
        return 'src/resources/pipeline-config';
    }

    private function configService(): PipelineConfig
    {
        return new PipelineConfig($this->rootPath(), $this->configDir());
    }

    private function configValues(array $values): ConfigValues
    {
        return new ConfigValues($this->rootPath(), $values);
    }

    private function resolveStringArg(InputInterface $input, string $name): ?string
    {
        $value = $input->getArgument($name);
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
