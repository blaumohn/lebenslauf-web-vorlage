<?php

namespace App\Http\Actions;

use App\Http\AppContext;
use App\Http\Mail\MailMessage;
use App\Http\ResponseHelper;
use App\Http\View\PageViewBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ContactSubmitAction
{
    private AppContext $context;

    public function __construct(AppContext $context)
    {
        $this->context = $context;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $trustProxy = $this->context->config->getBool('TRUST_PROXY', false);
        $ip = $this->context->ipResolver->resolve($request, $trustProxy);
        $ipHash = $this->context->ipHashService->hashIp($ip);

        $window = $this->context->config->requireInt('RATE_LIMIT_WINDOW_SECONDS');
        $maxPost = $this->context->config->requireInt('CONTACT_MAX_POST');

        if (!$this->context->rateLimiter->allow('contact_post_' . $ipHash, $maxPost, $window)) {
            return $this->renderRateLimit($response);
        }

        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];
        $form = $this->extractFormData($data);

        $emailValid = $this->isEmailValid($form['email']);
        $captchaOk = $this->isCaptchaValid($form, $ipHash);

        if ($this->isFormInvalid($form, $emailValid, $captchaOk)) {
            return $this->renderFormError($response, $ipHash, $form, $captchaOk);
        }

        $sent = $this->context->mailService->send($this->buildContactMessage($form));
        if (!$sent) {
            return $this->renderContactForm(
                $response,
                $ipHash,
                $this->formValues($form),
                'Versand fehlgeschlagen. Bitte später erneut versuchen.',
                500
            );
        }

        $base = PageViewBuilder::base();
        $html = $this->context->twig->render('contact_ok.html.twig', [
            'title' => 'Kontakt',
        ] + $base);

        return ResponseHelper::html($response, $html);
    }

    private function buildContactMessage(array $form): MailMessage
    {
        $body = "Name: {$form['name']}\n"
            . "E-Mail: {$form['email']}\n\n"
            . "Nachricht:\n{$form['message']}";
        return new MailMessage(
            module: 'Contact',
            title: 'Neue Nachricht von ' . $form['name'],
            body: $body,
        );
    }

    private function renderRateLimit(ResponseInterface $response): ResponseInterface
    {
        $base = PageViewBuilder::base();
        $html = $this->context->twig->render('error.html.twig', [
            'title' => 'Zu viele Anfragen',
            'message' => 'Bitte später erneut versuchen.',
        ] + $base);
        return ResponseHelper::html($response, $html, 429);
    }

    private function extractFormData(array $data): array
    {
        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'message' => trim((string) ($data['message'] ?? '')),
            'captcha_id' => trim((string) ($data['captcha_id'] ?? '')),
            'captcha_answer' => trim((string) ($data['captcha_answer'] ?? '')),
        ];
    }

    private function isEmailValid(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function isCaptchaValid(array $form, string $ipHash): bool
    {
        if ($form['captcha_id'] === '' || $form['captcha_answer'] === '') {
            return false;
        }

        return $this->context->captchaService->verify(
            $form['captcha_id'],
            $form['captcha_answer'],
            $ipHash
        );
    }

    private function isFormInvalid(array $form, bool $emailValid, bool $captchaOk): bool
    {
        return $form['name'] === '' || !$emailValid || $form['message'] === '' || !$captchaOk;
    }

    private function formValues(array $form): array
    {
        return [
            'name' => $form['name'],
            'email' => $form['email'],
            'message' => $form['message'],
        ];
    }

    private function renderFormError(
        ResponseInterface $response,
        string $ipHash,
        array $form,
        bool $captchaOk
    ): ResponseInterface {
        $isDeployHint = !$captchaOk && $this->isLikelyDeployCase($form['captcha_id']);
        $error = $isDeployHint
            ? 'Die Seite wurde aktualisiert. Bitte das Formular erneut absenden.'
            : 'Bitte alle Felder korrekt ausfüllen.';
        $status = $isDeployHint ? 200 : 403;
        return $this->renderContactForm($response, $ipHash, $this->formValues($form), $error, $status);
    }

    private function isLikelyDeployCase(string $captchaId): bool
    {
        $ts = $this->context->captchaService->parseIdTimestamp($captchaId);
        if ($ts === null) {
            return false;
        }
        $ttl = $this->context->config->requireInt('CAPTCHA_TTL_SECONDS');
        if ((time() - $ts) > $ttl) {
            return false;
        }
        return $this->context->captchaService->getChallenge($captchaId) === null;
    }

    private function renderContactForm(
        ResponseInterface $response,
        string $ipHash,
        array $form,
        string $error,
        int $status
    ): ResponseInterface {
        $base = PageViewBuilder::base();
        $challenge = $this->context->captchaService->createChallenge($ipHash);
        $captchaId = $challenge['captcha_id'];
        $captchaUrl = '/captcha.png?id=' . urlencode($captchaId);
        $html = $this->context->twig->render('contact.html.twig', [
            'title' => 'Kontakt',
            'captcha_id' => $captchaId,
            'captcha_url' => $captchaUrl,
            'show_error' => true,
            'error_text' => $error,
            'form_values' => $form,
        ] + $base);
        return ResponseHelper::html($response, $html, $status);
    }
}
