<?php

declare(strict_types=1);

use Slim\Psr7\Factory\ServerRequestFactory;

final class CvFeatureTest extends FeatureTestCase
{
    public function testPublicCvNotFound(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/cv?lang=de');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testPublicCvFound(): void
    {
        $app = $this->app();
        $htmlPath = $this->root . '/var/cache/html/cv-public.de.html';
        file_put_contents($htmlPath, '<h1>Public</h1>');

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/cv?lang=de');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Public', (string) $response->getBody());
    }

    public function testPublicCvResolvedViaHeader(): void
    {
        $app = $this->app();
        $htmlPath = $this->root . '/var/cache/html/cv-public.de.html';
        file_put_contents($htmlPath, '<h1>Lebenslauf</h1>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv')
            ->withHeader('Accept-Language', 'de-DE,de;q=0.9');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Lebenslauf', (string) $response->getBody());
    }

    public function testPublicCvLangSelectWhenUnresolvable(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/cv');
        $response = $app->handle($request);

        $this->assertSame(300, $response->getStatusCode());
    }

    public function testPublicCvLanguageSelection(): void
    {
        $app = $this->app();
        $dePath = $this->root . '/var/cache/html/cv-public.de.html';
        $esPath = $this->root . '/var/cache/html/cv-public.es.html';
        file_put_contents($dePath, '<h1>Deutsch</h1>');
        file_put_contents($esPath, '<h1>Español</h1>');

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/cv?lang=es');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Español', (string) $response->getBody());
    }

    public function testPrivateCvRequiresValidToken(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv?token=bad&lang=de');
        $response = $app->handle($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testPrivateCvWithToken(): void
    {
        $app = $this->app();
        $profile = 'entw';

        $tokenService = $this->buildTokenService();
        $token = $tokenService->add($profile, null);

        $htmlPath = $this->root . '/var/cache/html/cv-private-' . $profile . '.de.html';
        file_put_contents($htmlPath, '<h1>Private</h1>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv?token=' . urlencode($token) . '&lang=de');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Private', (string) $response->getBody());
    }

    public function testExpiredTokenRedirectsToPublicCvWithNotice(): void
    {
        $app = $this->app();
        $profile = 'entw';

        $tokenService = $this->buildTokenService();
        $token = $tokenService->add($profile, time() - 10);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv?token=' . urlencode($token) . '&lang=de');
        $response = $app->handle($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('token_state=expired', $response->getHeaderLine('Location'));
    }

    public function testPublicCvShowsExpiredNoticeWhenRedirected(): void
    {
        $app = $this->app();
        $htmlPath = $this->root . '/var/cache/html/cv-public-token-expired.de.html';
        file_put_contents($htmlPath, '<h1>Public</h1><div class="system-notice">abgelaufen</div>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cv?lang=de&token_state=expired');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('system-notice', (string) $response->getBody());
    }

    public function testPrivateCvLanguageSelection(): void
    {
        $app = $this->app();
        $profile = 'entw';

        $tokenService = $this->buildTokenService();
        $token = $tokenService->add($profile, null);

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
