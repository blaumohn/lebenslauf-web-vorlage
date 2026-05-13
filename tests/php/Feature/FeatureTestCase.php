<?php

declare(strict_types=1);

use App\Http\Security\IpHashService;
use App\Http\Security\IpSaltService;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Storage\FileStorage;
use PipelineConfigSpec\PipelineConfigService;
use App\Http\AppBuilder;
use App\Http\ConfigCompiled;
use PHPUnit\Framework\TestCase;
use Slim\App;

abstract class FeatureTestCase extends TestCase
{
    protected string $root;

    protected function setUp(): void
    {
        $this->root = $this->createTestRoot();
        $this->copyDir(
            $this->configSourceDir(),
            $this->root . '/src/resources/pipeline-config'
        );
        $this->copyDir($this->projectRoot() . '/src/resources/templates', $this->root . '/src/resources/templates');
        $this->copyFile(
            $this->projectRoot() . '/src/resources/build/labels.json',
            $this->root . '/src/resources/build/labels.json'
        );
        $this->ensureDirs([
            $this->root . '/var/tmp/captcha',
            $this->root . '/var/tmp/ratelimit',
            $this->root . '/var/cache/html',
            $this->root . '/var/state/tokens',
            $this->root . '/var/config',
            $this->root . '/var/state/locks',
        ]);
        $this->compileConfig();
    }

    protected function tearDown(): void
    {
        $this->removeDir(dirname($this->root));
    }

    protected function app(): App
    {
        $config = new ConfigCompiled($this->root);
        return AppBuilder::build($config);
    }

    protected function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    protected function ipHashFor(string $ip): string
    {
        $ipSaltService = $this->buildIpSaltService();
        $salt = $ipSaltService->resolveSalt();
        $ipHashService = new IpHashService($salt);
        return $ipHashService->hashIp($ip);
    }

    protected function buildTokenService(): \App\Http\Security\TokenService
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->root . '/var/state/locks');
        $writer = new RuntimeAtomicWriter();
        return new \App\Http\Security\TokenService(
            $storage,
            $lockRunner,
            $writer,
            $this->root . '/var/state/tokens'
        );
    }

    protected function buildCaptchaService(): \App\Http\Captcha\CaptchaService
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->root . '/var/state/locks');
        $writer = new RuntimeAtomicWriter();
        return new \App\Http\Captcha\CaptchaService(
            $storage,
            $lockRunner,
            $writer,
            $this->root . '/var/tmp/captcha',
            600
        );
    }

    private function configSourceDir(): string
    {
        return $this->projectRoot() . '/src/resources/pipeline-config';
    }

    private function createTestRoot(): string
    {
        $suffix = '/php-mvp-app-' . bin2hex(random_bytes(6));
        $baseDir = sys_get_temp_dir();
        $base = $baseDir . $suffix;
        if (@mkdir($base . '/app', 0775, true)) {
            return $base . '/app';
        }

        $fallback = $this->projectRoot() . '/var/tmp';
        $base = $fallback . $suffix;
        if (@mkdir($base . '/app', 0775, true)) {
            return $base . '/app';
        }

        throw new RuntimeException('Konnte Test-Verzeichnis nicht anlegen: ' . $base . '/app');
    }

    private function ensureDirs(array $dirs): void
    {
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
    }

    private function compileConfig(): void
    {
        $configService = new PipelineConfigService($this->root, 'src/resources/pipeline-config');
        $configService->compile('dev', 'runtime');
    }

    private function buildIpSaltService(): IpSaltService
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->root . '/var/state/locks');
        $writer = new RuntimeAtomicWriter();
        return new IpSaltService(
            $storage,
            $lockRunner,
            $writer,
            $this->root . '/var/state',
            $this->root . '/var/tmp/captcha',
            $this->root . '/var/tmp/ratelimit'
        );
    }

    private function copyDir(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0775, true);
        }

        $items = scandir($source);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = $source . DIRECTORY_SEPARATOR . $item;
            $destPath = $dest . DIRECTORY_SEPARATOR . $item;

            if (is_dir($srcPath)) {
                $this->copyDir($srcPath, $destPath);
            } else {
                copy($srcPath, $destPath);
            }
        }
    }

    private function copyFile(string $source, string $dest): void
    {
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        copy($source, $dest);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
