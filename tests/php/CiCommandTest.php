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

    public function testCiRequiresAppPipelineEnvironment(): void
    {
        $output = $this->runCi([]);

        self::assertStringContainsString('APP_PIPELINE nicht gesetzt', $output);
        self::assertStringNotContainsString('CI_PIPELINE nicht gesetzt', $output);
    }

    public function testCiReadsAppPipelineFromEnvironment(): void
    {
        $output = $this->runCi([
            'APP_PIPELINE' => 'preview',
        ]);

        self::assertStringNotContainsString('APP_PIPELINE nicht gesetzt', $output);
        self::assertStringNotContainsString('CI_PIPELINE nicht gesetzt', $output);
    }

    private function runCi(array $env, bool $mustSucceed = false): string
    {
        $process = new Process(['bash', 'bin/ci'], $this->rootPath, array_merge([
            'SMTP_PASS' => 'preview-secret',
            'FTP_HOST' => 'preview.example.test',
            'FTP_USER' => 'preview-user',
            'FTP_PASS' => 'preview-pass',
            'FTP_PORT' => '21',
            'FTP_SERVER_DIR' => '/home/preview/public',
        ], $env));
        if ($mustSucceed) {
            $process->mustRun();
            return $process->getOutput();
        }
        $process->run();
        return $process->getOutput() . $process->getErrorOutput();
    }
}
