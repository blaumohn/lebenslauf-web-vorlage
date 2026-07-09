<?php

declare(strict_types=1);

use Slim\Psr7\Factory\ServerRequestFactory;

final class ErrorHandlerFeatureTest extends FeatureTestCase
{
    public function testUnknownRouteReturnsLocalizedNotFoundWithNavigation(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/cd?lang=es');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('No encontrado', $body);
        $this->assertStringNotContainsString('Serverfehler', $body);
    }

    public function testUnknownRouteResolvesLangFromAcceptHeaderHeader(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/cd')
            ->withHeader('Accept-Language', 'es-ES,es;q=0.9');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('No encontrado', (string) $response->getBody());
    }
}
