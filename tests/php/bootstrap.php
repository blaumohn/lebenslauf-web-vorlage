<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$deployState = __DIR__
    . '/../../src/resources/deploy-root/webroot/deploy-state.php';
if (is_file($deployState)) {
    require_once $deployState;
}

$featureTestCase = __DIR__ . '/Feature/FeatureTestCase.php';
if (is_file($featureTestCase)) {
    require_once $featureTestCase;
}
