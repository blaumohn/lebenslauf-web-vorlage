<?php

declare(strict_types=1);

use Slim\Psr7\Factory\ServerRequestFactory;

final class HomeFeatureTest extends FeatureTestCase
{
    public function testHomeExplicitLangNotFound(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/?lang=de');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testHomeExplicitLangFound(): void
    {
        $app = $this->app();
        $htmlPath = $this->root . '/var/cache/html/home/de/index.html';
        mkdir(dirname($htmlPath), 0775, true);
        file_put_contents($htmlPath, '<h1>Startseite</h1>');

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/?lang=de');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Startseite', (string) $response->getBody());
    }

    public function testHomeResolvedViaHeader(): void
    {
        $app = $this->app();
        $htmlPath = $this->root . '/var/cache/html/home/de/index.html';
        mkdir(dirname($htmlPath), 0775, true);
        file_put_contents($htmlPath, '<h1>Startseite</h1>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/')
            ->withHeader('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Startseite', (string) $response->getBody());
    }

    public function testHomeLangSelectWhenUnresolvable(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $app->handle($request);
        $body = (string) $response->getBody();

        $this->assertSame(300, $response->getStatusCode());
        $this->assertStringContainsString('Die bevorzugte Sprache konnte nicht automatisch erkannt werden.', $body);
        $this->assertStringContainsString('No se pudo detectar autom', $body);
    }
}
