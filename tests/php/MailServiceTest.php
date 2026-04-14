<?php

declare(strict_types=1);

namespace App\Tests;

use App\Http\ConfigCompiled;
use App\Http\Contact\MailService;
use PHPUnit\Framework\TestCase;

final class MailServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->createRoot();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testRejectsInvalidContactRecipient(): void
    {
        $this->writeConfig([
            'MAIL_STDOUT' => '0',
            'CONTACT_TO_EMAIL' => 'ungueltig',
        ]);
        $service = new MailService(new ConfigCompiled($this->root));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CONTACT_TO_EMAIL muss eine gueltige E-Mail-Adresse sein.');

        $service->send('Max', 'max@example.invalid', 'Nachricht');
    }

    private function writeConfig(array $config): void
    {
        $path = $this->root . '/var/config/config.php';
        $this->ensureDir(dirname($path));
        file_put_contents($path, '<?php return ' . var_export($config, true) . ';');
        $contextPath = $this->root . '/var/config/config.context.php';
        $context = ['pipeline' => 'dev', 'phase' => 'runtime'];
        file_put_contents($contextPath, '<?php return ' . var_export($context, true) . ';');
    }

    private function createRoot(): string
    {
        $suffix = '/mail-service-' . bin2hex(random_bytes(6));
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . $suffix;
        if (!@mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('Konnte Test-Verzeichnis nicht anlegen.');
        }
        return $root;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            $this->removePath($dir . '/' . $item);
        }
        rmdir($dir);
    }

    private function removePath(string $path): void
    {
        if (basename($path) === '.' || basename($path) === '..') {
            return;
        }
        if (is_dir($path)) {
            $this->removeDir($path);
            return;
        }
        unlink($path);
    }
}
