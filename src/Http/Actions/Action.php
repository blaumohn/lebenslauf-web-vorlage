<?php

namespace App\Http\Actions;

use App\Http\AppContext;
use App\Http\ResponseHelper;
use App\Http\View\PageViewBuilder;
use Psr\Http\Message\ResponseInterface;

abstract class Action
{
    public function __construct(protected AppContext $context)
    {
    }

    protected function renderError(ResponseInterface $response, string $title, string $message, int $status): ResponseInterface
    {
        $html = $this->context->twig->render('error.html.twig', [
            'title' => $title,
            'message' => $message,
        ] + PageViewBuilder::base(null, null));
        return ResponseHelper::html($response, $html, $status);
    }

    protected function notFound(ResponseInterface $response, string $message = 'Seite nicht gefunden.'): ResponseInterface
    {
        return $this->renderError($response, 'Nicht gefunden', $message, 404);
    }
}
