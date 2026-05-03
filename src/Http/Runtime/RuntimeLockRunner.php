<?php

namespace App\Http\Runtime;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

final class RuntimeLockRunner
{
    private string $lockDir;
    private int $timeoutMs;
    private int $pollIntervalMs;

    public function __construct(string $lockDir, int $timeoutMs = 300, int $pollIntervalMs = 25)
    {
        $this->lockDir = rtrim($lockDir, DIRECTORY_SEPARATOR);
        $this->timeoutMs = $this->requirePositiveMs($timeoutMs, 'timeoutMs');
        $this->pollIntervalMs = $this->requirePositiveMs($pollIntervalMs, 'pollIntervalMs');
    }

    public function runWithLock(string $key, callable $operation): mixed
    {
        $this->assertSymfonyLockAvailable();
        $normalizedKey = $this->normalizeKey($key);
        $result = $this->runWithSymfonyLock($normalizedKey, $operation);
        return $result;
    }

    private function assertSymfonyLockAvailable(): void
    {
        if (!class_exists(FlockStore::class) || !class_exists(LockFactory::class)) {
            throw new \RuntimeException('symfony/lock ist erforderlich, aber nicht verfuegbar.');
        }
    }

    private function runWithSymfonyLock(string $key, callable $operation): mixed
    {
        $this->ensureDir($this->lockDir);
        $this->debugLockDir();
        $store = new FlockStore($this->lockDir);
        $factory = new LockFactory($store);
        $lock = $factory->createLock($key);
        $this->acquireWithTimeout($lock, $key);
        try {
            $result = $operation();
            return $result;
        } finally {
            if ($lock->isAcquired()) {
                $lock->release();
            }
        }
    }

    private function acquireWithTimeout(object $lock, string $key): void
    {
        $deadline = microtime(true) + ($this->timeoutMs / 1000);
        while (true) {
            if ($lock->acquire(false)) {
                return;
            }
            if (microtime(true) >= $deadline) {
                throw new \RuntimeException("Lock-Timeout nach {$this->timeoutMs}ms: {$key}");
            }
            usleep($this->pollIntervalMs * 1000);
        }
    }

    private function normalizeKey(string $key): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        if (is_string($value) && $value !== '') {
            return $value;
        }
        throw new \RuntimeException('Lock-Key ist ungueltig.');
    }

    private function debugLockDir(): void
    {
        $dir = $this->lockDir;
        $perms = file_exists($dir) ? decoct(fileperms($dir) & 0777) : 'n/a';
        $owner = file_exists($dir) ? fileowner($dir) : -1;
        $group = file_exists($dir) ? filegroup($dir) : -1;
        $procUid = function_exists('posix_getuid') ? posix_getuid() : -1;
        $procGid = function_exists('posix_getgid') ? posix_getgid() : -1;
        error_log(sprintf(
            '[LockRunner] dir=%s | exists=%s | writable=%s | perms=%s | uid=%d | gid=%d | proc_uid=%d | proc_gid=%d',
            $dir,
            is_dir($dir) ? 'yes' : 'no',
            is_writable($dir) ? 'yes' : 'no',
            $perms,
            $owner,
            $group,
            $procUid,
            $procGid,
        ));
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (@mkdir($dir, 0775, true) || is_dir($dir)) {
            return;
        }
        throw new \RuntimeException("Lock-Verzeichnis fehlt: {$dir}");
    }

    private function requirePositiveMs(int $value, string $field): int
    {
        if ($value > 0) {
            return $value;
        }
        throw new \RuntimeException("Ungueltiger Wert fuer {$field}: {$value}");
    }
}
