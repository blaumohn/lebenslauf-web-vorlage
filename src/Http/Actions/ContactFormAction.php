<?php

namespace App\Http\Actions;

use App\Http\AppContext;
use App\Http\ResponseHelper;
use App\Http\View\PageViewBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ContactFormAction
{
    private AppContext $context;

    public function __construct(AppContext $context)
    {
        $this->context = $context;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $ipHash = $this->resolveIpHash($request);

        if ($this->isRateLimited($ipHash)) {
            return $this->renderError($response, 429);
        }

        return $this->renderForm($response, $ipHash, null);
    }

    private function resolveIpHash(ServerRequestInterface $request): string
    {
        $trustProxy = (bool) $this->context->config->get('TRUST_PROXY');
        $ip = $this->context->ipResolver->resolve($request, $trustProxy);
        return $this->context->ipHashService->hashIp($ip);
    }

    private function isRateLimited(string $ipHash): bool
    {
        $window = (int) $this->context->config->get('RATE_LIMIT_WINDOW_SECONDS');
        $maxGet = (int) $this->context->config->get('CAPTCHA_MAX_GET');
        return !$this->context->rateLimiter->allow('contact_get_' . $ipHash, $maxGet, $window);
    }

    private function renderError(ResponseInterface $response, int $status): ResponseInterface
    {
        $lang = $this->defaultLang();
        $base = PageViewBuilder::base(
            $this->context->cvStorage->getHeaderFragmentForLang($lang),
            $this->context->cvStorage->getFooterFragmentForLang($lang)
        );
        $html = $this->context->twig->render('error.html.twig', [
            'title' => 'Zu viele Anfragen',
            'message' => 'Bitte später erneut versuchen.',
        ] + $base);
        return ResponseHelper::html($response, $html, $status);
    }

    private function renderForm(ResponseInterface $response, string $ipHash, ?string $error): ResponseInterface
    {
        $lang = $this->defaultLang();
        $base = PageViewBuilder::base(
            $this->context->cvStorage->getHeaderFragmentForLang($lang),
            $this->context->cvStorage->getFooterFragmentForLang($lang)
        );
        $challenge = $this->context->captchaService->createChallenge($ipHash);
        $captchaId = $challenge['captcha_id'];
        $captchaUrl = '/captcha.png?id=' . urlencode($captchaId);
        $html = $this->context->twig->render($this->contactTemplate(), [
            'title' => 'Kontakt',
            'form' => [
                'show_error' => $error !== null,
                'error_text' => $error ?? '',
                'captcha_id' => $captchaId,
                'captcha_url' => $captchaUrl,
                'values' => ['name' => '', 'email' => '', 'message' => ''],
            ],
        ] + $base);
        return ResponseHelper::html($response, $html);
    }

    private function defaultLang(): string
    {
        $lang = strtolower(trim($this->context->config->get('CONTENT_LANG_DEFAULT')));
        if ($lang === '') {
            throw new \RuntimeException('Konfiguration fehlt: CONTENT_LANG_DEFAULT');
        }
        return $lang;
    }

    private function contactTemplate(): string
    {
        $lang = strtolower(trim($this->context->config->get('CONTENT_LANG_DEFAULT')));
        if ($lang === '') {
            throw new \RuntimeException('Konfiguration fehlt: CONTENT_LANG_DEFAULT');
        }
        return "@generated/contact/{$lang}.twig";
    }
}
