<?php

declare(strict_types=1);

use App\Http\Captcha\CaptchaService;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\RateLimiter;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * Race-nahe Tests für RateLimiter, CaptchaService und TokenService.
 *
 * Strategie: Lock-Key des jeweiligen Dienstes manuell halten,
 * dann den Dienst-Aufruf starten → muss mit Lock-Timeout scheitern.
 * Damit wird sichergestellt, dass jeder Schreibpfad tatsächlich unter
 * dem erwarteten Lock läuft und nicht am Lock vorbeigeht.
 */
final class ConcurrencyTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/concurrency-test-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    // -------------------------------------------------------------------------
    // RateLimiter
    // -------------------------------------------------------------------------

    public function testRateLimiterAllowTimesOutWhenLockBusy(): void
    {
        $lock = $this->acquireLock('ratelimit_ip_race');
        $limiter = $this->buildRateLimiter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lock-Timeout');

        try {
            $limiter->allow('ip.race', 5, 60);
        } finally {
            $lock->release();
        }
    }

    public function testRateLimiterSerializesCallsForSameKey(): void
    {
        $limiter = $this->buildRateLimiter();

        $first = $limiter->allow('serial_key', 2, 60);
        $second = $limiter->allow('serial_key', 2, 60);
        $third = $limiter->allow('serial_key', 2, 60);

        $this->assertTrue($first);
        $this->assertTrue($second);
        $this->assertFalse($third);
    }

    // -------------------------------------------------------------------------
    // CaptchaService
    // -------------------------------------------------------------------------

    public function testCaptchaVerifyTimesOutWhenLockBusy(): void
    {
        $service = $this->buildCaptchaService();
        $challenge = $service->createChallenge('iphash_race');
        $id = $challenge['captcha_id'];

        $lock = $this->acquireLock('captcha_' . $id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lock-Timeout');

        try {
            $service->verify($id, (string) $challenge['solution_text'], 'iphash_race');
        } finally {
            $lock->release();
        }
    }

    public function testCaptchaVerifyUsedAtWrittenUnderLock(): void
    {
        $service = $this->buildCaptchaService();
        $challenge = $service->createChallenge('iphash_write');
        $id = $challenge['captcha_id'];

        $ok = $service->verify($id, (string) $challenge['solution_text'], 'iphash_write');
        $this->assertTrue($ok);

        $after = $service->getChallenge($id);
        $this->assertNull($after, 'Challenge muss nach Verify inaktiv sein.');
    }

    // -------------------------------------------------------------------------
    // TokenService
    // -------------------------------------------------------------------------

    public function testTokenAddTimesOutWhenLockBusy(): void
    {
        $lock = $this->acquireLock('token_race_profile');
        $service = $this->buildTokenService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lock-Timeout');

        try {
            $service->add('race_profile', null);
        } finally {
            $lock->release();
        }
    }

    public function testTokenAddWritesAtomically(): void
    {
        $service = $this->buildTokenService();

        $tokenA = $service->add('write_profile', null);
        $tokenB = $service->add('write_profile', null);

        $this->assertCount(2, $service->list('write_profile'));
        $this->assertTrue($service->verify('write_profile', $tokenA));
        $this->assertTrue($service->verify('write_profile', $tokenB));
        $this->assertFalse($service->verify('write_profile', 'tok-x'));
    }

    public function testTokenReadOperationsNeedNoLock(): void
    {
        $service = $this->buildTokenService();
        $token = $service->add('read_profile', null);

        $lock = $this->acquireLock('token_read_profile');

        try {
            $verified = $service->verify('read_profile', $token);
            $found = $service->findProfileForToken($token);
        } finally {
            $lock->release();
        }

        $this->assertTrue($verified);
        $this->assertSame('read_profile', $found);
    }

    // -------------------------------------------------------------------------
    // Hilfsmethoden
    // -------------------------------------------------------------------------

    private function acquireLock(string $key): object
    {
        $normalizedKey = (string) preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        $factory = new LockFactory(new FlockStore($this->tempDir));
        $lock = $factory->createLock($normalizedKey);
        $this->assertTrue($lock->acquire(false), "Vorbedingung: Lock '{$key}' muss frei sein.");
        return $lock;
    }

    private function buildRateLimiter(): RateLimiter
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->tempDir, 80, 10);
        $writer = new RuntimeAtomicWriter();
        return new RateLimiter($storage, $lockRunner, $writer, $this->tempDir);
    }

    private function buildCaptchaService(): CaptchaService
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->tempDir, 80, 10);
        $writer = new RuntimeAtomicWriter();
        return new CaptchaService($storage, $lockRunner, $writer, $this->tempDir, 60);
    }

    private function buildTokenService(): TokenService
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->tempDir, 80, 10);
        $writer = new RuntimeAtomicWriter();
        return new TokenService($storage, $lockRunner, $writer, $this->tempDir);
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
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
