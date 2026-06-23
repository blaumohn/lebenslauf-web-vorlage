<?php

namespace App\Http\Actions;

use App\Http\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HomeAction extends Action
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $lang = (string) $request->getAttribute('lang');
        $html = $this->context->htmlCache->getHomeHtmlForLang($lang);
        if ($html === null) {
            return $this->notFound($response, 'Die Startseite wurde noch nicht erstellt.');
        }
        return ResponseHelper::html($response, $html);
    }
}
