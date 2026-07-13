<?php

declare(strict_types=1);

use App\Cli\CliContext;
use App\Cli\Command\BasePipelinePhaseCommand;
use App\Cli\Command\CiCommand;
use App\Cli\Command\ConfigCommand;
use PipelineConfigSpec\PipelineConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class PipelineCommandConfigTest extends TestCase
{
    public function testBasePipelineCommandResolvesPipelineAndConfigEarly(): void
    {
        $command = new PipelineCommandConfigTestCommand($this->context());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'pipeline' => 'dev',
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('pipeline=dev', $tester->getDisplay());
        self::assertStringContainsString('config=src/resources/fixtures', $tester->getDisplay());
    }

    public function testBuildPhaseCliOverrideIsAccepted(): void
    {
        $rootPath = dirname(__DIR__, 2);
        $service = new PipelineConfig($rootPath, 'src/resources/pipeline-config');

        $report = $service->describe('dev', 'build', [
            'APP_BASE_PATH' => '/test-path',
        ]);

        self::assertSame('/test-path', $report['values']['APP_BASE_PATH'] ?? null);
        self::assertSame('cli', $report['sources']['APP_BASE_PATH'] ?? null);
    }

    public function testBuildCommandAcceptsDataPathOverride(): void
    {
        $command = new PipelineCommandConfigTestCommand($this->context());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'pipeline'    => 'dev',
            '--overrides' => json_encode(['CONTENT_PATH' => '.local/alt-cv']),
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('config=.local/alt-cv', $tester->getDisplay());
    }

    public function testBasePipelineCommandClearsOverrideBetweenExecutions(): void
    {
        $tester = new CommandTester(new ConfigCommand($this->context()));
        $tester->execute([
            'pipeline'    => 'dev',
            'action'      => 'get',
            'arg1'        => 'CAPTCHA_MAX_GET',
            '--phase'     => 'runtime',
            '--overrides' => json_encode(['CAPTCHA_MAX_GET' => '10']),
        ]);

        $exitCode = $tester->execute([
            'pipeline' => 'dev',
            'action'   => 'get',
            'arg1'     => 'CAPTCHA_MAX_GET',
            '--phase'  => 'runtime',
        ]);

        $configured = (new PipelineConfig($this->context()->appRoot(), 'src/resources/pipeline-config'))
            ->values('dev', 'runtime');

        self::assertSame(0, $exitCode);
        self::assertSame((string) $configured['CAPTCHA_MAX_GET'], $tester->getDisplay());
    }

    public function testCiCommandOnlyAcceptsPipelineArgument(): void
    {
        $definition = (new CiCommand($this->context()))->getDefinition();

        self::assertTrue($definition->hasArgument('pipeline'));
        self::assertFalse($definition->hasArgument('args'));
    }

    private function context(): CliContext
    {
        return new CliContext(dirname(__DIR__, 2));
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
        $output->writeln('config=' . (string) $this->commandConfig()->get('CONTENT_PATH', ''));
        return 0;
    }
}
