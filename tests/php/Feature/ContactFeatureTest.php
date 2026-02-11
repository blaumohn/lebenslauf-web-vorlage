<?php

declare(strict_types=1);

use App\Http\Captcha\CaptchaService;
use App\Http\Storage\FileStorage;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ContactFeatureTest extends FeatureTestCase
{
    public function testContactFormRenders(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/contact');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('/captcha.png?id=', $body);
    }

    public function testContactFormKeepsValuesOnError(): void
    {
        $app = $this->app();

        $storage = new FileStorage();
        $service = new CaptchaService($storage, $this->root . '/var/tmp/captcha', 600);
        $ip = '203.0.113.10';
        $ipHash = $this->ipHashFor($ip);
        $challenge = $service->createChallenge($ipHash);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/contact', ['REMOTE_ADDR' => $ip])
            ->withParsedBody([
                'name' => 'Max Mustermann',
                'email' => 'max@example.com',
                'message' => 'Test Nachricht',
                'captcha_id' => $challenge['captcha_id'],
                'captcha_answer' => 'wrong',
            ]);

        $response = $app->handle($request);

        $this->assertSame(403, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('value="Max Mustermann"', $body);
        $this->assertStringContainsString('value="max@example.com"', $body);
        $this->assertStringContainsString('Test Nachricht', $body);
    }

    public function testContactSubmitSuccess(): void
    {
        $app = $this->app();

        $storage = new FileStorage();
        $service = new CaptchaService($storage, $this->root . '/var/tmp/captcha', 600);
        $ip = '203.0.113.11';
        $ipHash = $this->ipHashFor($ip);
        $challenge = $service->createChallenge($ipHash);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/contact', ['REMOTE_ADDR' => $ip])
            ->withParsedBody([
                'name' => 'Max Mustermann',
                'email' => 'max@example.com',
                'message' => 'Test Nachricht',
                'captcha_id' => $challenge['captcha_id'],
                'captcha_answer' => $challenge['solution_text'],
            ]);

        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Danke', $body);
    }
}
