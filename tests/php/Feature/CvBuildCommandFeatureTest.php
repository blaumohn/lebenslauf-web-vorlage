<?php

declare(strict_types=1);

use App\Cli\CliContext;
use App\Cli\Command\BuildCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class CvBuildCommandFeatureTest extends FeatureTestCase
{
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
}
