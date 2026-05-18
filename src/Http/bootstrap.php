<?php

use App\Http\AppBuilder;
use App\Http\BootstrapRuntime;
use App\Http\ConfigCompiled;

require __DIR__ . '/BootstrapRuntime.php';

$rootPath = BootstrapRuntime::rootPath();
$vendorDir = BootstrapRuntime::vendorPath($rootPath);

require $vendorDir . '/autoload.php';
BootstrapRuntime::registerAppHttpAutoload($rootPath . '/src/Http');
BootstrapRuntime::registerShutdownTrap($rootPath);

$config = new ConfigCompiled($rootPath);
$app = AppBuilder::build($config);
$app->run();
