<?php

declare(strict_types=1);

use App\Http\ConfigCompiled;
use PHPUnit\Framework\TestCase;

final class ConfigCompiledTest extends TestCase
{
    public function testReadsPipelinePhaseFromSeparateContextFile(): void
    {
        $root = $this->createRoot();
        $this->writePhp($root . '/var/config/config.php', ['APP_BASE_PATH' => '/public']);
        $this->writePhp($root . '/var/config/config.context.php', [
            'pipeline' => 'dev',
            'phase' => 'runtime',
        ]);

        $config = new ConfigCompiled($root);

        self::assertSame('dev', $config->pipeline());
        self::assertSame('runtime', $config->phase());
        self::assertSame('/public', $config->basePath());
    }

    public function testThrowsWhenContextFileIsMissing(): void
    {
        $root = $this->createRoot();
        $this->writePhp($root . '/var/config/config.php', []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Compiled config context fehlt');

        new ConfigCompiled($root);
    }

    public function testThrowsWhenContextFileIsInvalid(): void
    {
        $root = $this->createRoot();
        $this->writePhp($root . '/var/config/config.php', []);
        file_put_contents($root . '/var/config/config.context.php', "<?php\n\nreturn 'invalid';\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Compiled config context ungueltig');

        new ConfigCompiled($root);
    }

    private function createRoot(): string
    {
        $root = sys_get_temp_dir() . '/config-compiled-' . bin2hex(random_bytes(6));
        mkdir($root . '/var/config', 0775, true);
        return $root;
    }

    private function writePhp(string $path, array $payload): void
    {
        $content = "<?php\n\nreturn " . var_export($payload, true) . ";\n";
        file_put_contents($path, $content);
    }
}
