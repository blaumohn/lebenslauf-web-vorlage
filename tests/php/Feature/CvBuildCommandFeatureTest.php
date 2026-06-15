<?php

declare(strict_types=1);

use App\Cli\CliContext;
use App\Cli\Command\BuildCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class CvBuildCommandFeatureTest extends FeatureTestCase
{
    public function testBuildConfigWritesRuntimeConfigWithOverride(): void
    {
        $this->writeCompiledConfigValue('CAPTCHA_MAX_GET', 'stale');

        $tester = new CommandTester(new BuildCommand(new CliContext($this->root)));
        $exitCode = $tester->execute([
            'pipeline'    => 'dev',
            'task'        => 'config',
            '--overrides' => json_encode(['CAPTCHA_MAX_GET' => '10']),
        ]);

        self::assertSame(0, $exitCode, $tester->getDisplay());
        self::assertSame('10', $this->readCompiledConfigValue('CAPTCHA_MAX_GET'));
    }

    public function testBuildConfigRestoresRuntimeConfigWithoutOverride(): void
    {
        $this->writeCompiledConfigValue('CAPTCHA_MAX_GET', 'stale');

        $tester = new CommandTester(new BuildCommand(new CliContext($this->root)));
        $tester->execute([
            'pipeline'    => 'dev',
            'task'        => 'config',
            '--overrides' => json_encode(['CAPTCHA_MAX_GET' => '10']),
        ]);

        $exitCode = $tester->execute([
            'pipeline' => 'dev',
            'task'     => 'config',
        ]);

        self::assertSame(0, $exitCode, $tester->getDisplay());
        self::assertSame(5, $this->readCompiledConfigValue('CAPTCHA_MAX_GET'));
    }

    public function testBuildCvUsesDataPathOverride(): void
    {
        $this->copySchema();
        mkdir($this->root . '/custom-cv');
        copy(
            $this->projectRoot() . '/src/resources/fixtures/lebenslauf/daten-gueltig.yaml',
            $this->root . '/custom-cv/daten-sonderpfad.yaml'
        );

        $tester = new CommandTester(new BuildCommand(new CliContext($this->root)));
        $exitCode = $tester->execute([
            'pipeline'    => 'dev',
            'task'        => 'cv',
            '--overrides' => json_encode(['LEBENSLAUF_DATEN_PFAD' => 'custom-cv']),
        ]);

        self::assertSame(0, $exitCode, $tester->getDisplay());
        self::assertFileExists($this->root . '/var/cache/html/cv-private-sonderpfad.html');
        self::assertStringContainsString('sonderpfad', $tester->getDisplay());
    }

    private function copySchema(): void
    {
        $target = $this->root . '/src/resources/build/schemas/lebenslauf.schema.json';
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        copy(
            $this->projectRoot() . '/src/resources/build/schemas/lebenslauf.schema.json',
            $target
        );
    }

    private function readCompiledConfigValue(string $key): mixed
    {
        $payload = json_decode(
            (string) file_get_contents($this->root . '/var/config/config.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        return $payload['values'][$key] ?? null;
    }

    private function writeCompiledConfigValue(string $key, mixed $value): void
    {
        $path = $this->root . '/var/config/config.json';
        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $payload['values'][$key] = $value;
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
