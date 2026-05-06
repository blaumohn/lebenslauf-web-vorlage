<?php

declare(strict_types=1);

use App\Http\Captcha\CaptchaService;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

final class CaptchaServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/captcha-test-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testChallengeLifecycle(): void
    {
        $service = $this->buildService();

        $challenge = $service->createChallenge('iphash');
        $this->assertNotEmpty($challenge['captcha_id']);

        $loaded = $service->getChallenge($challenge['captcha_id']);
        $this->assertNotNull($loaded);

        $ok = $service->verify($challenge['captcha_id'], (string) $challenge['solution_text'], 'iphash');
        $this->assertTrue($ok);

        $again = $service->getChallenge($challenge['captcha_id']);
        $this->assertNull($again);
    }

    public function testCaptchaIdContainsTimestamp(): void
    {
        $service = $this->buildService();
        $challenge = $service->createChallenge('iphash');
        $id = $challenge['captcha_id'];

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}_\d+$/', $id);
    }

    public function testParseIdTimestampValid(): void
    {
        $service = $this->buildService();
        $ts = time();
        $id = bin2hex(random_bytes(16)) . '_' . $ts;

        $this->assertSame($ts, $service->parseIdTimestamp($id));
    }

    public function testParseIdTimestampInvalid(): void
    {
        $service = $this->buildService();

        $this->assertNull($service->parseIdTimestamp('nounderscore'));
        $this->assertNull($service->parseIdTimestamp('abc_notanumber'));
    }

    public function testRenderPng(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for CAPTCHA rendering.');
        }

        $service = $this->buildService();

        $png = $service->renderPng('ABC123');
        $this->assertNotEmpty($png);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));

        $size = getimagesizefromstring($png);
        $this->assertIsArray($size);
        $this->assertGreaterThan(0, $size[0]);
        $this->assertGreaterThan(0, $size[1]);
    }

    private function buildService(): CaptchaService
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->tempDir);
        $writer = new RuntimeAtomicWriter();
        return new CaptchaService($storage, $lockRunner, $writer, $this->tempDir, 60);
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
