<?php

declare(strict_types=1);

use App\Http\Runtime\RuntimeLockRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

final class RuntimeLockRunnerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->createTempRoot();
        $this->ensureDir($this->lockDir());
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testRunWithLockReturnsCallbackValue(): void
    {
        $runner = new RuntimeLockRunner($this->lockDir(), 120, 20);
        $value = $runner->runWithLock('runtime_lock_ok', [$this, 'callbackValue']);
        $this->assertSame('ok', $value);
    }

    public function testRunWithLockTimesOutWhenLockIsBusy(): void
    {
        $factory = new LockFactory(new FlockStore($this->lockDir()));
        $lock = $factory->createLock('runtime_lock_busy');
        $this->assertTrue($lock->acquire(false));
        $runner = new RuntimeLockRunner($this->lockDir(), 80, 10);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lock-Timeout');
        try {
            $runner->runWithLock('runtime_lock_busy', [$this, 'callbackValue']);
        } finally {
            $lock->release();
        }
    }

    public function callbackValue(): string
    {
        return 'ok';
    }

    private function createTempRoot(): string
    {
        $suffix = '/runtime-lock-' . bin2hex(random_bytes(6));
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

    private function lockDir(): string
    {
        return $this->root . '/var/state/locks';
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
