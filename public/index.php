<?php

$vendorDir = defined('APP_VENDOR_DIR') ? APP_VENDOR_DIR : __DIR__ . '/../vendor';
require $vendorDir . '/autoload.php';

$app = require __DIR__ . '/../src/Http/bootstrap.php';
$app->run();
