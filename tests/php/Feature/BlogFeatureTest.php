<?php

declare(strict_types=1);

use Slim\Psr7\Factory\ServerRequestFactory;

final class BlogFeatureTest extends FeatureTestCase
{
    public function testBlogIndexExplicitLangNotFound(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/blog?lang=de');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testBlogIndexExplicitLangFound(): void
    {
        $app = $this->app();
        $path = $this->root . '/var/cache/html/blog/de/index.html';
        mkdir(dirname($path), 0775, true);
        file_put_contents($path, '<h1>Blog</h1>');

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/blog?lang=de');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Blog', (string) $response->getBody());
    }

    public function testBlogIndexResolvedViaHeader(): void
    {
        $app = $this->app();
        $path = $this->root . '/var/cache/html/blog/de/index.html';
        mkdir(dirname($path), 0775, true);
        file_put_contents($path, '<h1>Blog</h1>');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/blog')
            ->withHeader('Accept-Language', 'de-AT,de;q=0.9,en;q=0.5');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Blog', (string) $response->getBody());
    }

    public function testBlogPostExplicitLangFound(): void
    {
        $app = $this->app();
        $path = $this->root . '/var/cache/html/blog/de/mein-beitrag.html';
        mkdir(dirname($path), 0775, true);
        file_put_contents($path, '<h1>Mein Beitrag</h1>');

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/blog/mein-beitrag?lang=de');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Mein Beitrag', (string) $response->getBody());
    }

    public function testBlogPostNotFound(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/blog/existiert-nicht?lang=de');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testBlogLangSelectWhenUnresolvable(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/blog');
        $response = $app->handle($request);

        $this->assertSame(300, $response->getStatusCode());
    }
}
