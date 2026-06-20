<?php

declare(strict_types=1);

use App\Cli\ConfigValues;
use App\Cli\Site\ContactContentRenderer;
use Slim\Psr7\Factory\ServerRequestFactory;
use Symfony\Component\Console\Output\NullOutput;

final class ContactFeatureTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->copyContactResource();
        $this->generateContactTemplate();
    }

    private function copyContactResource(): void
    {
        $src = $this->projectRoot() . '/src/resources/contact/contact.yaml';
        $dest = $this->root . '/src/resources/contact/contact.yaml';
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0775, true);
        }
        copy($src, $dest);
    }

    private function generateContactTemplate(): void
    {
        $config = new ConfigValues([
            'CONTENT_LANG_DEFAULT' => 'de',
            'CONTENT_LANGS' => 'de,en',
        ]);
        $renderer = new ContactContentRenderer($config, $this->root);
        $renderer->render(new NullOutput());
    }

    public function testContactFormRenders(): void
    {
        $app = $this->app();

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/contact');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('/captcha.png?id=', $body);
        $captchaId = $this->extractCaptchaId($body);
        $statePath = $this->captchaStatePath($captchaId);

        $this->assertFileExists($statePath);
        $this->assertStringContainsString(
            'solution_text',
            (string) file_get_contents($statePath)
        );
    }

    public function testContactFormKeepsValuesOnError(): void
    {
        $app = $this->app();

        $service = $this->buildCaptchaService();
        $ip = '203.0.113.10';
        $ipHash = $this->ipHashFor($ip);
        $challenge = $service->createChallenge($ipHash);

        $server = ['REMOTE_ADDR' => $ip];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/contact', $server)
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
        $this->assertStringContainsString(
            'value="Max Mustermann"',
            $body
        );
        $this->assertStringContainsString(
            'value="max@example.com"',
            $body
        );
        $this->assertStringContainsString('Test Nachricht', $body);
    }

    public function testDeployHintWhenContactSessionIsGone(): void
    {
        $app = $this->app();
        $ip = '203.0.113.12';

        $captchaId = bin2hex(random_bytes(16)) . '_' . time();

        $server = ['REMOTE_ADDR' => $ip];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/contact', $server)
            ->withParsedBody([
                'name' => 'Max Mustermann',
                'email' => 'max@example.com',
                'message' => 'Test Nachricht',
                'captcha_id' => $captchaId,
                'captcha_answer' => 'ABCDEF',
            ]);

        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('aktualisiert', $body);
    }

    public function testContactSubmitSuccess(): void
    {
        $app = $this->app();

        $service = $this->buildCaptchaService();
        $ip = '203.0.113.11';
        $ipHash = $this->ipHashFor($ip);
        $challenge = $service->createChallenge($ipHash);

        $server = ['REMOTE_ADDR' => $ip];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/contact', $server)
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

    private function extractCaptchaId(string $body): string
    {
        $matches = [];
        $pattern = '/name="captcha_id" value="([^"]+)"/';
        $this->assertSame(1, preg_match($pattern, $body, $matches));
        return $matches[1];
    }

    private function captchaStatePath(string $captchaId): string
    {
        return $this->root
            . '/var/tmp/captcha/'
            . $captchaId
            . '.json';
    }
}
