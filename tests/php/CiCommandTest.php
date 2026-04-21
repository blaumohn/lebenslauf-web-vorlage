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

    public function testDeployCheckAcceptsExplicitDirOption(): void
    {
        $output = $this->runCi(['deploy-check', 'preview', '--dir', '/tmp/preview-deploy-test'], false);

        self::assertStringNotContainsString('Unbekanntes Argument', $output);
        self::assertStringNotContainsString('Wert fuer --dir fehlt', $output);
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
