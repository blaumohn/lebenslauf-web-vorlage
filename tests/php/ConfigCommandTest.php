<?php

declare(strict_types=1);

use App\Cli\Application;
use App\Cli\CliContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ConfigCommandTest extends TestCase
{
    public function testGetReturnsDeployValueFromManifestDefault(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([
            'pipeline'    => 'preview',
            'action'      => 'get',
            'arg1'        => 'SFTP_PORT',
            '--phase'     => 'deploy',
            '--overrides' => json_encode($this->fullOverrides()),
        ]);
        self::assertSame(0, $exitCode);
        self::assertSame('22', trim($tester->getDisplay()));
    }

    public function testGetReturnsCliOverrideForDeploySecret(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([
            'pipeline'    => 'preview',
            'action'      => 'get',
            'arg1'        => 'SFTP_HOST',
            '--phase'     => 'deploy',
            '--overrides' => json_encode(array_merge($this->fullOverrides(), ['SFTP_HOST' => 'override-host'])),
        ]);
        self::assertSame(0, $exitCode);
        self::assertSame('override-host', trim($tester->getDisplay()));
    }

    public function testGetFailsWithoutPhaseOption(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([
            'pipeline' => 'preview',
            'action'   => 'get',
            'arg1'     => 'SFTP_HOST',
        ]);
        self::assertSame(1, $exitCode);
        self::assertStringContainsString('--phase fehlt. Beispiel: --phase runtime', $tester->getDisplay());
    }

    public function testLintValidatesFullPipeline(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([
            'pipeline'    => 'preview',
            'action'      => 'lint',
            '--overrides' => json_encode($this->fullOverrides()),
        ]);
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Config OK. Pipeline: preview', $tester->getDisplay());
    }

    public function testInitFailsWhenLocalConfigAlreadyExists(): void
    {
        $localFile = dirname(__DIR__, 2) . '/.local/prod.yaml';
        self::assertFileDoesNotExist($localFile, 'Testaufbau erwartet keine bestehende .local/prod.yaml');
        file_put_contents($localFile, "app:\n  APP_ROOT_URL: existing\n");
        try {
            $tester = $this->tester();
            $exitCode = $tester->execute([
                'pipeline' => 'prod',
                'action'   => 'init',
            ]);
            self::assertSame(1, $exitCode);
            self::assertStringContainsString('Config-Datei existiert bereits', $tester->getDisplay());
        } finally {
            unlink($localFile);
        }
    }

    private function fullOverrides(): array
    {
        return [
            'APP_ROOT_URL'       => 'https://example.invalid',
            'MAIL_TO_EMAIL'      => 'test@example.invalid',
            'SMTP_HOST'          => 'smtp.example.invalid',
            'SMTP_USER'          => 'testuser',
            'SMTP_PASS'          => 'testpass',
            'SMTP_FROM_EMAIL'    => 'from@example.invalid',
            'SFTP_SERVER_DIR'    => '/deploy/preview',
            'SFTP_HOST'          => 'sftp.example.invalid',
            'SFTP_USER'          => 'sftpuser',
            'SFTP_PASS'          => 'sftppass',
            'SSH_KNOWN_HOST_LINE' => 'sftp.example.invalid ssh-rsa AAAA',
        ];
    }

    private function tester(): CommandTester
    {
        $command = (new Application($this->context()))->find('config');
        return new CommandTester($command);
    }

    private function context(): CliContext
    {
        return new CliContext(dirname(__DIR__, 2));
    }
}
