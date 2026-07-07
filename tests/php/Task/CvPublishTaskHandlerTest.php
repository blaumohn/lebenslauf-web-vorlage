<?php

declare(strict_types=1);

namespace App\Tests\Task;

use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Task\Cv\CvPublishTaskHandler;
use App\Http\Task\QueuedTask;
use App\Http\Task\QueuedTaskFile;
use PHPUnit\Framework\TestCase;

final class CvPublishTaskHandlerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cv-publish-handler-' . bin2hex(random_bytes(4));
        foreach (['/var/tmp/html-publish', '/var/cache/html'] as $dir) {
            mkdir($this->root . $dir, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testCanHandleCvPublishType(): void
    {
        $handler = $this->makeHandler();
        $this->assertTrue($handler->canHandle('cv_publish'));
        $this->assertFalse($handler->canHandle('cv_token_add'));
    }

    public function testPublishesHtmlFilesToCache(): void
    {
        file_put_contents($this->root . '/var/tmp/html-publish/cv-public.html', '<html>A</html>');
        file_put_contents($this->root . '/var/tmp/html-publish/cv-public.de.html', '<html>B</html>');

        $result = $this->makeHandler()->handle($this->makeTask(), $this->root);

        $this->assertTrue($result->success);
        $this->assertFileExists($this->root . '/var/cache/html/cv-public.html');
        $this->assertFileExists($this->root . '/var/cache/html/cv-public.de.html');
        $this->assertStringEqualsFile($this->root . '/var/cache/html/cv-public.html', '<html>A</html>');
    }

    public function testResultBodyContainsFileCount(): void
    {
        file_put_contents($this->root . '/var/tmp/html-publish/cv-public.html', '<html/>');

        $result = $this->makeHandler()->handle($this->makeTask(), $this->root);

        $this->assertStringContainsString('1', $result->body);
    }

    public function testCleansStagingAfterPublish(): void
    {
        file_put_contents($this->root . '/var/tmp/html-publish/cv-public.html', '<html/>');

        $this->makeHandler()->handle($this->makeTask(), $this->root);

        $this->assertDirectoryDoesNotExist($this->root . '/var/tmp/html-publish');
    }

    public function testFailsWhenStagingIsEmpty(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->makeHandler()->handle($this->makeTask(), $this->root);
    }

    private function makeHandler(): CvPublishTaskHandler
    {
        return new CvPublishTaskHandler(new RuntimeAtomicWriter());
    }

    private function makeTask(): QueuedTask
    {
        $file = $this->root . '/task.ini';
        file_put_contents($file, "[task]\ntype = cv_publish\n");
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
