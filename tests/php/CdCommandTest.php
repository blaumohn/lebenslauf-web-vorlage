<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class CdCommandTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        $this->rootPath = dirname(__DIR__, 2);
    }

    public function testCdRequiresAppPipelineEnvironment(): void
    {
        $output = $this->runCd([]);

        self::assertStringContainsString('APP_PIPELINE nicht gesetzt', $output);
    }

    public function testCdRequiresDeployDirEnvironment(): void
    {
        $output = $this->runCd([
            'APP_PIPELINE' => 'preview',
        ]);

        self::assertStringContainsString('DEPLOY_DIR nicht gesetzt', $output);
    }

    public function testCdRejectsArguments(): void
    {
        $output = $this->runCd([
            'APP_PIPELINE' => 'preview',
            'DEPLOY_DIR' => '/tmp/preview-deploy-test',
        ], ['--dir', '/tmp/legacy']);

        self::assertStringContainsString('bin/cd verwendet keine Argumente', $output);
    }

    public function testCdReadsEnvironmentVariables(): void
    {
        $output = $this->runCd([
            'APP_PIPELINE' => 'preview',
            'DEPLOY_DIR' => '/tmp/preview-deploy-test',
        ]);

        self::assertStringNotContainsString('Pipeline fehlt', $output);
        self::assertStringNotContainsString('Wert fuer --dir fehlt', $output);
        self::assertStringNotContainsString('Unbekanntes Argument', $output);
    }

    private function runCd(array $env, array $args = []): string
    {
        $process = new Process(array_merge(['bash', 'bin/cd'], $args), $this->rootPath, array_merge([
            'SMTP_PASS' => 'preview-secret',
            'FTP_HOST' => 'preview.example.test',
            'FTP_USER' => 'preview-user',
            'FTP_PASS' => 'preview-pass',
            'FTP_PORT' => '21',
            'FTP_SERVER_DIR' => '/home/preview/public',
        ], $env));
        $process->run();

        return $process->getOutput() . $process->getErrorOutput();
    }
}
