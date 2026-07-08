<?php

namespace App\Http\Actions;

use App\Http\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class BlogAction extends Action
{
    public function invokeIndex(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $lang = (string) $request->getAttribute('lang');
        $html = $this->context->htmlCache->getBlogIndexHtmlForLang($lang);
        if ($html === null) {
            return $this->notFound($response, $lang);
        }
        return ResponseHelper::html($response, $html);
    }

    public function invokePost(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $lang = (string) $request->getAttribute('lang');
        $slug = $this->normalizeSlug((string) ($args['slug'] ?? ''));
        if ($slug === '') {
            return $this->notFound($response, $lang);
        }
        $html = $this->context->htmlCache->getBlogPostHtmlForLang($lang, $slug);
        if ($html === null) {
            return $this->notFound($response, $lang);
        }
        return ResponseHelper::html($response, $html);
    }

    private function normalizeSlug(string $slug): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
    }
}
