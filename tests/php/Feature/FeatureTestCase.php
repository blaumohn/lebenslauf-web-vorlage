<?php

declare(strict_types=1);

use App\Http\Security\IpHashService;
use App\Http\Security\IpSaltRuntime;
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
            $this->root . '/src/resources/config'
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
        ]);
        $this->compileConfig();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
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
        $runtime = $this->buildIpSaltRuntime();
        $salt = $runtime->resolveSalt();
        $service = new IpHashService($salt);
        return $service->hashIp($ip);
    }

    private function configSourceDir(): string
    {
        return $this->projectRoot() . '/src/resources/config';
    }

    private function createTestRoot(): string
    {
        $suffix = '/php-mvp-app-' . bin2hex(random_bytes(6));
        $baseDir = sys_get_temp_dir();
        $root = $baseDir . $suffix;
        if (@mkdir($root, 0775, true)) {
            return $root;
        }

        $fallback = $this->projectRoot() . '/var/tmp';
        $root = $fallback . $suffix;
        if (@mkdir($root, 0775, true)) {
            return $root;
        }

        throw new RuntimeException('Konnte Test-Verzeichnis nicht anlegen: ' . $root);
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
        $configService = new PipelineConfigService($this->root, 'src/resources/config');
        $configService->compile('dev', 'runtime');
    }

    private function buildIpSaltRuntime(): IpSaltRuntime
    {
        $storage = new FileStorage();
        return new IpSaltRuntime(
            $storage,
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
