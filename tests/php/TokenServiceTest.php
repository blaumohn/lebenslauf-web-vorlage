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

    public function testAddAndVerify(): void
    {
        $service = $this->service();

        $tokenA = $service->add('DEFAULT', null);
        $tokenB = $service->add('DEFAULT', null);

        $this->assertTrue($service->verify('DEFAULT', $tokenA));
        $this->assertTrue($service->verify('DEFAULT', $tokenB));
        $this->assertFalse($service->verify('DEFAULT', 'gamma'));
    }

    public function testAddIsAdditiveAndDoesNotRemoveExistingTokens(): void
    {
        $service = $this->service();

        $first = $service->add('DEFAULT', null);
        $second = $service->add('DEFAULT', null);

        $this->assertTrue($service->verify('DEFAULT', $first));
        $this->assertTrue($service->verify('DEFAULT', $second));
        $this->assertCount(2, $service->list('DEFAULT'));
    }

    public function testAddRejectsInvalidProfileName(): void
    {
        $service = $this->service();

        $this->expectException(\InvalidArgumentException::class);
        $service->add('../traversal', null);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $service = $this->service();

        $token = $service->add('DEFAULT', time() - 10);

        $this->assertFalse($service->verify('DEFAULT', $token));
        $this->assertNull($service->findProfileForToken($token));
    }

    public function testIsExpiredDistinguishesExpiredFromActiveAndUnknownTokens(): void
    {
        $service = $this->service();

        $expiredToken = $service->add('DEFAULT', time() - 10);
        $activeToken = $service->add('DEFAULT', null);

        $this->assertTrue($service->isExpired($expiredToken));
        $this->assertFalse($service->isExpired($activeToken));
        $this->assertFalse($service->isExpired('unknown'));
    }

    public function testTokenWithoutExpiryStaysValid(): void
    {
        $service = $this->service();

        $token = $service->add('DEFAULT', null);

        $this->assertTrue($service->verify('DEFAULT', $token));
    }

    public function testFindProfileForToken(): void
    {
        $service = $this->service();

        $tokenA = $service->add('A', null);
        $tokenB = $service->add('B', null);

        $this->assertSame('B', $service->findProfileForToken($tokenB));
        $this->assertSame('A', $service->findProfileForToken($tokenA));
        $this->assertNull($service->findProfileForToken('unknown'));
    }

    public function testListReturnsLabelAndTimestamps(): void
    {
        $service = $this->service();
        $service->add('DEFAULT', 1234567890, 'firma-x');

        $entries = $service->list('DEFAULT');

        $this->assertCount(1, $entries);
        $this->assertSame('firma-x', $entries[0]['label']);
        $this->assertSame(1234567890, $entries[0]['expires_at']);
    }

    public function testRevokeByHashPrefixRemovesOnlyMatchingEntry(): void
    {
        $service = $this->service();
        $kept = $service->add('DEFAULT', null, 'kept');
        $removed = $service->add('DEFAULT', null, 'removed');
        $prefix = substr(hash('sha256', $removed), 0, 12);

        $count = $service->revoke('DEFAULT', $prefix);

        $this->assertSame(1, $count);
        $this->assertTrue($service->verify('DEFAULT', $kept));
        $this->assertFalse($service->verify('DEFAULT', $removed));
    }

    public function testRevokeByLabelRemovesMatchingEntries(): void
    {
        $service = $this->service();
        $firstRemoved = $service->add('DEFAULT', null, 'firma-x');
        $secondRemoved = $service->add('DEFAULT', null, 'firma-x');
        $kept = $service->add('DEFAULT', null, 'firma-y');

        $count = $service->revoke('DEFAULT', 'firma-x');

        $this->assertSame(2, $count);
        $this->assertFalse($service->verify('DEFAULT', $firstRemoved));
        $this->assertFalse($service->verify('DEFAULT', $secondRemoved));
        $this->assertTrue($service->verify('DEFAULT', $kept));
    }

    public function testRevokeWithoutMatchReturnsZero(): void
    {
        $service = $this->service();
        $service->add('DEFAULT', null);

        $this->assertSame(0, $service->revoke('DEFAULT', 'nichtvorhanden'));
    }

    private function service(): TokenService
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->tempDir);
        $writer = new RuntimeAtomicWriter();
        return new TokenService($storage, $lockRunner, $writer, $this->tempDir);
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
