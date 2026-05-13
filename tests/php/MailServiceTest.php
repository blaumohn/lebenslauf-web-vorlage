<?php

declare(strict_types=1);

namespace App\Tests;

use App\Http\ConfigCompiled;
use App\Http\Mail\MailMessage;
use App\Http\Mail\MailService;
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

    public function testSendsToStdout(): void
    {
        $this->writeConfig(['MAIL_STDOUT' => '1', 'MAIL_TO_EMAIL' => 'a@example.invalid', 'SMTP_FROM_NAME' => 'TestApp']);
        $service = new MailService(new ConfigCompiled($this->root));
        $message = new MailMessage('Test', 'Betreff', 'Inhalt');

        $result = $service->send($message);

        $this->assertTrue($result);
    }

    public function testRejectsInvalidRecipient(): void
    {
        $this->writeConfig(['MAIL_STDOUT' => '0', 'MAIL_TO_EMAIL' => 'ungueltig']);
        $service = new MailService(new ConfigCompiled($this->root));
        $message = new MailMessage(module: 'Test', title: 'Test', body: 'Text');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MAIL_TO_EMAIL ist keine gültige E-Mail-Adresse.');

        $service->send($message);
    }

    public function testRejectsUnsupportedSmtpEncryption(): void
    {
        $this->writeConfig([
            'MAIL_STDOUT' => '0',
            'MAIL_TO_EMAIL' => 'a@example.invalid',
            'SMTP_HOST' => 'smtp.example.invalid',
            'SMTP_ENCRYPTION' => 'ssl',
            'SMTP_FROM_NAME' => 'TestApp',
        ]);
        $service = new MailService(new ConfigCompiled($this->root));
        $message = new MailMessage(module: 'Test', title: 'Test', body: 'Text');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SMTP_ENCRYPTION erlaubt nur none oder tls.');

        $service->send($message);
    }

    private function writeConfig(array $config): void
    {
        $path = $this->root . '/var/config/config.php';
        $this->ensureDir(dirname($path));
        $payload = [
            'pipeline_phase' => [
                'pipeline' => 'dev',
                'phase' => 'runtime',
            ],
            'values' => $config,
        ];
        file_put_contents($path, '<?php return ' . var_export($payload, true) . ';');
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
