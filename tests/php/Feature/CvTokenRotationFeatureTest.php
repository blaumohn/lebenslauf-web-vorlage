<?php

declare(strict_types=1);

use Slim\Psr7\Factory\ServerRequestFactory;

final class CvTokenRotationFeatureTest extends FeatureTestCase
{
    public function testTokenRotationViaTaskDispatchAndPrivateCvAccess(): void
    {
        $app = $this->app();
        $profile = 'smoke';

        file_put_contents(
            $this->root . '/var/cache/html/cv-private-' . $profile . '.html',
            '<h1>Geheiminhalt</h1>'
        );
        file_put_contents(
            $this->root . '/var/cache/html/cv-public.html',
            '<h1>Oeffentlich</h1>'
        );

        $this->placeTask($profile, 1);

        ob_start();
        $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/tasks/dispatch'));
        $mailOutput = (string) ob_get_clean();

        $token = $this->extractTokenFromMailOutput($mailOutput);
        $this->assertNotEmpty($token, 'Token aus Mail-Output nicht lesbar');

        $privateResp = $app->handle(
            (new ServerRequestFactory())
                ->createServerRequest('GET', '/cv?token=' . urlencode($token))
        );
        $this->assertSame(200, $privateResp->getStatusCode());
        $this->assertStringContainsString('Geheiminhalt', (string) $privateResp->getBody());

        $publicResp = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/cv')
        );
        $this->assertSame(200, $publicResp->getStatusCode());
        $this->assertStringNotContainsString('Geheiminhalt', (string) $publicResp->getBody());
    }

    private function placeTask(string $profile, int $count): void
    {
        $taskDir = dirname($this->root) . '/var/tasks';
        mkdir($taskDir, 0775, true);
        file_put_contents(
            $taskDir . '/token-rotation.ini',
            "[task]\ntype = cv_token_rotation\nprofile = {$profile}\ncount = {$count}\n"
        );
    }

    private function extractTokenFromMailOutput(string $output): string
    {
        $afterHeaders = strstr($output, "\n\n");
        if ($afterHeaders === false) {
            return '';
        }
        return trim(explode("\n", ltrim($afterHeaders, "\n"))[0]);
    }
}
