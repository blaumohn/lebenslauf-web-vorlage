<?php

register_shutdown_trap();

$vendorDir = defined('APP_VENDOR_DIR') ? APP_VENDOR_DIR : __DIR__ . '/../vendor';
require $vendorDir . '/autoload.php';

if (defined('APP_ROOT_DIR')) {
    registerAppHttpAutoload(APP_ROOT_DIR . '/src/Http');
}

$app = require __DIR__ . '/../src/Http/bootstrap.php';
$app->run();

function register_shutdown_trap(): void
{
    $logDir = defined('APP_ROOT_DIR')
        ? dirname(APP_ROOT_DIR) . '/log'
        : dirname(__DIR__) . '/var/log';
    register_shutdown_function('write_fatal_to_log', $logDir . '/error.log');
}

function write_fatal_to_log(string $logPath): void
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

function registerAppHttpAutoload(string $srcDir): void
{
    spl_autoload_register(
        function (string $class) use ($srcDir): void {
            $prefix = 'App\\Http\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relativeClass = substr($class, strlen($prefix));
            $relativePath = str_replace('\\', '/', $relativeClass) . '.php';
            $path = rtrim($srcDir, '/') . '/' . $relativePath;

            if (is_file($path)) {
                require $path;
            }
        },
        true,
        true
    );
}
