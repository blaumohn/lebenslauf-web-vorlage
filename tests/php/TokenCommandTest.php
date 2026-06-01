<?php

declare(strict_types=1);

use App\Cli\Application;
use App\Cli\CliContext;
use App\Cli\Command\TokenCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class TokenCommandTest extends TestCase
{
    public function testCommandDefinition(): void
    {
        $definition = (new TokenCommand($this->context()))->getDefinition();

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
        $args = $method->invoke(new TokenCommand($this->context()), $input);

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
        $args = $method->invoke(new TokenCommand($this->context()), $input);

        self::assertContains('--profile', $args);
        self::assertContains('gueltig', $args);
        self::assertContains('--count', $args);
        self::assertContains('3', $args);
    }

    public function testRotatesLocallyForDevPipeline(): void
    {
        $root = $this->makeTempRoot();
        file_put_contents($root . '/var/cache/html/cv-private-default.html', '<html/>');

        $tester = $this->testerForRoot($root);
        $exitCode = $tester->execute([
            'pipeline' => 'dev',
            'action'   => 'rotate',
            'profile'  => 'default',
        ]);
        $this->removeDir($root);

        self::assertSame(0, $exitCode);
        self::assertNotEmpty(trim($tester->getDisplay()), 'Kein Token ausgegeben');
    }

    public function testRejectsLocalRotationWhenProfileEmpty(): void
    {
        $root = $this->makeTempRoot();
        $tester = $this->testerForRoot($root);
        $exitCode = $tester->execute(['pipeline' => 'dev', 'action' => 'rotate']);
        $this->removeDir($root);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Profil', $tester->getDisplay());
    }

    public function testRejectsLocalRotationForUnknownProfile(): void
    {
        $root = $this->makeTempRoot();
        $tester = $this->testerForRoot($root);
        $exitCode = $tester->execute([
            'pipeline' => 'dev',
            'action'   => 'rotate',
            'profile'  => 'nichtvorhanden',
        ]);
        $this->removeDir($root);

        self::assertSame(1, $exitCode);
    }

    private function tester(): CommandTester
    {
        $command = (new Application($this->context()))->find('token');
        return new CommandTester($command);
    }

    private function testerForRoot(string $root): CommandTester
    {
        $command = (new Application(new CliContext($root)))->find('token');
        return new CommandTester($command);
    }

    private function makeTempRoot(): string
    {
        $root = sys_get_temp_dir() . '/token-cmd-' . bin2hex(random_bytes(4));
        $srcConfig = dirname(__DIR__, 2) . '/src/resources/pipeline-config';
        $destConfig = $root . '/src/resources/pipeline-config';
        mkdir($destConfig, 0775, true);
        foreach (glob($srcConfig . '/*.yaml') ?: [] as $file) {
            copy($file, $destConfig . '/' . basename($file));
        }
        foreach (['/var/cache/html', '/var/state/tokens', '/var/state/locks'] as $dir) {
            mkdir($root . $dir, 0775, true);
        }
        return $root;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function context(): CliContext
    {
        return new CliContext(dirname(__DIR__, 2));
    }
}
