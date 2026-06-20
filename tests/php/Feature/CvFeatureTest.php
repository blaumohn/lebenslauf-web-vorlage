<?php

declare(strict_types=1);

use Slim\Psr7\Factory\ServerRequestFactory;

final class CvFeatureTest extends FeatureTestCase
{
    public function testPublicCvNotFound(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testPublicCvFound(): void
    {
        $app = $this->app();
        $htmlPath = $this->root . '/var/cache/html/cv-public.html';
        file_put_contents($htmlPath, '<h1>Public</h1>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Public', (string) $response->getBody());
    }

    public function testPublicCvLanguageSelection(): void
    {
        $app = $this->app();
        $dePath = $this->root . '/var/cache/html/cv-public.de.html';
        $esPath = $this->root . '/var/cache/html/cv-public.es.html';
        file_put_contents($dePath, '<h1>Deutsch</h1>');
        file_put_contents($esPath, '<h1>Español</h1>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv?lang=es');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Español', (string) $response->getBody());
    }

    public function testPrivateCvRequiresValidToken(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv?token=bad');
        $response = $app->handle($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testPrivateCvWithToken(): void
    {
        $app = $this->app();
        $profile = 'entw';
        $token = 'secret-token';

        $tokenService = $this->buildTokenService();
        $tokenService->rotate($profile, [$token]);

        $htmlPath = $this->root . '/var/cache/html/cv-private-' . $profile . '.html';
        file_put_contents($htmlPath, '<h1>Private</h1>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv?token=' . urlencode($token));
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Private', (string) $response->getBody());
    }

    public function testPrivateCvLanguageSelection(): void
    {
        $app = $this->app();
        $profile = 'entw';
        $token = 'secret-token';

        $tokenService = $this->buildTokenService();
        $tokenService->rotate($profile, [$token]);

        $dePath = $this->root . '/var/cache/html/cv-private-' . $profile . '.de.html';
        $esPath = $this->root . '/var/cache/html/cv-private-' . $profile . '.es.html';
        file_put_contents($dePath, '<h1>Privat</h1>');
        file_put_contents($esPath, '<h1>Privado</h1>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv?token=' . urlencode($token) . '&lang=es');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Privado', (string) $response->getBody());
    }
}
