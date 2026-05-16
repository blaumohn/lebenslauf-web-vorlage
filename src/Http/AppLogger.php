<?php

namespace App\Http;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class AppLogger
{
    public static function create(string $logDir, string $channel): LoggerInterface
    {
        $logger = new Logger('app');
        if ($channel === 'file' || $channel === 'stack') {
            $logger->pushHandler(new StreamHandler($logDir . '/error.log', Level::Warning));
        }
        if ($channel === 'stderr' || $channel === 'stack') {
            $logger->pushHandler(new StreamHandler('php://stderr', Level::Debug));
        }
        return $logger;
    }
}
