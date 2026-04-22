<?php

declare(strict_types=1);

use App\Cli\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ConfigCommandTest extends TestCase
{
    public function testGetReturnsDeployValueFromConfiguredSource(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute([
            'action'       => 'get',
            'pipeline'     => 'preview',
            'arg1'         => 'FTP_PORT',
            '--phase'      => 'deploy',
            '--overrides'  => '{"preview":{"deploy":{"ftp":{"FTP_HOST":"h","FTP_USER":"u","FTP_PASS":"p"}}}}',
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame('21', trim($tester->getDisplay()));
    }

    public function testGetReturnsRuntimeSecretFromCliOverride(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute([
            'action'      => 'get',
            'pipeline'    => 'preview',
            'arg1'        => 'SMTP_PASS',
            '--phase'     => 'runtime',
            '--overrides' => '{"runtime":{"smtp":{"SMTP_PASS":"preview-secret"}}}',
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame('preview-secret', trim($tester->getDisplay()));
    }

    public function testLintChecksRequestedDeployPhase(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute([
            'action'      => 'lint',
            'pipeline'    => 'preview',
            '--phase'     => 'deploy',
            '--overrides' => '{"preview":{"deploy":{"ftp":{"FTP_HOST":"h","FTP_USER":"u","FTP_PASS":"p"}}}}',
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
}
