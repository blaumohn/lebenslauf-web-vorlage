<?php

namespace App\Http\Middleware;

use App\Http\AppContext;
use App\Http\ResponseHelper;
use App\Http\View\PageViewBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class LangMiddleware implements MiddlewareInterface
{
    private AppContext $context;

    public function __construct(AppContext $context)
    {
        $this->context = $context;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $supported = $this->supportedLangs();
        $lang = strtolower(trim((string) ($request->getQueryParams()['lang'] ?? '')));

        if ($lang === '' || !in_array($lang, $supported, true)) {
            $lang = $this->context->langResolver->fromHeader(
                $request->getHeaderLine('Accept-Language'),
                $supported
            ) ?? '';
        }

        if ($lang === '') {
            return $this->langSelectResponse($supported);
        }

        return $handler->handle($request->withAttribute('lang', $lang));
    }

    private function langSelectResponse(array $supported): ResponseInterface
    {
        $translations = $this->loadTranslations($supported);
        $base = PageViewBuilder::base(null, null);
        $html = $this->context->twig->render('lang-select.html.twig', [
            'supported_langs' => $supported,
            'translations'    => $translations,
        ] + $base);
        $response = $this->context->responseFactory->createResponse(300);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function loadTranslations(array $supported): array
    {
        $path = Path::join($this->context->appRoot, 'src', 'resources', 'lang-select', 'lang-select.yaml');
        $data = Yaml::parseFile($path);
        $translations = [];
        foreach ($supported as $lang) {
            $translations[$lang] = $this->resolveTranslationForLang($lang, $data);
        }
        return $translations;
    }

    private function resolveTranslationForLang(string $lang, array $data): array
    {
        $keys = ['title', 'heading', 'description', 'link'];
        $translation = [];
        foreach ($keys as $key) {
            $value = $data[$key][$lang] ?? null;
            if ($value === null) {
                throw new \RuntimeException("lang-select.yaml: '{$key}.{$lang}' fehlt");
            }
            $translation[$key] = $value;
        }
        return $translation;
    }

    private function supportedLangs(): array
    {
        $raw = (string) $this->context->config->get('CONTENT_LANGS');
        return array_values(array_filter(array_map('trim', explode(',', strtolower($raw)))));
    }
}
