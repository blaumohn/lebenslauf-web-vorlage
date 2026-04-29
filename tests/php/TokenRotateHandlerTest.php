<?php

declare(strict_types=1);

use App\Cli\Token\TokenRotateHandler;
use App\Http\Cv\CvStorage;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

final class TokenRotateHandlerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/handler-test-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir . '/html', 0775, true);
        mkdir($this->tempDir . '/tokens', 0775, true);
        mkdir($this->tempDir . '/locks', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testRejectsProfileWithoutPage(): void
    {
        $handler = $this->handler();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('keine Lebenslauf-Seite');

        $handler->rotate('kein-profil', 1);
    }

    public function testRejectsInvalidProfileName(): void
    {
        $handler = $this->handler();

        $this->expectException(\InvalidArgumentException::class);

        $handler->rotate('../traversal', 1);
    }

    public function testRotatesTokensForExistingProfile(): void
    {
        file_put_contents($this->tempDir . '/html/cv-private-test.html', '<h1>Test</h1>');

        $tokens = $this->handler()->rotate('test', 2);

        $this->assertCount(2, $tokens);
        $this->assertNotSame($tokens[0], $tokens[1]);
    }

    public function testRotatedTokenIsVerifiable(): void
    {
        file_put_contents($this->tempDir . '/html/cv-private-test.html', '<h1>Test</h1>');
        $tokenService = $this->tokenService();

        $tokens = $this->handlerWith($tokenService)->rotate('test', 1);

        $this->assertTrue($tokenService->verify('test', $tokens[0]));
    }

    private function handler(): TokenRotateHandler
    {
        return $this->handlerWith($this->tokenService());
    }

    private function handlerWith(TokenService $tokenService): TokenRotateHandler
    {
        $cvStorage = new CvStorage(new FileStorage(), $this->tempDir . '/html');
        return new TokenRotateHandler($cvStorage, $tokenService);
    }

    private function tokenService(): TokenService
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->tempDir . '/locks');
        $writer = new RuntimeAtomicWriter();
        return new TokenService($storage, $lockRunner, $writer, $this->tempDir . '/tokens');
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
