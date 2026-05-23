<?php

declare(strict_types=1);

use App\Http\ConfigCompiled;
use App\Http\Cv\CvStorage;
use App\Http\Mail\MailService;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\TokenRotationService;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;
use App\Http\Task\Deploy\DeployState;
use App\Http\Task\Deploy\DeploySwitchTaskHandler;
use App\Http\Task\Deploy\DeploySwitcher;
use App\Http\Task\Deploy\PreparedDeployState;
use App\Http\Task\Task;
use App\Http\Task\Token\CvTokenRotationTaskHandler;
use App\Http\Task\TaskRunner;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TaskDeployTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/task-deploy-test-' . bin2hex(random_bytes(6));
        foreach (['/var/config', '/var/cache/html', '/var/state/tokens', '/var/state/locks'] as $sub) {
            mkdir($this->dir . $sub, 0775, true);
        }
        $this->writeConfig();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    // ── PreparedDeployState ──────────────────────────────────────────────────

    public function testPreparedDeployStateRoundtrip(): void
    {
        $state = PreparedDeployState::fromParams('b', 'a');

        $this->assertSame("[state]\napp=b\nvendor=a\n", $state->toIni());
    }

    public function testPreparedDeployStateRejectsInvalidSlot(): void
    {
        $this->expectException(RuntimeException::class);
        PreparedDeployState::fromParams('c', 'a');
    }

    public function testDeployStateResolvesRunMarkerPaths(): void
    {
        $state = DeployState::fromParams('run-42', 'b', 'a');

        $this->assertSame('b', $state->appLabel());
        $this->assertSame('a', $state->vendorLabel());
        $this->assertSame('app-b/.deploy-run', $state->appRunMarkerPath());
        $this->assertSame(
            "# deploy-slot: b\n"
            . "RewriteEngine On\n"
            . "RewriteCond %{DOCUMENT_ROOT}/app-b/public/%{REQUEST_URI} -f\n"
            . "RewriteRule ^(.*)$ /app-b/public/\$1 [L]\n"
            . "RewriteRule ^ /app-b/public/index.php [L,QSA]\n",
            $state->toHtaccess(),
        );
    }

    public function testDeployStateRejectsMissingDeployId(): void
    {
        $this->expectException(RuntimeException::class);
        DeployState::fromParams('', 'b', 'a');
    }

    public function testDeployStateRejectsInvalidSlotLabel(): void
    {
        $this->expectException(RuntimeException::class);
        DeployState::fromParams('run-42', 'x', 'a');
    }

    public function testDeployStateValidatesPreparedSlots(): void
    {
        $this->writeVendorSlot('a');
        $this->writeAppMarker('b', 'run-42');
        $state = DeployState::fromParams('run-42', 'b', 'a');

        $state->validatePreparedSlots($this->dir);

        $this->assertSame('run-42', $state->deployId());
    }

    // ── DeploySwitcher ───────────────────────────────────────────────────────

    public function testDeploySwitcherWritesHtaccess(): void
    {
        $switcher = new DeploySwitcher(new RuntimeAtomicWriter(), new RuntimeLockRunner($this->dir), $this->dir);

        $switcher->switchTo(DeployState::fromParams('42', 'b', 'a'));

        $this->assertFileExists($this->dir . '/.htaccess');
    }

    // ── Task ─────────────────────────────────────────────────────────────────

    public function testTaskParsesFile(): void
    {
        $file = $this->writeTempIni("[task]\ntype=deploy_switch\napp=b\nvendor=a\nrun_id=42\n");

        $task = Task::fromFile($file);

        $this->assertSame('deploy_switch', $task->type());
        $this->assertSame('b', $task->get('app'));
        $this->assertSame('a', $task->get('vendor'));
        $this->assertSame('42', $task->get('run_id'));
    }

    public function testTaskRejectsMissingType(): void
    {
        $file = $this->writeTempIni("[task]\n");
        $this->expectException(RuntimeException::class);
        Task::fromFile($file);
    }

    public function testTaskParsesTokenRotationIniFormat(): void
    {
        $ini = "[task]\ntype = cv_token_rotation\nprofile = default\ncount = 1\n\n";
        $file = $this->writeTempIni($ini);

        $task = Task::fromFile($file);

        $this->assertSame('cv_token_rotation', $task->type());
        $this->assertSame('default', $task->get('profile'));
        $this->assertSame('1', $task->get('count'));
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
        $this->writeVendorSlot('a');
        $this->writeAppMarker('b', '42');
        $ini = "[task]\ntype=deploy_switch\napp=b\nvendor=a\nrun_id=42\n";
        $task = Task::fromFile($this->writeTempIni($ini));

        $result = $this->buildDeploySwitchHandler()->handle($task, $this->dir);

        $this->assertTrue($result->success);
        $this->assertFileExists($this->dir . '/.htaccess');
    }

    public function testDeploySwitchTaskHandlerRejectsLegacyAppMarkerPath(): void
    {
        $this->writeVendorSlot('a');
        $this->writeLegacyAppMarker('b', '42');
        $ini = "[task]\ntype=deploy_switch\napp=b\nvendor=a\nrun_id=42\n";
        $task = Task::fromFile($this->writeTempIni($ini));

        $this->expectException(RuntimeException::class);
        $this->buildDeploySwitchHandler()->handle($task, $this->dir);
    }

    public function testDeploySwitchTaskHandlerRejectsRunIdMismatch(): void
    {
        $this->writeVendorSlot('a');
        $this->writeAppMarker('b', '99');
        $ini = "[task]\ntype=deploy_switch\napp=b\nvendor=a\nrun_id=42\n";
        $task = Task::fromFile($this->writeTempIni($ini));

        $this->expectException(RuntimeException::class);
        $this->buildDeploySwitchHandler()->handle($task, $this->dir);
    }

    public function testDeploySwitchTaskHandlerRejectsMissingRunId(): void
    {
        $task = Task::fromFile($this->writeTempIni("[task]\ntype=deploy_switch\napp=b\nvendor=a\n"));

        $this->expectException(RuntimeException::class);
        $this->buildDeploySwitchHandler()->handle($task, $this->dir);
    }

    public function testDeploySwitchTaskHandlerRejectsMissingVendorMeta(): void
    {
        $this->writeAppMarker('b', '42');
        $task = Task::fromFile($this->writeTempIni("[task]\ntype=deploy_switch\napp=b\nvendor=a\nrun_id=42\n"));

        $this->expectException(RuntimeException::class);
        $this->buildDeploySwitchHandler()->handle($task, $this->dir);
    }

    // ── TaskRunner ───────────────────────────────────────────────────────────

    public function testTaskRunnerIdleOnNoTasks(): void
    {
        $runner = new TaskRunner([], $this->dir, $this->buildMailService(), new NullLogger(), new RuntimeAtomicWriter());
        $this->assertSame(0, $runner->runPending());
    }

    public function testTaskRunnerProcessesAndDeletesDeploySwitchTask(): void
    {
        $this->writeVendorSlot('a');
        $this->writeAppMarker('b', '42');
        $taskDir = $this->dir . '/var/tasks';
        mkdir($taskDir, 0775, true);
        $taskFile = $taskDir . '/20260505T000000Z-deploy-switch.ini';
        file_put_contents($taskFile, "[task]\ntype=deploy_switch\napp=b\nvendor=a\nrun_id=42\n");

        [$count] = $this->runRunnerCapturingOutput(
            new TaskRunner([$this->buildDeploySwitchHandler()], $this->dir, $this->buildMailService(), new NullLogger(), new RuntimeAtomicWriter()),
        );

        $this->assertSame(1, $count);
        $this->assertFileDoesNotExist($taskFile);
        $this->assertFileExists($this->dir . '/.htaccess');
    }

    public function testTaskRunnerProcessesAndDeletesTokenRotationTask(): void
    {
        $profile = 'default';
        file_put_contents($this->dir . '/var/cache/html/cv-private-' . $profile . '.html', '<html/>');
        $taskDir = $this->dir . '/var/tasks';
        mkdir($taskDir, 0775, true);
        $taskFile = $taskDir . '/20260505T000000Z-cv-token-rotation.ini';
        file_put_contents($taskFile, "[task]\ntype=cv_token_rotation\nprofile={$profile}\ncount=1\n");

        [$count] = $this->runRunnerCapturingOutput(
            new TaskRunner([$this->buildTokenRotationHandler()], $this->dir, $this->buildMailService(), new NullLogger(), new RuntimeAtomicWriter()),
        );

        $this->assertSame(1, $count);
        $this->assertFileDoesNotExist($taskFile);
        $this->assertFileExists($this->dir . '/var/state/tokens/' . $profile . '.txt');
    }

    public function testTaskRunnerSendsErrorMailForUnknownType(): void
    {
        $taskDir = $this->dir . '/var/tasks';
        mkdir($taskDir, 0775, true);
        $taskFile = $taskDir . '/unknown.ini';
        file_put_contents($taskFile, "[task]\ntype=unknown_type\n");

        [$count, $mailOutput] = $this->runRunnerCapturingOutput(
            new TaskRunner([], $this->dir, $this->buildMailService(), new NullLogger(), new RuntimeAtomicWriter()),
        );

        $this->assertSame(1, $count);
        $this->assertFileDoesNotExist($taskFile);
        $this->assertStringContainsString('fehlgeschlagen', $mailOutput);
    }

    public function testTaskRunnerDeletesTaskAndLogsWhenMailFails(): void
    {
        $this->writeInvalidMailConfig();
        $taskDir = $this->dir . '/var/tasks';
        mkdir($taskDir, 0775, true);
        $taskFile = $taskDir . '/unknown.ini';
        file_put_contents($taskFile, "[task]\ntype=unknown_type\n");

        $runner = new TaskRunner([], $this->dir, new MailService(new ConfigCompiled($this->dir)), new NullLogger(), new RuntimeAtomicWriter());
        [$count] = $this->runRunnerCapturingOutput($runner);

        $this->assertSame(1, $count);
        $this->assertFileDoesNotExist($taskFile);
    }

    // ── Hilfsmethoden ───────────────────────────────────────────────────────

    private function buildDeploySwitchHandler(): DeploySwitchTaskHandler
    {
        $switcher = new DeploySwitcher(new RuntimeAtomicWriter(), new RuntimeLockRunner($this->dir), $this->dir);
        return new DeploySwitchTaskHandler($switcher);
    }

    private function buildTokenRotationHandler(): CvTokenRotationTaskHandler
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($this->dir . '/var/state/locks');
        $writer = new RuntimeAtomicWriter();
        $cvStorage = new CvStorage($storage, $this->dir . '/var/cache/html');
        $tokenService = new TokenService($storage, $lockRunner, $writer, $this->dir . '/var/state/tokens');
        return new CvTokenRotationTaskHandler(new TokenRotationService($cvStorage, $tokenService));
    }

    private function buildMailService(): MailService
    {
        return new MailService(new ConfigCompiled($this->dir));
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runRunnerCapturingOutput(TaskRunner $runner): array
    {
        $previousLog = (string) ini_get('error_log');
        ini_set('error_log', $this->dir . '/task-error.log');
        ob_start();
        $bufferLevel = ob_get_level();

        try {
            $count = $runner->runPending();
            $output = (string) ob_get_clean();
            return [$count, $output];
        } finally {
            if (ob_get_level() >= $bufferLevel) {
                ob_end_clean();
            }
            ini_set('error_log', $previousLog);
        }
    }

    private function writeVendorSlot(string $vendor): void
    {
        $dir = $this->dir . "/vendor-{$vendor}";
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/.meta', "[vendor]\nchecksum=checksum-1\n");
    }

    private function writeAppMarker(string $app, string $runId): void
    {
        $dir = $this->dir . "/app-{$app}";
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/.deploy-run', $runId);
    }

    private function writeLegacyAppMarker(string $app, string $runId): void
    {
        $dir = $this->dir . "/{$app}";
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/.deploy-run', $runId);
    }

    private function writeTempIni(string $content): string
    {
        $file = $this->dir . '/task-' . bin2hex(random_bytes(4)) . '.ini';
        file_put_contents($file, $content);
        return $file;
    }

    private function writeConfig(): void
    {
        $this->writeConfigPayload([
            'MAIL_STDOUT' => '1',
            'SMTP_FROM_NAME' => 'Test',
            'MAIL_TO_EMAIL' => 'a@example.invalid',
        ]);
    }

    private function writeInvalidMailConfig(): void
    {
        $this->writeConfigPayload([
            'MAIL_STDOUT' => '1',
            'SMTP_FROM_NAME' => 'Test',
            'MAIL_TO_EMAIL' => 'kein-gueltiges-email',
        ]);
    }

    private function writeConfigPayload(array $values): void
    {
        $payload = ['pipeline_phase' => ['pipeline' => 'dev', 'phase' => 'runtime'], 'values' => $values];
        file_put_contents($this->dir . '/var/config/config.php', '<?php return ' . var_export($payload, true) . ';');
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
