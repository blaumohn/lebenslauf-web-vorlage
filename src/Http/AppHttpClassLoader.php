<?php

namespace App\Http;

final class AppHttpClassLoader
{
    public function __construct(private string $srcDir)
    {
    }

    public function __invoke(string $class): void
    {
        $prefix = 'App\\Http\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $relativePath = str_replace('\\', '/', $relative) . '.php';
        $path = rtrim($this->srcDir, '/') . '/' . $relativePath;
        if (is_file($path)) {
            require $path;
        }
    }
}
