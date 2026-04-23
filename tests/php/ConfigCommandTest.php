<?php

declare(strict_types=1);

use App\Cli\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

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
            '--overrides'  => '{"preview":{"deploy":{"ftp":{"FTP_HOST":"h","FTP_USER":"u","FTP_PASS":"p","SSH_KNOWN_HOST_LINE":"p"}}}}',
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame('21', trim($tester->getDisplay()));
    }

    public function testGetReturnsRuntimeSecretFromCliOverride(): void
    {
        $manifestPath = $this->manifestPath();
        $originalManifest = file_get_contents($manifestPath);
        self::assertNotFalse($originalManifest);
        $manifest = Yaml::parse($originalManifest);
        self::assertIsArray($manifest);

        try {
            $manifest['pipelines'] ??= [];
            $manifest['pipelines']['preview'] ??= [];
            $manifest['pipelines']['preview']['runtime'] ??= [];
            $manifest['pipelines']['preview']['runtime']['smtp'] = ['SMTP_PASS'];
            $this->writeManifest($manifestPath, $manifest);

            $tester = $this->tester();
            $exitCode = $tester->execute([
                'action'      => 'get',
                'pipeline'    => 'preview',
                'arg1'        => 'SMTP_PASS',
                '--phase'     => 'runtime',
                '--overrides' => '{"preview":{"runtime":{"smtp":{"SMTP_PASS":"preview-secret"}}}}',
            ]);

            self::assertSame(0, $exitCode);
            self::assertSame('preview-secret', trim($tester->getDisplay()));
        } finally {
            file_put_contents($manifestPath, $originalManifest);
        }
    }

    public function testGetFailsWithoutPhaseOption(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute([
            'action'   => 'get',
            'pipeline' => 'preview',
            'arg1'     => 'SMTP_PASS',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString(
            '--phase fehlt. Beispiel: --phase runtime',
            $tester->getDisplay()
        );
    }

    public function testLintChecksRequestedDeployPhase(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute([
            'action'      => 'lint',
            'pipeline'    => 'preview',
            '--phase'     => 'deploy',
            '--overrides' => '{"preview":{"deploy":{"ftp":{"FTP_HOST":"h","FTP_USER":"u","FTP_PASS":"p","SSH_KNOWN_HOST_LINE":"p"}}}}',
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

    private function manifestPath(): string
    {
        return dirname(__DIR__, 2) . '/src/resources/config/config.manifest.yaml';
    }

    private function writeManifest(string $path, array $manifest): void
    {
        $payload = Yaml::dump($manifest, 8, 2);
        if (file_put_contents($path, $payload) === false) {
            throw new \RuntimeException('Failed to write manifest.');
        }
    }
}
