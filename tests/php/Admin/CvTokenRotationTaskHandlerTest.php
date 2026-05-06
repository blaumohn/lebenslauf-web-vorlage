<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Http\Admin\AdminTask;
use App\Http\Admin\Token\CvTokenRotationTaskHandler;
use App\Http\ConfigCompiled;
use App\Http\Cv\CvStorage;
use App\Http\Mail\MailService;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\TokenRotationService;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

final class CvTokenRotationTaskHandlerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cv-token-handler-' . bin2hex(random_bytes(4));
        foreach (['/var/cache/html', '/var/state/tokens', '/var/state/locks', '/var/config'] as $dir) {
            mkdir($this->root . $dir, 0775, true);
        }
        $this->writeConfig();
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
        file_put_contents($this->root . '/var/cache/html/cv-private-default.html', '<html/>');
        $handler = $this->makeHandler();
        $task = $this->makeTask('default', 2);

        $handler->handle($task, $this->root);

        $tokenFile = $this->root . '/var/state/tokens/default.txt';
        $this->assertFileExists($tokenFile);
        $hashes = array_filter(explode("\n", trim(file_get_contents($tokenFile))));
        $this->assertCount(2, $hashes);
    }

    private function makeHandler(): CvTokenRotationTaskHandler
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->root . '/var/state/locks');
        $writer = new RuntimeAtomicWriter();
        $cvStorage = new CvStorage($storage, $this->root . '/var/cache/html');
        $tokenService = new TokenService($storage, $lockRunner, $writer, $this->root . '/var/state/tokens');
        $rotateHandler = new TokenRotationService($cvStorage, $tokenService);
        $mailService = new MailService(new ConfigCompiled($this->root));
        return new CvTokenRotationTaskHandler($rotateHandler, $mailService);
    }

    private function makeTask(string $profile, int $count): AdminTask
    {
        $file = $this->root . '/task.ini';
        file_put_contents($file, "[task]\ntype = cv_token_rotation\nprofile = {$profile}\ncount = {$count}\n");
        return AdminTask::fromFile($file);
    }

    private function writeConfig(): void
    {
        $payload = ['pipeline_phase' => ['pipeline' => 'dev', 'phase' => 'runtime'],
            'values' => ['MAIL_STDOUT' => '1', 'SMTP_FROM_NAME' => 'Test', 'MAIL_TO_EMAIL' => 'a@example.invalid']];
        file_put_contents($this->root . '/var/config/config.php', '<?php return ' . var_export($payload, true) . ';');
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
