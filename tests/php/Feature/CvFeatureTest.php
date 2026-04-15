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
        $enPath = $this->root . '/var/cache/html/cv-public.en.html';
        file_put_contents($dePath, '<h1>Deutsch</h1>');
        file_put_contents($enPath, '<h1>English</h1>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv?lang=en');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('English', (string) $response->getBody());
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
        $enPath = $this->root . '/var/cache/html/cv-private-' . $profile . '.en.html';
        file_put_contents($dePath, '<h1>Privat</h1>');
        file_put_contents($enPath, '<h1>Private English</h1>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv?token=' . urlencode($token) . '&lang=en');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Private English', (string) $response->getBody());
    }
}
