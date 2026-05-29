<?php

declare(strict_types=1);

use App\Http\ConfigCompiled;
use PHPUnit\Framework\TestCase;

final class ConfigCompiledTest extends TestCase
{
    public function testReadsPipelinePhaseFromCompiledPayload(): void
    {
        $root = $this->createRoot();
        $this->writeConfig($root, [
            'pipeline_phase' => [
                'pipeline' => 'dev',
                'phase' => 'runtime',
            ],
            'values' => [
                'APP_BASE_PATH' => '/public',
            ],
        ]);

        $config = new ConfigCompiled($root);

        self::assertSame('dev', $config->pipeline());
        self::assertSame('runtime', $config->phase());
        self::assertSame('/public', $config->get('APP_BASE_PATH'));
    }

    public function testThrowsWhenPipelinePhaseIsMissing(): void
    {
        $root = $this->createRoot();
        $this->writeConfig($root, ['values' => []]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Compiled config pipeline_phase ungueltig');

        new ConfigCompiled($root);
    }

    public function testThrowsWhenValuesAreMissing(): void
    {
        $root = $this->createRoot();
        $this->writeConfig($root, [
            'pipeline_phase' => [
                'pipeline' => 'dev',
                'phase' => 'runtime',
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Compiled config values ungueltig');

        new ConfigCompiled($root);
    }

    public function testThrowsWhenKeyMissing(): void
    {
        $root = $this->createRoot();
        $this->writeConfig($root, [
            'pipeline_phase' => ['pipeline' => 'dev', 'phase' => 'runtime'],
            'values' => [],
        ]);

        $config = new ConfigCompiled($root);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Config-Schlüssel fehlt: MISSING_KEY');

        $config->get('MISSING_KEY');
    }

    private function createRoot(): string
    {
        $root = sys_get_temp_dir() . '/config-compiled-' . bin2hex(random_bytes(6));
        mkdir($root . '/var/config', 0775, true);
        return $root;
    }

    private function writeConfig(string $root, array $payload): void
    {
        $path = $root . '/var/config/config.php';
        $content = "<?php\n\nreturn " . var_export($payload, true) . ";\n";
        file_put_contents($path, $content);
    }
}
