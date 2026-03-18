<?php

declare(strict_types=1);

use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\RateLimiter;
use App\Http\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ratelimit-test-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testAllowsWithinWindow(): void
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->tempDir);
        $writer = new RuntimeAtomicWriter();
        $limiter = new RateLimiter($storage, $lockRunner, $writer, $this->tempDir);

        $this->assertTrue($limiter->allow('key', 2, 60));
        $this->assertTrue($limiter->allow('key', 2, 60));
        $this->assertFalse($limiter->allow('key', 2, 60));
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
