<?php

declare(strict_types=1);

use App\Http\Admin\AdminTask;
use App\Http\Admin\AdminTaskHandler;
use App\Http\Admin\AdminTaskRunner;
use App\Http\Admin\Deploy\DeploySwitchTaskHandler;
use App\Http\Admin\Deploy\DeploySwitcher;
use App\Http\Admin\Deploy\PreparedDeployState;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use PHPUnit\Framework\TestCase;

final class AdminDeployTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/admin-deploy-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    // ── PreparedDeployState ──────────────────────────────────────────────────

    public function testPreparedDeployStateRoundtrip(): void
    {
        $file = $this->dir . '/prepared.ini';
        file_put_contents($file, "[prepared]\ntree=b\nvendor=a\n");

        $state = PreparedDeployState::fromFile($file);

        $this->assertSame("[state]\ntree=b\nvendor=a\n", $state->toIni());
    }

    public function testPreparedDeployStateRejectsInvalidSlot(): void
    {
        $file = $this->dir . '/bad.ini';
        file_put_contents($file, "[prepared]\ntree=c\nvendor=a\n");

        $this->expectException(RuntimeException::class);
        PreparedDeployState::fromFile($file);
    }

    public function testPreparedDeployStateMissingFileThrows(): void
    {
        $this->expectException(RuntimeException::class);
        PreparedDeployState::fromFile($this->dir . '/missing.ini');
    }

    // ── DeploySwitcher ───────────────────────────────────────────────────────

    public function testDeploySwitcherWritesStateFile(): void
    {
        $stateFile = $this->dir . '/.deploy-state.ini';
        $writer = new RuntimeAtomicWriter();
        $lockRunner = new RuntimeLockRunner($this->dir);
        $switcher = new DeploySwitcher($writer, $lockRunner, $this->dir);

        $file = $this->dir . '/prepared.ini';
        file_put_contents($file, "[prepared]\ntree=b\nvendor=a\n");
        $state = PreparedDeployState::fromFile($file);

        $switcher->switchTo($state);

        $this->assertFileExists($stateFile);
        $this->assertSame("[state]\ntree=b\nvendor=a\n", file_get_contents($stateFile));
    }

    // ── AdminTask ────────────────────────────────────────────────────────────

    public function testAdminTaskParsesFile(): void
    {
        $file = $this->writeTempIni("[task]\ntype=deploy_switch\nprepared_state=var/admin/deploy-prepared/ref.ini\n");

        $task = AdminTask::fromFile($file);

        $this->assertSame('deploy_switch', $task->type());
        $this->assertSame('var/admin/deploy-prepared/ref.ini', $task->get('prepared_state'));
    }

    public function testAdminTaskRejectsMissingType(): void
    {
        $file = $this->writeTempIni("[task]\n");
        $this->expectException(RuntimeException::class);
        AdminTask::fromFile($file);
    }

    // ── DeploySwitchTaskHandler ──────────────────────────────────────────────

    public function testDeploySwitchTaskHandlerCanHandle(): void
    {
        $handler = $this->buildDeploySwitchHandler();

        $this->assertTrue($handler->canHandle('deploy_switch'));
        $this->assertFalse($handler->canHandle('cv_token_reset'));
    }

    public function testDeploySwitchTaskHandlerPerformsSwitch(): void
    {
        $prepDir = $this->dir . '/var/admin/deploy-prepared';
        mkdir($prepDir, 0775, true);
        file_put_contents($prepDir . '/ref.ini', "[prepared]\ntree=b\nvendor=a\n");

        $taskFile = $this->writeTempIni("[task]\ntype=deploy_switch\nprepared_state=var/admin/deploy-prepared/ref.ini\n");
        $task = AdminTask::fromFile($taskFile);

        $this->buildDeploySwitchHandler()->handle($task, $this->dir);

        $this->assertSame("[state]\ntree=b\nvendor=a\n", file_get_contents($this->dir . '/.deploy-state.ini'));
    }

    // ── AdminTaskRunner ──────────────────────────────────────────────────────

    public function testAdminTaskRunnerIdleOnNoTasks(): void
    {
        $runner = new AdminTaskRunner([], $this->dir);
        $this->assertSame(0, $runner->runPending());
    }

    public function testAdminTaskRunnerProcessesAndDeletesTask(): void
    {
        $taskDir = $this->dir . '/var/admin/tasks';
        $prepDir = $this->dir . '/var/admin/deploy-prepared';
        mkdir($taskDir, 0775, true);
        mkdir($prepDir, 0775, true);
        file_put_contents($prepDir . '/ref.ini', "[prepared]\ntree=b\nvendor=a\n");
        $taskFile = $taskDir . '/20260505T000000Z-deploy-switch.ini';
        file_put_contents($taskFile, "[task]\ntype=deploy_switch\nprepared_state=var/admin/deploy-prepared/ref.ini\n");

        $runner = new AdminTaskRunner([$this->buildDeploySwitchHandler()], $this->dir);
        $count = $runner->runPending();

        $this->assertSame(1, $count);
        $this->assertFileDoesNotExist($taskFile);
        $this->assertFileExists($this->dir . '/.deploy-state.ini');
    }

    public function testAdminTaskRunnerThrowsForUnknownType(): void
    {
        $taskDir = $this->dir . '/var/admin/tasks';
        mkdir($taskDir, 0775, true);
        file_put_contents($taskDir . '/task.ini', "[task]\ntype=unknown_type\n");

        $runner = new AdminTaskRunner([], $this->dir);

        $this->expectException(RuntimeException::class);
        $runner->runPending();
    }

    // ── Hilfsmethoden ───────────────────────────────────────────────────────

    private function buildDeploySwitchHandler(): DeploySwitchTaskHandler
    {
        $writer = new RuntimeAtomicWriter();
        $lockRunner = new RuntimeLockRunner($this->dir);
        $switcher = new DeploySwitcher($writer, $lockRunner, $this->dir);
        return new DeploySwitchTaskHandler($switcher);
    }

    private function writeTempIni(string $content): string
    {
        $file = $this->dir . '/task-' . bin2hex(random_bytes(4)) . '.ini';
        file_put_contents($file, $content);
        return $file;
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
