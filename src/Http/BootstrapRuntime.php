<?php

namespace App\Http;

final class BootstrapRuntime
{
    private static string $appHttpSrcDir = '';

    public static function rootPath(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function vendorPath(string $rootPath): string
    {
        return $rootPath . '/vendor';
    }

    public static function registerShutdownTrap(string $rootPath): void
    {
        $logDir = $rootPath . '/var/log';

        $logPath = $logDir . '/error.log';
        $handler = [self::class, 'writeFatalToLog'];
        register_shutdown_function($handler, $logPath);
    }

    public static function writeFatalToLog(string $logPath): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($error['type'], $fatal, true)) {
            return;
        }

        @mkdir(dirname($logPath), 0755, true);
        $entry = date('c') . ' FATAL ' . $error['message']
            . ' in ' . $error['file'] . ':' . $error['line'] . PHP_EOL;
        @file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
    }

    public static function registerAppHttpAutoload(string $srcDir): void
    {
        self::$appHttpSrcDir = $srcDir;
        spl_autoload_register(
            [self::class, 'loadAppHttpClass'],
            true,
            true
        );
    }

    public static function loadAppHttpClass(string $class): void
    {
        $prefix = 'App\\Http\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $relativePath = str_replace('\\', '/', $relativeClass) . '.php';
        $path = rtrim(self::$appHttpSrcDir, '/') . '/' . $relativePath;

        if (is_file($path)) {
            require $path;
        }
    }
}
