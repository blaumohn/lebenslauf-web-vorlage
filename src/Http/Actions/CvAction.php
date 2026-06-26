<?php

namespace App\Http\Actions;

use App\Http\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CvAction extends Action
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $lang = (string) $request->getAttribute('lang');
        $params = $request->getQueryParams();
        $token = isset($params['token']) ? (string) $params['token'] : '';

        if ($token !== '') {
            return $this->handlePrivateCv($response, $params, $token, $lang);
        }

        return $this->handlePublicCv($response, $lang);
    }

    private function handlePrivateCv(
        ResponseInterface $response,
        array $params,
        string $token,
        string $lang,
    ): ResponseInterface {
        $profile = isset($params['profile']) ? (string) $params['profile'] : '';
        if ($profile === '') {
            $profile = $this->context->tokenService->findProfileForToken($token) ?? '';
        }

        if ($profile === '' || !$this->context->tokenService->verify($profile, $token)) {
            return $this->renderError($response, 'Zugriff verweigert', 'Token ungültig oder abgelaufen.', 403);
        }

        $html = $this->context->htmlCache->getPrivateHtmlForLang($profile, $lang);
        if ($html === null) {
            return $this->notFound($response, 'Privater Lebenslauf noch nicht vorhanden.');
        }

        return ResponseHelper::html($response, $html);
    }

    private function handlePublicCv(ResponseInterface $response, string $lang): ResponseInterface
    {
        $html = $this->context->htmlCache->getPublicHtmlForLang($lang);
        if ($html === null) {
            return $this->notFound($response, 'Öffentlicher Lebenslauf noch nicht vorhanden.');
        }

        return ResponseHelper::html($response, $html);
    }
}
