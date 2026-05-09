<?php

declare(strict_types=1);

use App\Cli\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ConfigCommandTest extends TestCase
{
    public function testGetReturnsDeployValueFromManifestDefault(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([
            'action'       => 'get',
            'pipeline'     => 'preview',
            'arg1'         => 'SFTP_PORT',
            '--phase'      => 'deploy',
            '--overrides'  => '{"SFTP_HOST":"h","SFTP_USER":"u","SFTP_PASS":"p","SSH_KNOWN_HOST_LINE":"k"}',
        ]);
        self::assertSame(0, $exitCode);
        self::assertSame('22', trim($tester->getDisplay()));
    }

    public function testGetReturnsCliOverrideForDeploySecret(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([
            'action'       => 'get',
            'pipeline'     => 'preview',
            'arg1'         => 'SFTP_HOST',
            '--phase'      => 'deploy',
            '--overrides'  => '{"SFTP_HOST":"override-host","SFTP_USER":"u","SFTP_PASS":"p","SSH_KNOWN_HOST_LINE":"k"}',
        ]);
        self::assertSame(0, $exitCode);
        self::assertSame('override-host', trim($tester->getDisplay()));
    }

    public function testGetFailsWithoutPhaseOption(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([
            'action'   => 'get',
            'pipeline' => 'preview',
            'arg1'     => 'SFTP_HOST',
        ]);
        self::assertSame(1, $exitCode);
        self::assertStringContainsString('--phase fehlt. Beispiel: --phase runtime', $tester->getDisplay());
    }

    public function testLintChecksRequestedDeployPhase(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([
            'action'      => 'lint',
            'pipeline'    => 'preview',
            '--phase'     => 'deploy',
            '--overrides' => '{"SFTP_HOST":"h","SFTP_USER":"u","SFTP_PASS":"p","SSH_KNOWN_HOST_LINE":"k"}',
        ]);
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Config OK. Pipeline-Phase: preview/deploy', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        $command = (new Application())->find('config');
        return new CommandTester($command);
    }
}
