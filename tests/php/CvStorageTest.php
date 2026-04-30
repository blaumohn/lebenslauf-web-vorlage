<?php

declare(strict_types=1);

use App\Http\Cv\CvStorage;
use App\Http\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

final class CvStorageTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/cv-storage-test-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testHasPrivateReturnsTrueWhenFileExists(): void
    {
        $storage = new CvStorage(new FileStorage(), $this->tempDir);
        file_put_contents($this->tempDir . '/cv-private-test.html', '<h1>Test</h1>');

        $this->assertTrue($storage->hasPrivate('test'));
    }

    public function testHasPrivateReturnsFalseWhenFileMissing(): void
    {
        $storage = new CvStorage(new FileStorage(), $this->tempDir);

        $this->assertFalse($storage->hasPrivate('nicht-vorhanden'));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
