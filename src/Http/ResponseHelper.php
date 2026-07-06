<?php

namespace App\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\StreamFactory;

final class ResponseHelper
{
    public static function html(ResponseInterface $response, string $html, int $status = 200): ResponseInterface
    {
        $response->getBody()->write($html);
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    public static function text(ResponseInterface $response, string $text, int $status = 200): ResponseInterface
    {
        $response->getBody()->write($text);
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    public static function redirect(ResponseInterface $response, string $location, int $status = 302): ResponseInterface
    {
        return $response
            ->withStatus($status)
            ->withHeader('Location', $location);
    }

    public static function png(ResponseInterface $response, string $png): ResponseInterface
    {
        $stream = (new StreamFactory())->createStream($png);
        return $response
            ->withBody($stream)
            ->withHeader('Content-Type', 'image/png')
            ->withHeader('Content-Length', (string) strlen($png))
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
