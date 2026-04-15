<?php

declare(strict_types=1);

use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

final class TokenServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/token-test-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testRotateAndVerify(): void
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->tempDir);
        $writer = new RuntimeAtomicWriter();
        $service = new TokenService($storage, $lockRunner, $writer, $this->tempDir);

        $tokens = ['alpha', 'beta'];
        $service->rotate('DEFAULT', $tokens);

        $this->assertTrue($service->verify('DEFAULT', 'alpha'));
        $this->assertFalse($service->verify('DEFAULT', 'gamma'));
    }

    public function testFindProfileForToken(): void
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->tempDir);
        $writer = new RuntimeAtomicWriter();
        $service = new TokenService($storage, $lockRunner, $writer, $this->tempDir);

        $service->rotate('A', ['token-a']);
        $service->rotate('B', ['token-b']);

        $this->assertSame('B', $service->findProfileForToken('token-b'));
        $this->assertNull($service->findProfileForToken('unknown'));
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
