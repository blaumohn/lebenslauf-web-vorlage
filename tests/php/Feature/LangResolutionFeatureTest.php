<?php

declare(strict_types=1);

use Slim\Psr7\Factory\ServerRequestFactory;

final class LangResolutionFeatureTest extends FeatureTestCase
{
    public function testLangSelectWhenHeaderExplicitlyUnsupported(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/')
            ->withHeader('Accept-Language', 'fr-FR,fr;q=0.9');
        $response = $app->handle($request);

        $this->assertSame(300, $response->getStatusCode());
    }

    public function testQueryLangWinsOverUnsupportedHeader(): void
    {
        $app = $this->app();
        $htmlPath = $this->root . '/var/cache/html/home/de/index.html';
        mkdir(dirname($htmlPath), 0775, true);
        file_put_contents($htmlPath, '<h1>Startseite</h1>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/?lang=de')
            ->withHeader('Accept-Language', 'fr-FR,fr;q=0.9');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Startseite', (string) $response->getBody());
    }

    public function testUnsupportedQueryLangFallsBackToSupportedHeader(): void
    {
        $app = $this->app();
        $htmlPath = $this->root . '/var/cache/html/home/es/index.html';
        mkdir(dirname($htmlPath), 0775, true);
        file_put_contents($htmlPath, '<h1>Inicio</h1>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/?lang=fr')
            ->withHeader('Accept-Language', 'es-ES,es;q=0.9');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Inicio', (string) $response->getBody());
    }

    public function testLangSelectLinksReencodeQueryValuesConsistentlyWithPhpParsing(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/?q=hallo+welt')
            ->withHeader('Accept-Language', 'fr-FR,fr;q=0.9');
        $response = $app->handle($request);
        $body = (string) $response->getBody();

        $this->assertSame(300, $response->getStatusCode());
        preg_match('/href="([^"]*lang=de[^"]*)"/', $body, $matches);
        $this->assertNotEmpty($matches, 'Sprachauswahl-Link mit lang=de nicht gefunden: ' . $body);
        $href = html_entity_decode($matches[1]);

        $this->assertStringContainsString('q=hallo%20welt', $href);
        $this->assertStringNotContainsString('q=hallo%2Bwelt', $href);
    }
}
