<?php

declare(strict_types=1);

use App\Cli\Util\PythonRunner;
use PHPUnit\Framework\TestCase;

final class PythonRunnerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = '/private/tmp/python-runner-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testRunnerDoesNotFallbackWithoutVenvPython(): void
    {
        $runner = new PythonRunner($this->root);

        $method = new ReflectionMethod(PythonRunner::class, 'resolveCommand');

        self::assertNull($method->invoke($runner));
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($child) ? $this->removeTree($child) : unlink($child);
        }
        rmdir($path);
    }
}
