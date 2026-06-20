<?php

namespace App\Http\Actions;

use App\Http\AppContext;
use App\Http\ResponseHelper;
use App\Http\View\PageViewBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HomeAction
{
    private AppContext $context;

    public function __construct(AppContext $context)
    {
        $this->context = $context;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $lang = $this->defaultLang();
        $hasPublic = $this->context->cvStorage->hasPublic();
        $publicMessage = $hasPublic
            ? 'Der öffentliche Lebenslauf ist verfügbar.'
            : 'Noch kein öffentlicher Lebenslauf vorhanden.';
        $base = PageViewBuilder::base(
            $this->context->cvStorage->getHeaderFragmentForLang($lang),
            $this->context->cvStorage->getFooterFragmentForLang($lang)
        );
        $html = $this->context->twig->render('home.html.twig', [
            'title' => 'Home',
            'public_cv_message' => $publicMessage,
        ] + $base);

        return ResponseHelper::html($response, $html);
    }

    private function defaultLang(): string
    {
        $raw = strtolower(trim((string) $this->context->config->get('CONTENT_LANG_DEFAULT')));
        if ($raw === '') {
            throw new \RuntimeException('Konfiguration fehlt: CONTENT_LANG_DEFAULT');
        }
        return $raw;
    }
}
