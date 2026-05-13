<?php

declare(strict_types=1);

use App\Cli\Command\BasePipelinePhaseCommand;
use App\Cli\Command\CiCommand;
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
        self::assertStringContainsString('config=.local/lebenslauf', $tester->getDisplay());
    }

    public function testBuildPhaseCliOverrideIsAccepted(): void
    {
        $rootPath = dirname(__DIR__, 2);
        $service = new PipelineConfigService($rootPath, 'src/resources/pipeline-config');

        $report = $service->describe('dev', 'build', [
            'APP_BASE_PATH' => '/test-path',
        ]);

        self::assertSame('/test-path', $report['values']['APP_BASE_PATH'] ?? null);
        self::assertSame('cli', $report['sources']['APP_BASE_PATH'] ?? null);
    }

    public function testCiCommandOnlyAcceptsPipelineArgument(): void
    {
        $definition = (new CiCommand())->getDefinition();

        self::assertTrue($definition->hasArgument('pipeline'));
        self::assertFalse($definition->hasArgument('args'));
    }
}

final class PipelineCommandConfigTestCommand extends BasePipelinePhaseCommand
{
    protected function commandPhase(): string
    {
        return 'build';
    }

    protected function runPipelineCommand(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('pipeline=' . $this->pipelineName());
        $output->writeln('config=' . (string) $this->commandConfig()->get('LEBENSLAUF_DATEN_PFAD', ''));
        return 0;
    }
}
