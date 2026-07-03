<?php

namespace App\Http\Middleware;

use App\Http\AppContext;
use App\Http\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
            return $this->langSelectResponse();
        }

        return $handler->handle($request->withAttribute('lang', $lang));
    }

    private function langSelectResponse(): ResponseInterface
    {
        $html = $this->context->htmlCache->getLangSelectHtml();
        if ($html === null) {
            throw new \RuntimeException('Lang-Select-Fragment fehlt. LangSelectRenderer muss zuerst laufen.');
        }
        $response = $this->context->responseFactory->createResponse(300);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function supportedLangs(): array
    {
        $raw = (string) $this->context->config->get('CONTENT_LANGS');
        return array_values(array_filter(array_map('trim', explode(',', strtolower($raw)))));
    }
}
