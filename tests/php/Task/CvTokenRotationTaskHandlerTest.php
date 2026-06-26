<?php

declare(strict_types=1);

namespace App\Tests\Task;

use App\Http\SiteHtmlCache;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\TokenRotationService;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;
use App\Http\Task\QueuedTask;
use App\Http\Task\QueuedTaskFile;
use App\Http\Task\Token\CvTokenRotationTaskHandler;
use PHPUnit\Framework\TestCase;

final class CvTokenRotationTaskHandlerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cv-token-handler-' . bin2hex(random_bytes(4));
        foreach (['/var/cache/html', '/var/state/tokens', '/var/state/locks'] as $dir) {
            mkdir($this->root . $dir, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testCanHandleCvTokenRotationType(): void
    {
        $handler = $this->makeHandler();
        $this->assertTrue($handler->canHandle('cv_token_rotation'));
        $this->assertFalse($handler->canHandle('deploy_switch'));
    }

    public function testRejectsUnknownProfile(): void
    {
        $handler = $this->makeHandler();
        $task = $this->makeTask('missing', 1);

        $this->expectException(\InvalidArgumentException::class);
        $handler->handle($task, $this->root);
    }

    public function testRotatesTokensForKnownProfile(): void
    {
        file_put_contents($this->root . '/var/cache/html/cv-private-default.de.html', '<html/>');
        $handler = $this->makeHandler();
        $task = $this->makeTask('default', 2);

        $result = $handler->handle($task, $this->root);

        $this->assertTrue($result->success);
        $tokenFile = $this->root . '/var/state/tokens/default.txt';
        $this->assertFileExists($tokenFile);
        $hashes = array_filter(explode("\n", trim((string) file_get_contents($tokenFile))));
        $this->assertCount(2, $hashes);
    }

    private function makeHandler(): CvTokenRotationTaskHandler
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->root . '/var/state/locks');
        $writer = new RuntimeAtomicWriter();
        $htmlCache = new SiteHtmlCache($storage, $this->root . '/var/cache/html');
        $tokenService = new TokenService($storage, $lockRunner, $writer, $this->root . '/var/state/tokens');
        return new CvTokenRotationTaskHandler(new TokenRotationService($htmlCache, $tokenService));
    }

    private function makeTask(string $profile, int $count): QueuedTask
    {
        $file = $this->root . '/task.ini';
        file_put_contents($file, "[task]\ntype = cv_token_rotation\nprofile = {$profile}\ncount = {$count}\n");
        return QueuedTaskFile::load($file);
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
