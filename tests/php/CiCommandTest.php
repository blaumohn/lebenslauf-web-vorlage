<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class CiCommandTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        $this->rootPath = dirname(__DIR__, 2);
    }

    public function testResolveDeployReturnsEnvFormat(): void
    {
        $this->prepareConfig();

        $output = $this->runCi(['resolve-deploy', 'preview']);

        self::assertStringContainsString("ftp_host=preview.example.test\n", $output);
        self::assertStringContainsString("ftp_port=21\n", $output);
        self::assertStringContainsString("ftp_server_dir=/home/preview/public\n", $output);
    }

    public function testResolveDeploySupportsGithubOutputFormat(): void
    {
        $this->prepareConfig();

        $output = $this->runCi(['resolve-deploy', 'preview', '--format', 'github-output']);

        self::assertStringContainsString("ftp_user=preview-user\n", $output);
        self::assertStringContainsString("ftp_pass=preview-pass\n", $output);
    }

    public function testDeployCheckAcceptsExplicitDirOption(): void
    {
        $output = $this->runCi(['deploy-check', 'preview', '--dir', '/tmp/preview-deploy-test'], false);

        self::assertStringNotContainsString('Unbekanntes Argument', $output);
        self::assertStringNotContainsString('Wert fuer --dir fehlt', $output);
    }

    private function prepareConfig(): void
    {
        $this->runCi(['prepare-config', 'preview']);
    }

    private function runCi(array $args, bool $mustSucceed = true): string
    {
        $process = new Process(array_merge(['bash', 'bin/ci'], $args), $this->rootPath, [
            'SMTP_PASS' => 'preview-secret',
            'FTP_HOST' => 'preview.example.test',
            'FTP_USER' => 'preview-user',
            'FTP_PASS' => 'preview-pass',
            'FTP_PORT' => '21',
            'FTP_SERVER_DIR' => '/home/preview/public',
        ]);
        if ($mustSucceed) {
            $process->mustRun();
            return $process->getOutput();
        }
        $process->run();
        return $process->getOutput() . $process->getErrorOutput();
    }
}
