<?php

declare(strict_types=1);

use App\Cli\Application;
use App\Cli\CliContext;
use App\Cli\Command\TokenCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class TokenCommandTest extends TestCase
{
    public function testCommandDefinition(): void
    {
        $definition = (new TokenCommand($this->context()))->getDefinition();

        self::assertTrue($definition->hasArgument('pipeline'));
        self::assertTrue($definition->hasArgument('action'));
        self::assertTrue($definition->hasArgument('profile'));
        self::assertTrue($definition->hasArgument('value'));
        self::assertTrue($definition->hasOption('label'));
        self::assertTrue($definition->hasOption('ttl-days'));
        self::assertTrue($definition->hasOption('no-expiry'));
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

    public function testRejectsMissingProfile(): void
    {
        $root = $this->makeTempRoot();
        $tester = $this->testerForRoot($root);
        $exitCode = $tester->execute(['pipeline' => 'dev', 'action' => 'add']);
        $this->removeDir($root);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Profil', $tester->getDisplay());
    }

    public function testAddsTokensLocallyForDevPipeline(): void
    {
        $root = $this->makeTempRoot();
        file_put_contents($root . '/var/cache/html/cv-private-default.de.html', '<html/>');

        $tester = $this->testerForRoot($root);
        $exitCode = $tester->execute([
            'pipeline' => 'dev',
            'action'   => 'add',
            'profile'  => 'default',
        ]);
        $this->removeDir($root);

        self::assertSame(0, $exitCode);
        self::assertNotEmpty(trim($tester->getDisplay()), 'Kein Token ausgegeben');
    }

    public function testAddRejectsUnknownProfile(): void
    {
        $root = $this->makeTempRoot();
        $tester = $this->testerForRoot($root);
        $exitCode = $tester->execute([
            'pipeline' => 'dev',
            'action'   => 'add',
            'profile'  => 'nichtvorhanden',
        ]);
        $this->removeDir($root);

        self::assertSame(1, $exitCode);
    }

    public function testAddIsAdditiveAcrossInvocations(): void
    {
        $root = $this->makeTempRoot();
        file_put_contents($root . '/var/cache/html/cv-private-default.de.html', '<html/>');
        $tester = $this->testerForRoot($root);

        $tester->execute(['pipeline' => 'dev', 'action' => 'add', 'profile' => 'default']);
        $tester->execute(['pipeline' => 'dev', 'action' => 'add', 'profile' => 'default']);

        $listTester = $this->testerForRoot($root);
        $listTester->execute(['pipeline' => 'dev', 'action' => 'list', 'profile' => 'default']);
        $output = $listTester->getDisplay();
        $this->removeDir($root);

        self::assertSame(2, substr_count($output, 'label='));
    }

    public function testListReportsNoSharesForUnknownProfile(): void
    {
        $root = $this->makeTempRoot();
        $tester = $this->testerForRoot($root);
        $exitCode = $tester->execute(['pipeline' => 'dev', 'action' => 'list', 'profile' => 'unbekannt']);
        $this->removeDir($root);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Keine Freigaben', $tester->getDisplay());
    }

    public function testRevokeByLabelRemovesShare(): void
    {
        $root = $this->makeTempRoot();
        file_put_contents($root . '/var/cache/html/cv-private-default.de.html', '<html/>');
        $addTester = $this->testerForRoot($root);
        $addTester->execute([
            'pipeline' => 'dev',
            'action'   => 'add',
            'profile'  => 'default',
            '--label'  => 'firma-x',
        ]);

        $revokeTester = $this->testerForRoot($root);
        $exitCode = $revokeTester->execute([
            'pipeline' => 'dev',
            'action'   => 'revoke',
            'profile'  => 'default',
            'value'    => 'firma-x',
        ]);
        $this->removeDir($root);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('1 Freigabe(n) entfernt', $revokeTester->getDisplay());
    }

    public function testRevokeWithoutMatchFails(): void
    {
        $root = $this->makeTempRoot();
        file_put_contents($root . '/var/cache/html/cv-private-default.de.html', '<html/>');
        $addTester = $this->testerForRoot($root);
        $addTester->execute(['pipeline' => 'dev', 'action' => 'add', 'profile' => 'default']);

        $revokeTester = $this->testerForRoot($root);
        $exitCode = $revokeTester->execute([
            'pipeline' => 'dev',
            'action'   => 'revoke',
            'profile'  => 'default',
            'value'    => 'nichtvorhanden',
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
