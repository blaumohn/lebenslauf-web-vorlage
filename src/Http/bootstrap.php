<?php

use App\Http\AppBuilder;
use App\Http\BootstrapRuntime;
use App\Http\ConfigCompiled;

require __DIR__ . '/BootstrapRuntime.php';

$rootPath = BootstrapRuntime::rootPath();
$vendorDir = $rootPath . '/vendor';

// deploy: Diese Zeile wird im Staging durch den Vendor-Slot-Pfad ersetzt.
// Änderung hier → _inject_vendor_require() in scripts/sftp-deploy.py anpassen.
// Siehe: https://docs.template.ysdani.com/de/areas/deploy/slot-switch/
require $vendorDir . '/autoload.php';
BootstrapRuntime::registerAppHttpAutoload($rootPath . '/src/Http');
BootstrapRuntime::registerShutdownTrap($rootPath);

$config = new ConfigCompiled($rootPath);
$app = AppBuilder::build($config);
$app->run();
