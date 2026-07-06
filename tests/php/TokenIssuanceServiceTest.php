<?php

declare(strict_types=1);

use App\Http\SiteHtmlCache;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\CvTokenSubjectResolver;
use App\Http\Security\TokenIssuanceService;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

final class TokenIssuanceServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/issuance-test-' . bin2hex(random_bytes(6));
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
        $service = $this->service();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('keine Seite');

        $service->add('kein-profil', 1, null);
    }

    public function testRejectsInvalidProfileName(): void
    {
        // Profilname besteht die hasPrivate()-Prüfung (glob matcht Leerzeichen
        // literal), muss aber an TokenServices Namensvalidierung scheitern.
        file_put_contents($this->tempDir . '/html/cv-private-invalid profile.de.html', '<h1>Test</h1>');
        $service = $this->service();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Profilname ungültig');

        $service->add('invalid profile', 1, null);
    }

    public function testAddIssuesTokensForExistingProfile(): void
    {
        file_put_contents($this->tempDir . '/html/cv-private-test.de.html', '<h1>Test</h1>');

        $tokens = $this->service()->add('test', 2, null);

        $this->assertCount(2, $tokens);
        $this->assertNotSame($tokens[0], $tokens[1]);
    }

    public function testIssuedTokenIsVerifiable(): void
    {
        file_put_contents($this->tempDir . '/html/cv-private-test.de.html', '<h1>Test</h1>');
        $tokenService = $this->tokenService();

        $tokens = $this->serviceWith($tokenService)->add('test', 1, null);

        $this->assertTrue($tokenService->verify('test', $tokens[0]));
    }

    public function testListAndRevokeDelegateToTokenService(): void
    {
        file_put_contents($this->tempDir . '/html/cv-private-test.de.html', '<h1>Test</h1>');
        $tokenService = $this->tokenService();
        $service = $this->serviceWith($tokenService);
        $tokens = $service->add('test', 2, null, 'firma-x');

        $this->assertCount(2, $service->list('test'));

        $service->revokeAll('test');

        $this->assertSame([], $service->list('test'));
        $this->assertFalse($tokenService->verify('test', $tokens[0]));
    }

    private function service(): TokenIssuanceService
    {
        return $this->serviceWith($this->tokenService());
    }

    private function serviceWith(TokenService $tokenService): TokenIssuanceService
    {
        $htmlCache = new SiteHtmlCache(new FileStorage(), $this->tempDir . '/html');
        return new TokenIssuanceService(new CvTokenSubjectResolver($htmlCache), $tokenService);
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
