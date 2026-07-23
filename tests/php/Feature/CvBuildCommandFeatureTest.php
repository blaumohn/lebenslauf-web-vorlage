<?php

declare(strict_types=1);

use App\Cli\CliContext;
use App\Cli\Command\BuildCommand;
use PipelineConfigSpec\PipelineConfig;
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

        $configured = (new PipelineConfig($this->root, 'src/resources/pipeline-config'))
            ->values('dev', 'runtime');

        self::assertSame(0, $exitCode, $tester->getDisplay());
        self::assertSame($configured['CAPTCHA_MAX_GET'], $this->readCompiledConfigValue('CAPTCHA_MAX_GET'));
    }

    public function testBuildCvUsesDataPathOverride(): void
    {
        $this->prepareCustomContentRoot('custom-content');

        $tester = new CommandTester(new BuildCommand(new CliContext($this->root)));
        $exitCode = $tester->execute([
            'pipeline'    => 'dev',
            'task'        => 'site',
            '--overrides' => json_encode(['CONTENT_PATH' => 'custom-content']),
        ]);

        self::assertSame(0, $exitCode, $tester->getDisplay());
        self::assertFileExists($this->root . '/var/cache/html/cv-private-sonderpfad.de.html');
        self::assertStringContainsString('sonderpfad', $tester->getDisplay());
    }

    public function testBuildSiteAllowsTranslationsForDisabledLangs(): void
    {
        $this->prepareCustomContentRoot('custom-content-with-pt');

        $tester = new CommandTester(new BuildCommand(new CliContext($this->root)));
        $exitCode = $tester->execute([
            'pipeline'    => 'dev',
            'task'        => 'site',
            '--overrides' => json_encode(['CONTENT_PATH' => 'custom-content-with-pt']),
        ]);

        self::assertSame(0, $exitCode, $tester->getDisplay());
        self::assertSame('de,es', $this->readCompiledConfigValue('CONTENT_LANGS'));
        $this->assertBuiltLangs(['de', 'es'], ['pt']);
    }

    public function testBuildSiteUsesOnlyConfiguredLangs(): void
    {
        $this->prepareCustomContentRoot('custom-content-with-pt');

        $tester = new CommandTester(new BuildCommand(new CliContext($this->root)));
        $exitCode = $tester->execute([
            'pipeline'    => 'dev',
            'task'        => 'site',
            '--overrides' => json_encode([
                'CONTENT_LANGS' => 'es,pt',
                'CONTENT_PATH'  => 'custom-content-with-pt',
            ]),
        ]);

        self::assertSame(0, $exitCode, $tester->getDisplay());
        self::assertSame('es,pt', $this->readCompiledConfigValue('CONTENT_LANGS'));
        $this->assertBuiltLangs(['es', 'pt'], ['de']);
    }

    private function prepareCustomContentRoot(string $name): void
    {
        $root = $this->root . '/' . $name;
        mkdir($root . '/lebenslauf', 0775, true);
        copy(
            $this->projectRoot() . '/src/resources/fixtures/lebenslauf/daten-demo.yaml',
            $root . '/lebenslauf/daten-sonderpfad.yaml'
        );
        copy(
            $this->projectRoot() . '/src/resources/fixtures/lebenslauf/kontaktdaten.yaml',
            $root . '/lebenslauf/kontaktdaten.yaml'
        );
        mkdir($root . '/blog', 0775, true);
        mkdir($root . '/home', 0775, true);
        copy(
            $this->projectRoot() . '/src/resources/fixtures/home/home.yaml',
            $root . '/home/home.yaml'
        );
        mkdir($root . '/site', 0775, true);
        copy(
            $this->projectRoot() . '/src/resources/fixtures/site/site.yaml',
            $root . '/site/site.yaml'
        );
        mkdir($this->root . '/src/resources/contact', 0775, true);
        copy(
            $this->projectRoot() . '/src/resources/contact/contact.yaml',
            $this->root . '/src/resources/contact/contact.yaml'
        );
    }

    private function assertBuiltLangs(array $enabledLangs, array $disabledLangs): void
    {
        foreach ($enabledLangs as $lang) {
            self::assertFileExists($this->root . "/var/cache/html/home/{$lang}/index.html");
            self::assertFileExists($this->root . "/var/cache/html/blog/{$lang}/index.html");
            self::assertFileExists($this->root . "/var/cache/html/cv-private-sonderpfad.{$lang}.html");
        }

        foreach ($disabledLangs as $lang) {
            self::assertFileDoesNotExist($this->root . "/var/cache/html/home/{$lang}/index.html");
            self::assertFileDoesNotExist($this->root . "/var/cache/html/blog/{$lang}/index.html");
            self::assertFileDoesNotExist($this->root . "/var/cache/html/cv-private-sonderpfad.{$lang}.html");
        }
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
