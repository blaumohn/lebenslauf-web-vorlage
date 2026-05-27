<?php

use App\Http\AppBuilder;
use App\Http\AppHttpClassLoader;
use App\Http\ConfigCompiled;

require_once __DIR__ . '/AppHttpClassLoader.php';

function run_bootstrap(string $appSlot, string $vendorDir): void
{
    require $vendorDir . '/autoload.php';
    register_app_http_autoload($appSlot . '/src/Http');

    $config = new ConfigCompiled($appSlot);
    $app = AppBuilder::build($config);
    $app->run();
}

function register_app_http_autoload(string $srcDir): void
{
    spl_autoload_register(new AppHttpClassLoader($srcDir), true, true);
}
