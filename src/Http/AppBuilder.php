<?php

namespace App\Http;

use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppBuilder
{
    public static function build(ConfigCompiled $config, string $appRoot, string $deployRoot): App
    {
        $basePath = self::resolveBasePath($config->get('APP_BASE_PATH'));
        $context = AppContext::fromConfig($config, $appRoot, $deployRoot, $basePath);

        $app = SlimAppFactory::create();
        $app->addBodyParsingMiddleware();

        if ($basePath !== '') {
            $app->setBasePath($basePath);
        }

        Routes::register($app, $context);

        $app->addRoutingMiddleware();

        $isDev = strtolower($config->pipeline()) === 'dev';
        $errorMiddleware = $app->addErrorMiddleware($isDev, true, true);
        $errorMiddleware->setDefaultErrorHandler(new ErrorHandler($context));

        return $app;
    }

    private static function resolveBasePath(mixed $raw): string
    {
        $value = trim((string) $raw);
        if ($value === '' || $value === '/') {
            return '';
        }
        return '/' . trim($value, '/');
    }
}
