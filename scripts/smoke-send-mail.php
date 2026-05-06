<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\ConfigCompiled;
use App\Http\Mail\MailMessage;
use App\Http\Mail\MailService;

function main(string $rootPath): void
{
    $config = new ConfigCompiled($rootPath);
    $service = new MailService($config);
    $service->send(new MailMessage(
        module: 'Smoke',
        title: 'Automatische Testmail aus CI',
        body: 'Smoke-Test erfolgreich.',
    ));
    echo "[smtp-smoke] Testmail gesendet.\n";
}

main(dirname(__DIR__));
