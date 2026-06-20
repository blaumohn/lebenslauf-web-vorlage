<?php

namespace App\Http;

use App\Http\Storage\StorageException;
use App\Http\View\PageViewBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Throwable;

final class ErrorHandler
{
    private AppContext $context;

    public function __construct(AppContext $context)
    {
        $this->context = $context;
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        if ($logErrors) {
            $this->context->logger->error($exception->getMessage(), [
                'url'       => (string) $request->getUri(),
                'exception' => $exception,
            ]);
        }

        $message = 'Ein unerwarteter Fehler ist aufgetreten.';

        if ($exception instanceof StorageException) {
            $message = 'Datei konnte nicht gespeichert werden. Bitte Dateirechte prüfen.';
        }

        if ($displayErrorDetails) {
            $message .= ' ' . $exception->getMessage();
        }

        $lang = strtolower(trim((string) $this->context->config->get('CONTENT_LANG_DEFAULT')));
        if ($lang === '') {
            throw new \RuntimeException('Konfiguration fehlt: CONTENT_LANG_DEFAULT');
        }
        $base = PageViewBuilder::base(
            $this->context->cvStorage->getHeaderFragmentForLang($lang),
            $this->context->cvStorage->getFooterFragmentForLang($lang)
        );
        $html = $this->context->twig->render('error.html.twig', [
            'title' => 'Serverfehler',
            'message' => $message,
        ] + $base);

        return ResponseHelper::html(new Response(), $html, 500);
    }
}
