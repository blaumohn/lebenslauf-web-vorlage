<?php

use App\Http\AppBuilder;
use App\Http\AppHttpClassLoader;
use App\Http\ConfigCompiled;

require_once __DIR__ . '/AppHttpClassLoader.php';

function run_bootstrap(string $appRoot, string $vendorDir): void
{
    $deployRoot = dirname($appRoot);
    require $vendorDir . '/autoload.php';
    register_app_http_autoload($appRoot . '/src/Http');

    $config = new ConfigCompiled($appRoot);
    $app = AppBuilder::build($config, $appRoot, $deployRoot);
    $app->run();
}

function register_app_http_autoload(string $srcDir): void
{
    spl_autoload_register(new AppHttpClassLoader($srcDir), true, true);
}
