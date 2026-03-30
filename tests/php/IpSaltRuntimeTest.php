<?php

declare(strict_types=1);

use App\Http\Security\IpSaltRuntime;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
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
        $this->ensureDir($this->lockDir());
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testResolveSaltCreatesUnifiedStateAndClearsIpFiles(): void
    {
        $this->writeIpFixtures();
        $runtime = $this->runtime();
        $salt = $runtime->resolveSalt();
        $state = $this->readStatePayload();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $salt);
        $this->assertFileExists($this->statePath());
        $this->assertSame($salt, $state['salt']);
        $this->assertSame(hash('sha256', 'ip-salt:' . $salt), $state['fingerprint']);
        $this->assertSame('READY', $state['status']);
        $this->assertSame(1, $state['generation']);
        $this->assertArrayHasKey('updated_at', $state);
        $this->assertFileDoesNotExist($this->stateDir() . '/ip_salt.txt');
        $this->assertFileDoesNotExist($this->stateDir() . '/ip_salt.fingerprint');
        $this->assertFileDoesNotExist($this->stateDir() . '/ip_salt.marker.json');
        $this->assertIpDirsEmpty();
        $this->assertSecureFileMode($this->statePath());
    }

    public function testResolveSaltRotatesWhenFingerprintMismatch(): void
    {
        $runtime = $this->runtime();
        $first = $runtime->resolveSalt();
        $this->writeIpFixtures();
        $state = $this->readStatePayload();
        $state['fingerprint'] = 'broken';
        $this->writeStatePayload($state);

        $second = $runtime->resolveSalt();
        $next = $this->readStatePayload();

        $this->assertNotSame($first, $second);
        $this->assertSame('READY', $next['status']);
        $this->assertSame(2, $next['generation']);
        $this->assertIpDirsEmpty();
    }

    public function testResetSaltAlwaysRotatesAndKeepsStableAfterwards(): void
    {
        $runtime = $this->runtime();
        $first = $runtime->resolveSalt();
        $this->writeIpFixtures();
        $second = $runtime->resetSalt();
        $third = $runtime->resolveSalt();
        $state = $this->readStatePayload();

        $this->assertNotSame($first, $second);
        $this->assertSame($second, $third);
        $this->assertSame(2, $state['generation']);
        $this->assertSame('READY', $state['status']);
        $this->assertIpDirsEmpty();
    }

    public function testResolveSaltRecoversFromInProgressState(): void
    {
        $runtime = $this->runtime();
        $first = $runtime->resolveSalt();
        $state = $this->readStatePayload();
        $state['status'] = 'IN_PROGRESS';
        $this->writeStatePayload($state);

        $second = $runtime->resolveSalt();
        $next = $this->readStatePayload();

        $this->assertNotSame($first, $second);
        $this->assertSame('READY', $next['status']);
        $this->assertSame(2, $next['generation']);
    }

    private function runtime(): IpSaltRuntime
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->lockDir());
        $writer = new RuntimeAtomicWriter();
        return new IpSaltRuntime(
            $storage,
            $lockRunner,
            $writer,
            $this->stateDir(),
            $this->captchaDir(),
            $this->rateLimitDir()
        );
    }

    private function readStatePayload(): array
    {
        $raw = file_get_contents($this->statePath());
        if (!is_string($raw)) {
            throw new RuntimeException('State-Datei konnte nicht gelesen werden.');
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        throw new RuntimeException('State-Datei hat ungueltiges JSON.');
    }

    private function writeStatePayload(array $payload): void
    {
        $payload['updated_at'] = gmdate('c');
        $encoded = json_encode($payload);
        if (!is_string($encoded)) {
            throw new RuntimeException('State-Datei konnte nicht geschrieben werden.');
        }
        file_put_contents($this->statePath(), $encoded . "\n");
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

    private function createTempRoot(): string
    {
        $suffix = '/ip-salt-runtime-' . bin2hex(random_bytes(6));
        $root = sys_get_temp_dir() . $suffix;
        if (@mkdir($root, 0775, true)) {
            return $root;
        }
        $fallback = dirname(__DIR__, 2) . '/var/tmp';
        $root = $fallback . $suffix;
        if (@mkdir($root, 0775, true)) {
            return $root;
        }
        throw new RuntimeException('Konnte Test-Verzeichnis nicht anlegen: ' . $root);
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

    private function lockDir(): string
    {
        return $this->root . '/var/state/locks';
    }

    private function statePath(): string
    {
        return $this->stateDir() . '/ip_salt.state.json';
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
