<?php

declare(strict_types=1);

use App\Http\Captcha\CaptchaService;
use App\Http\Storage\FileStorage;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CaptchaFeatureTest extends FeatureTestCase
{
    public function testCaptchaPngReturnsBinary(): void
    {
        $app = $this->app();

        $storage = new FileStorage();
        $service = new CaptchaService($storage, $this->root . '/var/tmp/captcha', 600);
        $challenge = $service->createChallenge($this->ipHashFor('127.0.0.1'));

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/captcha.png?id=' . $challenge['captcha_id']);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $this->assertNotEmpty($body);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($body, 0, 8));
    }

    public function testCaptchaNotFoundForUnknownId(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/captcha.png?id=missing');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testCaptchaNotFoundAfterUse(): void
    {
        $app = $this->app();

        $storage = new FileStorage();
        $service = new CaptchaService($storage, $this->root . '/var/tmp/captcha', 600);
        $ipHash = $this->ipHashFor('127.0.0.1');
        $challenge = $service->createChallenge($ipHash);
        $service->verify($challenge['captcha_id'], $challenge['solution_text'], $ipHash);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/captcha.png?id=' . $challenge['captcha_id']);
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }
}
