<?php

declare(strict_types=1);

use App\Cli\Application;
use App\Cli\Command\TokenCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class TokenCommandTest extends TestCase
{
    public function testCommandDefinition(): void
    {
        $definition = (new TokenCommand())->getDefinition();

        self::assertTrue($definition->hasArgument('pipeline'));
        self::assertTrue($definition->hasArgument('action'));
        self::assertTrue($definition->hasArgument('profile'));
        self::assertTrue($definition->hasArgument('count'));
    }

    public function testRejectsUnknownAction(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([
            'pipeline' => 'dev',
            'action'   => 'invalid',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Usage:', $tester->getDisplay());
    }

    public function testBuildRotateArgsPassesTaskTypeFirst(): void
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturnMap([
            ['profile', 'gueltig'],
            ['count', '1'],
        ]);

        $method = new ReflectionMethod(TokenCommand::class, 'buildRotateArgs');
        $args = $method->invoke(new TokenCommand(), $input);

        self::assertSame('cv_token_rotation', $args[0]);
    }

    public function testBuildRotateArgsPassesProfileAndCountFlags(): void
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturnMap([
            ['profile', 'gueltig'],
            ['count', '3'],
        ]);

        $method = new ReflectionMethod(TokenCommand::class, 'buildRotateArgs');
        $args = $method->invoke(new TokenCommand(), $input);

        self::assertContains('--profile', $args);
        self::assertContains('gueltig', $args);
        self::assertContains('--count', $args);
        self::assertContains('3', $args);
    }

    private function tester(): CommandTester
    {
        $command = (new Application())->find('token');
        return new CommandTester($command);
    }
}
