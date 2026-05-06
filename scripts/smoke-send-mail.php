<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\ConfigCompiled;
use App\Http\Contact\MailService;

function main(string $rootPath): void
{
    $config = new ConfigCompiled($rootPath);
    $service = new MailService($config);
    $service->send('CI Smoke', 'ci@ci.invalid', 'Automatische Testmail aus CI.');
    echo "[smtp-smoke] Testmail gesendet.\n";
}

main(dirname(__DIR__));
