<?php

declare(strict_types=1);

use App\Cli\ConfigValues;
use App\Cli\Site\BlogIndexRenderer;
use App\Cli\Site\BlogPostRenderer;
use Slim\Psr7\Factory\ServerRequestFactory;
use Symfony\Component\Console\Output\NullOutput;

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

    public function testRenderedBlogLinksKeepTheirLanguage(): void
    {
        $blogDir = $this->root . '/src/resources/fixtures/blog';
        mkdir($blogDir, 0775, true);
        copy($this->projectRoot() . '/src/resources/fixtures/blog/blog.yaml', $blogDir . '/blog.yaml');
        copy(
            $this->projectRoot() . '/src/resources/fixtures/blog/blog-beispiel.yaml',
            $blogDir . '/blog-beispiel.yaml'
        );

        $config = new ConfigValues([
            'CONTENT_LANGS' => 'de,es',
            'APP_BASE_PATH' => '/',
            'CONTENT_PATH' => 'src/resources/fixtures',
        ]);
        $output = new NullOutput();
        (new BlogIndexRenderer($config, $this->root))->render($output);
        (new BlogPostRenderer($config, $this->root))->render($output);

        $indexHtml = (string) file_get_contents($this->root . '/var/cache/html/blog/de/index.html');
        self::assertStringContainsString(
            'href="/blog/beispiel-beitrag-eins?lang=de"',
            $indexHtml
        );

        $postHtml = (string) file_get_contents(
            $this->root . '/var/cache/html/blog/de/beispiel-beitrag-eins.html'
        );
        self::assertStringContainsString('href="/blog?lang=de"', $postHtml);
    }
}
