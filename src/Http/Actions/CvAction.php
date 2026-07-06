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

        $tokenState = isset($params['token_state']) ? (string) $params['token_state'] : '';
        return $this->handlePublicCv($response, $lang, $tokenState === 'expired');
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

        if ($profile !== '' && $this->context->tokenService->verify($profile, $token)) {
            return $this->servePrivateCv($response, $profile, $lang);
        }

        if ($this->context->tokenService->isExpired($token)) {
            return ResponseHelper::redirect($response, "/cv?lang={$lang}&token_state=expired");
        }

        return $this->renderError($response, 'Zugriff verweigert', 'Token ungültig.', 403);
    }

    private function servePrivateCv(ResponseInterface $response, string $profile, string $lang): ResponseInterface
    {
        $html = $this->context->htmlCache->getPrivateHtmlForLang($profile, $lang);
        if ($html === null) {
            return $this->notFound($response, 'Privater Lebenslauf noch nicht vorhanden.');
        }

        return ResponseHelper::html($response, $html);
    }

    private function handlePublicCv(ResponseInterface $response, string $lang, bool $showExpiredNotice): ResponseInterface
    {
        $html = $showExpiredNotice
            ? $this->context->htmlCache->getPublicHtmlWithNoticeForLang($lang)
            : $this->context->htmlCache->getPublicHtmlForLang($lang);
        if ($html === null) {
            return $this->notFound($response, 'Öffentlicher Lebenslauf noch nicht vorhanden.');
        }

        return ResponseHelper::html($response, $html);
    }
}
