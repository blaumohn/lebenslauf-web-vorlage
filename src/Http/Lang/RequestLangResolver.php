<?php

namespace App\Http\Lang;

use App\Http\AppContext;
use Psr\Http\Message\ServerRequestInterface;

final class RequestLangResolver
{
    public function __construct(private readonly AppContext $context) {}

    public function resolve(ServerRequestInterface $request): ?string
    {
        $supported = $this->supportedLangs();
        $lang = strtolower(trim((string) ($request->getQueryParams()['lang'] ?? '')));

        if ($lang !== '' && in_array($lang, $supported, true)) {
            return $lang;
        }

        return $this->context->langResolver->fromHeader($request->getHeaderLine('Accept-Language'), $supported);
    }

    /** @return list<string> */
    public function supportedLangs(): array
    {
        $raw = (string) $this->context->config->get('CONTENT_LANGS');
        return array_values(array_filter(array_map('trim', explode(',', strtolower($raw)))));
    }
}
