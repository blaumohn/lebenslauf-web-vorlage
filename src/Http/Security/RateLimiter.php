<?php

namespace App\Http\Security;

use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Storage\FileStorage;
use Symfony\Component\Filesystem\Path;

final class RateLimiter
{
    private FileStorage $storage;
    private RuntimeLockRunner $lockRunner;
    private RuntimeAtomicWriter $writer;
    private string $dir;

    public function __construct(
        FileStorage $storage,
        RuntimeLockRunner $lockRunner,
        RuntimeAtomicWriter $writer,
        string $dir
    ) {
        $this->storage = $storage;
        $this->lockRunner = $lockRunner;
        $this->writer = $writer;
        $this->dir = $dir;
        $this->storage->ensureDir($this->dir);
    }

    public function allow(string $key, int $max, int $windowSeconds): bool
    {
        $safeKey = $this->safeKey($key);
        $path = Path::join($this->dir, $safeKey . '.json');
        $now = time();
        $locked = fn() => $this->allowLocked($path, $max, $windowSeconds, $now);
        return (bool) $this->lockRunner->runWithLock('ratelimit_' . $safeKey, $locked);
    }

    private function allowLocked(string $path, int $max, int $windowSeconds, int $now): bool
    {
        $data = $this->storage->readJson($path) ?? ['timestamps' => []];
        $timestamps = array_filter($data['timestamps'] ?? [], fn ($ts) => is_int($ts));

        $filtered = [];
        foreach ($timestamps as $timestamp) {
            if ($timestamp >= ($now - $windowSeconds)) {
                $filtered[] = $timestamp;
            }
        }

        if (count($filtered) >= $max) {
            return false;
        }

        $filtered[] = $now;
        $this->writeJson($path, ['timestamps' => array_values($filtered)]);
        return true;
    }

    private function writeJson(string $path, array $data): void
    {
        $encoded = json_encode($data);
        if (!is_string($encoded)) {
            throw new \RuntimeException("Rate-Limit-Datei konnte nicht serialisiert werden: {$path}");
        }
        $this->writer->writeText($path, $encoded . "\n");
    }

    private function safeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    }
}
