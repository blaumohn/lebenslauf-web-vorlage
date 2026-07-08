<?php

namespace App\Http\Actions;

use App\Http\AppContext;
use App\Http\ResponseHelper;
use Psr\Http\Message\ResponseInterface;

abstract class Action
{
    public function __construct(protected AppContext $context)
    {
    }

    protected function renderError(ResponseInterface $response, string $key, string $lang, int $status): ResponseInterface
    {
        $html = $this->context->htmlCache->getErrorFragmentForLang($key, $lang);
        if ($html === null) {
            throw new \RuntimeException("Error-Fragment fehlt ({$key}, {$lang}). ErrorRenderer muss zuerst laufen.");
        }
        return ResponseHelper::html($response, $html, $status);
    }

    protected function notFound(ResponseInterface $response, string $lang): ResponseInterface
    {
        return $this->renderError($response, 'not-found', $lang, 404);
    }
}
