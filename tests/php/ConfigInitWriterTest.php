<?php

declare(strict_types=1);

namespace App\Tests;

use App\Cli\Config\ConfigInitWriter;
use PHPUnit\Framework\TestCase;

final class ConfigInitWriterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->createRoot();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testWritesGroupedMissingVarsWithMetaComments(): void
    {
        $writer = new ConfigInitWriter($this->root);

        $target = $writer->write('preview', [
            'sftp' => [
                'SFTP_HOST' => ['desc' => 'SFTP-Host', 'notes' => null, 'example' => 'sftp.example.invalid'],
                'SFTP_USER' => ['desc' => null, 'notes' => null, 'example' => null],
            ],
        ]);

        self::assertSame($this->root . '/.local/preview.yaml', $target);
        $content = file_get_contents($target);
        self::assertStringContainsString('sftp:', $content);
        self::assertStringContainsString('  # SFTP-Host', $content);
        self::assertStringContainsString('  # Beispiel: sftp.example.invalid', $content);
        self::assertStringContainsString('  SFTP_HOST: ""', $content);
        self::assertStringContainsString('  SFTP_USER: ""', $content);
    }

    public function testFailsWhenTargetAlreadyExists(): void
    {
        $writer = new ConfigInitWriter($this->root);
        mkdir($this->root . '/.local', 0775, true);
        file_put_contents($writer->targetPath('preview'), "sftp:\n  SFTP_HOST: existing\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Config-Datei existiert bereits');

        $writer->write('preview', ['sftp' => ['SFTP_HOST' => ['desc' => null, 'notes' => null, 'example' => null]]]);
    }

    private function createRoot(): string
    {
        $suffix = '/config-init-writer-' . bin2hex(random_bytes(6));
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . $suffix;
        if (!@mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('Konnte Test-Verzeichnis nicht anlegen.');
        }
        return $root;
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
