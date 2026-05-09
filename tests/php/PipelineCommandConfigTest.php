<?php

declare(strict_types=1);

use App\Cli\Command\BasePipelinePhaseCommand;
use PipelineConfigSpec\PipelineConfigService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class PipelineCommandConfigTest extends TestCase
{
    public function testBasePipelineCommandResolvesPipelineAndConfigEarly(): void
    {
        $command = new PipelineCommandConfigTestCommand();
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'pipeline' => 'dev',
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('pipeline=dev', $tester->getDisplay());
        self::assertStringContainsString('config=src', $tester->getDisplay());
    }

    public function testPythonPathsCliSourceIsAccepted(): void
    {
        $rootPath = dirname(__DIR__, 2);
        $service = new PipelineConfigService($rootPath, 'src/resources/pipeline-config');

        $report = $service->describe('dev', 'python', [
            'PYTHON_PATHS' => 'src:.',
        ]);

        self::assertSame('src:.', $report['values']['PYTHON_PATHS'] ?? null);
        self::assertSame('cli', $report['sources']['PYTHON_PATHS'] ?? null);
    }
}

final class PipelineCommandConfigTestCommand extends BasePipelinePhaseCommand
{
    protected function commandPhase(): string
    {
        return 'python';
    }

    protected function runPipelineCommand(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('pipeline=' . $this->pipelineName());
        $output->writeln('config=' . (string) $this->commandConfig()->get('PYTHON_PATHS', ''));
        return 0;
    }
}
