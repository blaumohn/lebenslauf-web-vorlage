<?php

namespace App\Http\Middleware;

use App\Http\AppContext;
use App\Http\Lang\RequestLangResolver;
use App\Http\Url\QueryString;
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
            return $this->langSelectResponse($request);
        }

        return $handler->handle($request->withAttribute('lang', $lang));
    }

    private function langSelectResponse(ServerRequestInterface $request): ResponseInterface
    {
        $messageHtml = $this->context->htmlCache->getLangSelectMessage();
        $title = $this->context->htmlCache->getLangSelectTitle();
        if ($messageHtml === null || $title === null) {
            throw new \RuntimeException('Lang-Select-Fragment fehlt. LangSelectRenderer muss zuerst laufen.');
        }

        $html = $this->context->twig->render('lang-select.html.twig', [
            'title' => $title,
            'message_html' => $messageHtml,
            'lang_items' => $this->buildLangItems($request),
        ]);

        $response = $this->context->responseFactory->createResponse(300);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /** @return list<array{code: string, href: string}> */
    private function buildLangItems(ServerRequestInterface $request): array
    {
        $currentParams = $request->getQueryParams();
        unset($currentParams['lang']);

        $pairs = [];
        foreach ($currentParams as $key => $value) {
            $pairs[] = [(string) $key, (string) $value];
        }

        $items = [];
        foreach ($this->langResolver->supportedLangs() as $lang) {
            $items[] = [
                'code' => strtoupper($lang),
                'href' => '?' . QueryString::build([...$pairs, ['lang', $lang]]),
            ];
        }
        return $items;
    }
}
