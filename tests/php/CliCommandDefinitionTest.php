<?php

declare(strict_types=1);

use App\Cli\Application;
use App\Cli\CliContext;
use App\Cli\Command\BuildCommand;
use App\Cli\Command\CaptchaCommand;
use App\Cli\Command\IpHashCommand;
use App\Cli\Command\SetupCommand;
use App\Cli\Command\StartCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CliCommandDefinitionTest extends TestCase
{
    public function testBuildCommandDefinition(): void
    {
        $definition = (new BuildCommand($this->context()))->getDefinition();

        self::assertTrue($definition->hasArgument('pipeline'));
        self::assertTrue($definition->hasArgument('task'));
        self::assertTrue($definition->hasArgument('arg1'));
        self::assertTrue($definition->hasArgument('arg2'));
    }

    public function testBuildCommandRejectsUnknownTask(): void
    {
        $tester = $this->tester('build');
        $exitCode = $tester->execute([
            'pipeline' => 'dev',
            'task'     => 'bogus',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Usage:', $tester->getDisplay());
    }

    public function testCaptchaCommandDefinition(): void
    {
        $definition = (new CaptchaCommand($this->context()))->getDefinition();

        self::assertTrue($definition->hasArgument('pipeline'));
        self::assertTrue($definition->hasArgument('action'));
    }

    public function testCaptchaCommandRejectsUnknownAction(): void
    {
        $tester = $this->tester('captcha');
        $exitCode = $tester->execute([
            'pipeline' => 'dev',
            'action'   => 'bogus',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Usage:', $tester->getDisplay());
    }

    public function testIpHashCommandDefinition(): void
    {
        $definition = (new IpHashCommand($this->context()))->getDefinition();

        self::assertTrue($definition->hasArgument('action'));
    }

    public function testIpHashCommandRejectsUnknownAction(): void
    {
        $tester = $this->tester('ip-hash');
        $exitCode = $tester->execute(['action' => 'bogus']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Usage:', $tester->getDisplay());
    }

    public function testStartCommandDefinition(): void
    {
        $definition = (new StartCommand($this->context()))->getDefinition();

        self::assertTrue($definition->hasArgument('pipeline'));
    }

    public function testSetupCommandDefinition(): void
    {
        $definition = (new SetupCommand($this->context()))->getDefinition();

        self::assertTrue($definition->hasArgument('pipeline'));
        self::assertTrue($definition->hasArgument('action'));
        self::assertTrue($definition->hasOption('with-sample-content'));
    }

    private function tester(string $name): CommandTester
    {
        $command = (new Application($this->context()))->find($name);
        return new CommandTester($command);
    }

    private function context(): CliContext
    {
        return new CliContext(dirname(__DIR__, 2));
    }
}
