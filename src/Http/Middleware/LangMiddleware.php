<?php

namespace App\Http\Middleware;

use App\Http\AppContext;
use App\Http\Lang\RequestLangResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class LangMiddleware implements MiddlewareInterface
{
    private AppContext $context;
    private RequestLangResolver $langResolver;

    public function __construct(AppContext $context)
    {
        $this->context = $context;
        $this->langResolver = new RequestLangResolver($context);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $lang = $this->langResolver->resolve($request);

        if ($lang === null) {
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
}
