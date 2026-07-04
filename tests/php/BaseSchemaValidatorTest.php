<?php

declare(strict_types=1);

use App\Cli\ConfigValues;
use App\Cli\Site\BaseSchemaValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class BaseSchemaValidatorTest extends TestCase
{
    public function testValidateYamlFileReportsSyntaxErrorInsteadOfThrowing(): void
    {
        $dir = sys_get_temp_dir() . '/base-schema-validator-test-' . uniqid();
        mkdir($dir, 0775, true);
        $path = $dir . '/broken.yaml';
        file_put_contents($path, "titel: \"kaputt\n  status: published\n");

        $output = new BufferedOutput();
        $result = $this->validator()->validateYamlFileForTest($path, 'contact.schema.json', 'Test', $output);

        self::assertFalse($result);
        self::assertStringContainsString('YAML-Fehler', $output->fetch());

        unlink($path);
        rmdir($dir);
    }

    private function validator(): object
    {
        return new class(new ConfigValues([]), dirname(__DIR__, 2)) extends BaseSchemaValidator {
            public function validateYamlFileForTest(
                string $path,
                string $schemaName,
                string $label,
                \Symfony\Component\Console\Output\OutputInterface $output
            ): bool {
                return $this->validateYamlFile($path, $schemaName, $label, $output);
            }
        };
    }
}
