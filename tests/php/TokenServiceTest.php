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

        $tokens = $service->add('DEFAULT', 2, null);

        $this->assertTrue($service->verify('DEFAULT', $tokens[0]));
        $this->assertTrue($service->verify('DEFAULT', $tokens[1]));
        $this->assertFalse($service->verify('DEFAULT', 'gamma'));
    }

    public function testAddIsAdditiveAndDoesNotRemoveExistingTokens(): void
    {
        $service = $this->service();

        $first = $service->add('DEFAULT', 1, null);
        $second = $service->add('DEFAULT', 1, null);

        $this->assertTrue($service->verify('DEFAULT', $first[0]));
        $this->assertTrue($service->verify('DEFAULT', $second[0]));
        $this->assertCount(2, $service->list('DEFAULT'));
    }

    public function testAddRejectsInvalidProfileName(): void
    {
        $service = $this->service();

        $this->expectException(\InvalidArgumentException::class);
        $service->add('../traversal', 1, null);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $service = $this->service();

        $tokens = $service->add('DEFAULT', 1, time() - 10);

        $this->assertFalse($service->verify('DEFAULT', $tokens[0]));
        $this->assertNull($service->findProfileForToken($tokens[0]));
    }

    public function testTokenWithoutExpiryStaysValid(): void
    {
        $service = $this->service();

        $tokens = $service->add('DEFAULT', 1, null);

        $this->assertTrue($service->verify('DEFAULT', $tokens[0]));
    }

    public function testFindProfileForToken(): void
    {
        $service = $this->service();

        $tokenA = $service->add('A', 1, null)[0];
        $tokenB = $service->add('B', 1, null)[0];

        $this->assertSame('B', $service->findProfileForToken($tokenB));
        $this->assertSame('A', $service->findProfileForToken($tokenA));
        $this->assertNull($service->findProfileForToken('unknown'));
    }

    public function testListReturnsLabelAndTimestamps(): void
    {
        $service = $this->service();
        $service->add('DEFAULT', 1, 1234567890, 'firma-x');

        $entries = $service->list('DEFAULT');

        $this->assertCount(1, $entries);
        $this->assertSame('firma-x', $entries[0]['label']);
        $this->assertSame(1234567890, $entries[0]['expires_at']);
    }

    public function testRevokeByHashPrefixRemovesOnlyMatchingEntry(): void
    {
        $service = $this->service();
        $kept = $service->add('DEFAULT', 1, null, 'kept')[0];
        $removed = $service->add('DEFAULT', 1, null, 'removed')[0];
        $prefix = substr(hash('sha256', $removed), 0, 12);

        $count = $service->revoke('DEFAULT', $prefix);

        $this->assertSame(1, $count);
        $this->assertTrue($service->verify('DEFAULT', $kept));
        $this->assertFalse($service->verify('DEFAULT', $removed));
    }

    public function testRevokeByLabelRemovesMatchingEntries(): void
    {
        $service = $this->service();
        $service->add('DEFAULT', 1, null, 'firma-x');
        $kept = $service->add('DEFAULT', 1, null, 'firma-y')[0];

        $count = $service->revoke('DEFAULT', 'firma-x');

        $this->assertSame(1, $count);
        $this->assertTrue($service->verify('DEFAULT', $kept));
    }

    public function testRevokeWithoutMatchReturnsZero(): void
    {
        $service = $this->service();
        $service->add('DEFAULT', 1, null);

        $this->assertSame(0, $service->revoke('DEFAULT', 'nichtvorhanden'));
    }

    public function testRevokeAllRemovesEverything(): void
    {
        $service = $this->service();
        $tokens = $service->add('DEFAULT', 2, null);

        $service->revokeAll('DEFAULT');

        $this->assertSame([], $service->list('DEFAULT'));
        $this->assertFalse($service->verify('DEFAULT', $tokens[0]));
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
