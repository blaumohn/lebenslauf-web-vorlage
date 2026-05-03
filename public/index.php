<?php

$vendorDir = defined('APP_VENDOR_DIR') ? APP_VENDOR_DIR : __DIR__ . '/../vendor';
require $vendorDir . '/autoload.php';

if (defined('APP_ROOT_DIR')) {
    registerAppHttpAutoload(APP_ROOT_DIR . '/src/Http');
}

$app = require __DIR__ . '/../src/Http/bootstrap.php';
$app->run();

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
