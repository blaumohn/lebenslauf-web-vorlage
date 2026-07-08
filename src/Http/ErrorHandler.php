<?php

namespace App\Http;

use App\Http\Lang\RequestLangResolver;
use App\Http\Storage\StorageException;
use App\Http\View\PageViewBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;
use Throwable;

final class ErrorHandler
{
    private AppContext $context;
    private RequestLangResolver $langResolver;

    public function __construct(AppContext $context)
    {
        $this->context = $context;
        $this->langResolver = new RequestLangResolver($context);
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

        $lang = $this->resolveLang($request);

        if ($exception instanceof HttpNotFoundException) {
            return $this->renderCachedError('not-found', $lang, 404);
        }

        return $this->renderServerError($exception, $displayErrorDetails, $lang);
    }

    private function renderServerError(Throwable $exception, bool $displayErrorDetails, string $lang): ResponseInterface
    {
        $message = 'Ein unerwarteter Fehler ist aufgetreten.';

        if ($exception instanceof StorageException) {
            $message = 'Datei konnte nicht gespeichert werden. Bitte Dateirechte prüfen.';
        }

        if ($displayErrorDetails) {
            $message .= ' ' . $exception->getMessage();
        }

        $base = PageViewBuilder::base(
            $this->context->htmlCache->getHeaderFragmentForLang($lang),
            $this->context->htmlCache->getFooterFragmentForLang($lang)
        );
        $html = $this->context->twig->render('error.html.twig', [
            'title' => 'Serverfehler',
            'message' => $message,
        ] + $base);

        return ResponseHelper::html(new Response(), $html, 500);
    }

    private function renderCachedError(string $key, string $lang, int $status): ResponseInterface
    {
        $html = $this->context->htmlCache->getErrorFragmentForLang($key, $lang);
        if ($html === null) {
            throw new \RuntimeException("Error-Fragment fehlt ({$key}, {$lang}). ErrorRenderer muss zuerst laufen.");
        }
        return ResponseHelper::html(new Response(), $html, $status);
    }

    private function resolveLang(ServerRequestInterface $request): string
    {
        $attribute = $request->getAttribute('lang');
        if (is_string($attribute) && $attribute !== '') {
            return $attribute;
        }

        $resolved = $this->langResolver->resolve($request);
        if ($resolved !== null) {
            return $resolved;
        }

        return $this->langResolver->supportedLangs()[0] ?? 'de';
    }
}
