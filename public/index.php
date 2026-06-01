<?php

const ERROR_LOG_PATH = '/log/error.log';

function main(): void
{
    ini_set('log_errors', '1');
    ini_set('display_errors', '0');

    $deployRoot = dirname(__DIR__, 2);
    $appSlot    = dirname(__DIR__);

    // deploy-inject: Zeile wird per Regex erkannt.
    // https://docs.template.ysdani.com/de/areas/deploy/slot-switch/
    $vendorDir  = $appSlot . '/vendor';

    ini_set('error_log', $deployRoot . ERROR_LOG_PATH);
    registerShutdownTrap($deployRoot);

    require $appSlot . '/src/Http/bootstrap.php';
    run_bootstrap($appSlot, $vendorDir);
}

function registerShutdownTrap(string $deployRoot): void
{
    $logPath = $deployRoot . ERROR_LOG_PATH;
    register_shutdown_function('writeFatalToLog', $logPath);
}

function writeFatalToLog(string $logPath): void
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

main();
