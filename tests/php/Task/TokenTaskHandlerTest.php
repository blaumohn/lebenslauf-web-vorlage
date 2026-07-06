<?php

declare(strict_types=1);

namespace App\Tests\Task;

use App\Http\SiteHtmlCache;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\CvTokenSubjectResolver;
use App\Http\Security\TokenIssuanceService;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;
use App\Http\Task\QueuedTask;
use App\Http\Task\QueuedTaskFile;
use App\Http\Task\Token\TokenTaskHandler;
use PHPUnit\Framework\TestCase;

final class TokenTaskHandlerTest extends TestCase
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

    public function testCanHandleCvTokenTypes(): void
    {
        $handler = $this->makeHandler();
        $this->assertTrue($handler->canHandle('cv_token_add'));
        $this->assertTrue($handler->canHandle('cv_token_list'));
        $this->assertTrue($handler->canHandle('cv_token_revoke'));
        $this->assertFalse($handler->canHandle('deploy_switch'));
    }

    public function testRejectsUnknownProfileOnAdd(): void
    {
        $handler = $this->makeHandler();
        $task = $this->makeTask('cv_token_add', ['profile' => 'missing', 'count' => '1']);

        $this->expectException(\InvalidArgumentException::class);
        $handler->handle($task, $this->root);
    }

    public function testAddsTokensForKnownProfile(): void
    {
        file_put_contents($this->root . '/var/cache/html/cv-private-default.de.html', '<html/>');
        $handler = $this->makeHandler();
        $task = $this->makeTask('cv_token_add', ['profile' => 'default', 'count' => '2']);

        $result = $handler->handle($task, $this->root);

        $this->assertTrue($result->success);
        $tokens = array_filter(explode("\n", $result->body));
        $this->assertCount(2, $tokens);
    }

    public function testListReturnsJsonBody(): void
    {
        file_put_contents($this->root . '/var/cache/html/cv-private-default.de.html', '<html/>');
        $handler = $this->makeHandler();
        $handler->handle($this->makeTask('cv_token_add', ['profile' => 'default', 'count' => '1']), $this->root);

        $result = $handler->handle($this->makeTask('cv_token_list', ['profile' => 'default']), $this->root);

        $this->assertTrue($result->success);
        $entries = json_decode($result->body, true);
        $this->assertCount(1, $entries);
    }

    public function testRevokeAllRemovesTokens(): void
    {
        file_put_contents($this->root . '/var/cache/html/cv-private-default.de.html', '<html/>');
        $handler = $this->makeHandler();
        $handler->handle($this->makeTask('cv_token_add', ['profile' => 'default', 'count' => '2']), $this->root);

        $result = $handler->handle($this->makeTask('cv_token_revoke', ['profile' => 'default', 'identifier' => 'all']), $this->root);

        $this->assertTrue($result->success);
        $list = $handler->handle($this->makeTask('cv_token_list', ['profile' => 'default']), $this->root);
        $this->assertSame([], json_decode($list->body, true));
    }

    private function makeHandler(): TokenTaskHandler
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->root . '/var/state/locks');
        $writer = new RuntimeAtomicWriter();
        $htmlCache = new SiteHtmlCache($storage, $this->root . '/var/cache/html');
        $tokenService = new TokenService($storage, $lockRunner, $writer, $this->root . '/var/state/tokens');
        $issuanceService = new TokenIssuanceService(new CvTokenSubjectResolver($htmlCache), $tokenService);
        return new TokenTaskHandler($issuanceService, 'cv');
    }

    /** @param array<string, string> $fields */
    private function makeTask(string $type, array $fields): QueuedTask
    {
        $file = $this->root . '/task-' . bin2hex(random_bytes(3)) . '.ini';
        $lines = ["[task]", "type = {$type}"];
        foreach ($fields as $key => $value) {
            $lines[] = "{$key} = {$value}";
        }
        file_put_contents($file, implode("\n", $lines) . "\n");
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
