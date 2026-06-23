<?php

declare(strict_types=1);

namespace App\Tests;

use App\Cli\Setup\SampleContentCopier;
use PHPUnit\Framework\TestCase;

final class SampleContentCopierTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->createRoot();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testCopiesSampleContentToFixedTarget(): void
    {
        $copier = new SampleContentCopier($this->root);

        $target = $copier->copy();

        self::assertSame($this->root . '/.local/content', $target);
        self::assertFileExists($target . '/lebenslauf/daten-demo.yaml');
        self::assertFileExists($target . '/home/home.yaml');
    }

    public function testFailsWhenTargetAlreadyExists(): void
    {
        $copier = new SampleContentCopier($this->root);
        mkdir($copier->targetPath(), 0775, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Sample-Ziel existiert bereits');

        $copier->copy();
    }

    private function seedFixtures(): void
    {
        $fixtures = [
            'lebenslauf/daten-demo.yaml' => "titel: Beispiel\n",
            'home/home.yaml'             => "titel: Startseite\n",
        ];
        foreach ($fixtures as $rel => $content) {
            $path = $this->root . '/src/resources/fixtures/' . $rel;
            $this->ensureDir(dirname($path));
            file_put_contents($path, $content);
        }
    }

    private function createRoot(): string
    {
        $suffix = '/sample-content-' . bin2hex(random_bytes(6));
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . $suffix;
        if (!@mkdir($root, 0775, true) && !is_dir($root)) {
            $root = dirname(__DIR__, 2) . '/var/tmp' . $suffix;
            if (!@mkdir($root, 0775, true) && !is_dir($root)) {
                throw new \RuntimeException('Konnte Test-Verzeichnis nicht anlegen.');
            }
        }
        return $root;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
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
            if (is_dir($path)) {
                $this->removeDir($path);
                continue;
            }
            unlink($path);
        }
        rmdir($dir);
    }
}
