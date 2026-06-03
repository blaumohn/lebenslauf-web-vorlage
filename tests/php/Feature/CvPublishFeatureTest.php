<?php

declare(strict_types=1);

use Slim\Psr7\Factory\ServerRequestFactory;

final class CvPublishFeatureTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        mkdir($this->root . '/var/tmp/html-publish', 0775, true);
        mkdir($this->root . '/var/tasks', 0775, true);
    }

    public function testPublishTaskMovesHtmlToCache(): void
    {
        $app = $this->app();
        file_put_contents(
            $this->root . '/var/tmp/html-publish/cv-public.html',
            '<html><body>Lebenslauf</body></html>'
        );
        $this->placeTask();

        $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/tasks/dispatch'));

        $this->assertFileExists($this->root . '/var/cache/html/cv-public.html');
        $this->assertStringEqualsFile(
            $this->root . '/var/cache/html/cv-public.html',
            '<html><body>Lebenslauf</body></html>'
        );
    }

    public function testPublishTaskStagingIsCleanedUp(): void
    {
        $app = $this->app();
        file_put_contents($this->root . '/var/tmp/html-publish/cv-public.html', '<html/>');
        $this->placeTask();

        $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/tasks/dispatch'));

        $this->assertDirectoryDoesNotExist($this->root . '/var/tmp/html-publish');
    }

    public function testCvRouteServesPublishedHtml(): void
    {
        $app = $this->app();
        file_put_contents(
            $this->root . '/var/tmp/html-publish/cv-public.html',
            '<html><body>Frisch</body></html>'
        );
        $this->placeTask();
        $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/tasks/dispatch'));

        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/cv')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Frisch', (string) $response->getBody());
    }

    private function placeTask(): void
    {
        file_put_contents(
            $this->root . '/var/tasks/cv-publish.ini',
            "[task]\ntype = cv_publish\n"
        );
    }
}
