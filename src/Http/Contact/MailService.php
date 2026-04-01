<?php

namespace App\Http\Contact;

use App\Http\ConfigCompiled;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    private ConfigCompiled $config;

    public function __construct(ConfigCompiled $config)
    {
        $this->config = $config;
    }

    public function send(string $replyName, string $replyEmail, string $message): bool
    {
        if ($this->config->requireBool('MAIL_STDOUT')) {
            return $this->sendToStdout($replyName, $replyEmail, $message);
        }

        $to = $this->contactRecipient();
        if ($to === '') {
            return false;
        }

        $mailer = $this->createMailer($replyName, $replyEmail, $to);
        $mailer->Body = $this->buildMessageBody($replyName, $replyEmail, $message);

        return $mailer->send();
    }

    private function sendToStdout(string $replyName, string $replyEmail, string $message): bool
    {
        $payload = "=== CONTACT FORM ===\n";
        $payload .= $this->buildMessageBody($replyName, $replyEmail, $message);
        $stream = fopen('php://stdout', 'wb');
        if ($stream === false) {
            error_log($payload);
        } else {
            fwrite($stream, $payload);
        }
        return true;
    }

    private function buildMessageBody(string $replyName, string $replyEmail, string $message): string
    {
        $payload = "Name: {$replyName}\n";
        $payload .= "E-Mail: {$replyEmail}\n\n";
        $payload .= "Nachricht:\n{$message}\n";
        return $payload;
    }

    private function contactRecipient(): string
    {
        return (string) $this->config->get('CONTACT_TO_EMAIL');
    }

    private function createMailer(string $replyName, string $replyEmail, string $to): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $this->configureSmtp($mailer);

        $fromEmail = $this->resolveFromEmail($to);
        $fromName = $this->config->requireString('SMTP_FROM_NAME');
        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($to);
        $mailer->addReplyTo($replyEmail, $replyName);
        $mailer->Subject = 'Kontaktformular';

        return $mailer;
    }

    private function configureSmtp(PHPMailer $mailer): void
    {
        $smtpHost = (string) $this->config->get('SMTP_HOST');
        if ($smtpHost === '') {
            return;
        }

        $mailer->isSMTP();
        $mailer->Host = $smtpHost;
        $mailer->Port = (int) $this->config->get('SMTP_PORT');
        $mailer->SMTPAuth = true;
        $mailer->Username = (string) $this->config->get('SMTP_USER');
        $mailer->Password = (string) $this->config->get('SMTP_PASS');
        $mailer->SMTPSecure = (string) $this->config->get('SMTP_ENCRYPTION');
    }

    private function resolveFromEmail(string $recipient): string
    {
        $fromEmail = (string) $this->config->get('CONTACT_FROM_EMAIL');
        $fromEmail = $fromEmail !== '' ? $fromEmail : $recipient;
        $configuredFromEmail = (string) $this->config->get('SMTP_FROM_EMAIL');
        return $configuredFromEmail !== '' ? $configuredFromEmail : $fromEmail;
    }
}
