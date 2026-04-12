<?php

declare(strict_types=1);

use App\Cli\Command\BasePipelineCommand;
use PipelineConfigSpec\PipelineConfigService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class PipelineCommandConfigTest extends TestCase
{
    public function testOverrideRequiresKeyValueSyntax(): void
    {
        $command = new PipelineCommandConfigTestCommand();
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'pipeline' => 'dev',
            '--override' => ['PYTHON_PATHS'],
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString(
            'Override muss als KEY=VALUE angegeben werden.',
            $tester->getDisplay()
        );
    }

    public function testPythonPathsCliSourceIsAccepted(): void
    {
        $rootPath = dirname(__DIR__, 2);
        $service = new PipelineConfigService($rootPath, 'src/resources/config');

        $report = $service->describe('dev', 'python', [
            'PYTHON_PATHS' => 'src:.',
        ]);

        self::assertSame('src:.', $report['values']['PYTHON_PATHS'] ?? null);
        self::assertSame('cli', $report['sources']['PYTHON_PATHS'] ?? null);
    }
}

final class PipelineCommandConfigTestCommand extends BasePipelineCommand
{
    protected function commandPhase(): string
    {
        return 'python';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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

        $output->writeln((string) $config->get('PYTHON_PATHS', ''));
        return Command::SUCCESS;
    }
}
