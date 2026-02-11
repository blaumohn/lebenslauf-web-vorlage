<?php

declare(strict_types=1);

use App\Http\Security\IpSaltRuntime;
use App\Http\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

final class IpSaltRuntimeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->createTempRoot();
        $this->ensureDir($this->stateDir());
        $this->ensureDir($this->captchaDir());
        $this->ensureDir($this->rateLimitDir());
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testResolveSaltCreatesStateAndClearsIpFiles(): void
    {
        $this->writeIpFixtures();
        $runtime = $this->runtime();
        $salt = $runtime->resolveSalt();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $salt);
        $this->assertFileExists($this->saltPath());
        $this->assertFileExists($this->fingerprintPath());
        $this->assertIpDirsEmpty();
        $this->assertSecureFileMode($this->saltPath());
        $this->assertSecureFileMode($this->fingerprintPath());
    }

    public function testResolveSaltRotatesWhenFingerprintMismatch(): void
    {
        $runtime = $this->runtime();
        $first = $runtime->resolveSalt();
        $this->writeIpFixtures();
        file_put_contents($this->fingerprintPath(), "broken\n");

        $second = $runtime->resolveSalt();

        $this->assertNotSame($first, $second);
        $this->assertIpDirsEmpty();
    }

    public function testResetSaltAlwaysRotatesAndClearsIpFiles(): void
    {
        $runtime = $this->runtime();
        $first = $runtime->resolveSalt();
        $this->writeIpFixtures();

        $second = $runtime->resetSalt();
        $third = $runtime->resolveSalt();

        $this->assertNotSame($first, $second);
        $this->assertSame($second, $third);
        $this->assertIpDirsEmpty();
    }

    private function runtime(): IpSaltRuntime
    {
        $storage = new FileStorage();
        return new IpSaltRuntime(
            $storage,
            $this->stateDir(),
            $this->captchaDir(),
            $this->rateLimitDir()
        );
    }

    private function createTempRoot(): string
    {
        $root = sys_get_temp_dir() . '/ip-salt-runtime-' . bin2hex(random_bytes(6));
        mkdir($root, 0775, true);
        return $root;
    }

    private function writeIpFixtures(): void
    {
        file_put_contents($this->captchaDir() . '/challenge.json', '{"id":"x"}');
        file_put_contents($this->rateLimitDir() . '/rate.json', '{"timestamps":[1]}');
    }

    private function assertIpDirsEmpty(): void
    {
        $this->assertSame(0, $this->countFiles($this->captchaDir()));
        $this->assertSame(0, $this->countFiles($this->rateLimitDir()));
    }

    private function countFiles(string $dir): int
    {
        $items = scandir($dir);
        if (!is_array($items)) {
            return 0;
        }
        $count = 0;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_file($path)) {
                $count++;
            }
        }
        return $count;
    }

    private function assertSecureFileMode(string $path): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->assertFileExists($path);
            return;
        }
        $mode = fileperms($path) & 0777;
        $this->assertSame(0600, $mode);
    }

    private function stateDir(): string
    {
        return $this->root . '/var/state';
    }

    private function captchaDir(): string
    {
        return $this->root . '/var/tmp/captcha';
    }

    private function rateLimitDir(): string
    {
        return $this->root . '/var/tmp/ratelimit';
    }

    private function saltPath(): string
    {
        return $this->stateDir() . '/ip_salt.txt';
    }

    private function fingerprintPath(): string
    {
        return $this->stateDir() . '/ip_salt.fingerprint';
    }

    private function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
                continue;
            }
            unlink($path);
        }
        rmdir($dir);
    }
}
