<?php

declare(strict_types=1);

use App\Cli\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ConfigCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->clearPreviewEnv();
    }

    public function testGetReturnsDeployValueFromConfiguredSource(): void
    {
        $this->setDeployEnv();
        $tester = $this->tester();

        $exitCode = $tester->execute([
            'action' => 'get',
            'pipeline' => 'preview',
            'arg1' => 'FTP_PORT',
            '--phase' => 'deploy',
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame('21', trim($tester->getDisplay()));
    }

    public function testGetReturnsRuntimeSecretFromSystemSource(): void
    {
        $this->setRuntimeEnv();
        $tester = $this->tester();

        $exitCode = $tester->execute([
            'action' => 'get',
            'pipeline' => 'preview',
            'arg1' => 'SMTP_PASS',
            '--phase' => 'runtime',
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame('preview-secret', trim($tester->getDisplay()));
    }

    public function testLintChecksRequestedDeployPhase(): void
    {
        $this->setDeployEnv();
        $tester = $this->tester();

        $exitCode = $tester->execute([
            'action' => 'lint',
            'pipeline' => 'preview',
            '--phase' => 'deploy',
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString(
            'Config OK. Pipeline-Phase: preview/deploy',
            $tester->getDisplay()
        );
    }

    private function tester(): CommandTester
    {
        $command = (new Application())->find('config');
        return new CommandTester($command);
    }

    private function setDeployEnv(): void
    {
        putenv('FTP_HOST=preview.example.test');
        putenv('FTP_USER=preview-user');
        putenv('FTP_PASS=preview-pass');
    }

    private function setRuntimeEnv(): void
    {
        putenv('SMTP_PASS=preview-secret');
    }

    private function clearPreviewEnv(): void
    {
        putenv('SMTP_PASS');
        putenv('FTP_HOST');
        putenv('FTP_USER');
        putenv('FTP_PASS');
    }
}
