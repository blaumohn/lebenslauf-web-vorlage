<?php

namespace App\Http\Actions;

use App\Http\AppContext;
use App\Http\ResponseHelper;
use App\Http\View\PageViewBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CvAction
{
    private AppContext $context;

    public function __construct(AppContext $context)
    {
        $this->context = $context;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $token = isset($params['token']) ? (string) $params['token'] : '';
        $lang = isset($params['lang']) ? (string) $params['lang'] : '';
        $lang = $this->resolveLang($lang);
        $fallbackLang = $this->defaultLang();

        if ($token !== '') {
            return $this->handlePrivateCv($response, $params, $token, $lang, $fallbackLang);
        }

        return $this->handlePublicCv($response, $lang, $fallbackLang);
    }

    private function handlePrivateCv(
        ResponseInterface $response,
        array $params,
        string $token,
        string $lang,
        string $fallbackLang
    ): ResponseInterface {
        $profile = isset($params['profile']) ? (string) $params['profile'] : '';
        if ($profile === '') {
            $profile = $this->context->tokenService->findProfileForToken($token) ?? '';
        }

        if ($profile === '' || !$this->context->tokenService->verify($profile, $token)) {
            return $this->renderError($response, 'Zugriff verweigert', 'Token ungültig oder abgelaufen.', 403);
        }

        $privateHtml = $this->resolvePrivateHtml($profile, $lang, $fallbackLang);
        if ($privateHtml === null) {
            return $this->renderError($response, 'Nicht gefunden', 'Privater Lebenslauf noch nicht vorhanden.', 404);
        }

        return ResponseHelper::html($response, $privateHtml);
    }

    private function handlePublicCv(
        ResponseInterface $response,
        string $lang,
        string $fallbackLang
    ): ResponseInterface {
        $publicHtml = $this->resolvePublicHtml($lang, $fallbackLang);
        if ($publicHtml === null) {
            return $this->renderError($response, 'Nicht gefunden', 'Öffentlicher Lebenslauf noch nicht vorhanden.', 404);
        }

        return ResponseHelper::html($response, $publicHtml);
    }

    private function renderError(
        ResponseInterface $response,
        string $title,
        string $message,
        int $status
    ): ResponseInterface {
        $base = PageViewBuilder::base($this->context->cvStorage->getHeaderFragment());
        $html = $this->context->twig->render('error.html.twig', [
            'title' => $title,
            'message' => $message,
        ] + $base);
        return ResponseHelper::html($response, $html, $status);
    }

    private function resolvePrivateHtml(string $profile, string $lang, string $fallbackLang): ?string
    {
        $privateHtml = $this->context->cvStorage->getPrivateHtmlForLang($profile, $lang);
        if ($privateHtml !== null || $lang === $fallbackLang) {
            return $privateHtml;
        }

        return $this->context->cvStorage->getPrivateHtmlForLang($profile, $fallbackLang);
    }

    private function resolvePublicHtml(string $lang, string $fallbackLang): ?string
    {
        $publicHtml = $this->context->cvStorage->getPublicHtmlForLang($lang);
        if ($publicHtml !== null || $lang === $fallbackLang) {
            return $publicHtml;
        }

        return $this->context->cvStorage->getPublicHtmlForLang($fallbackLang);
    }

    private function resolveLang(string $lang): string
    {
        $lang = strtolower(trim($lang));
        if ($lang === '') {
            return $this->defaultLang();
        }

        $supported = $this->supportedLangs();
        if ($supported === []) {
            return $lang;
        }

        return in_array($lang, $supported, true) ? $lang : $this->defaultLang();
    }

    private function defaultLang(): string
    {
        $supported = $this->supportedLangs();
        return $supported[0] ?? $this->resolveDefaultLang();
    }

    private function supportedLangs(): array
    {
        $raw = (string) $this->context->config->get('LEBENSLAUF_LANGS');
        return $this->parseLangs($raw, $this->resolveDefaultLang());
    }

    private function resolveDefaultLang(): string
    {
        $raw = (string) $this->context->config->get('LEBENSLAUF_LANG_DEFAULT');
        $value = strtolower(trim($raw));
        return $value === '' ? 'de' : $value;
    }

    private function parseLangs(string $raw, string $fallback): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            $fallback = strtolower(trim($fallback));
            return $fallback === '' ? [] : [$fallback];
        }
        $parts = preg_split('/\s*,\s*/', $raw);
        if ($parts === false) {
            return [];
        }
        return $this->normalizeLangs($parts);
    }

    private function normalizeLangs(array $parts): array
    {
        $langs = [];
        foreach ($parts as $part) {
            $value = strtolower(trim((string) $part));
            if ($value !== '') {
                $langs[] = $value;
            }
        }
        return array_values(array_unique($langs));
    }
}
