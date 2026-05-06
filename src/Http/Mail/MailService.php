<?php

namespace App\Http\Mail;

use App\Http\ConfigCompiled;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    public function __construct(private readonly ConfigCompiled $config) {}

    public function send(MailMessage $message): bool
    {
        $to = $this->config->requireString('MAIL_TO_EMAIL');
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('MAIL_TO_EMAIL ist keine gültige E-Mail-Adresse.');
        }
        if ($this->config->requireBool('MAIL_STDOUT')) {
            return $this->sendToStdout($message, $to);
        }
        return $this->createMailer($message, $to)->send();
    }

    private function sendToStdout(MailMessage $message, string $to): bool
    {
        $appName = $this->config->requireString('SMTP_FROM_NAME');
        $payload = "=== MAIL ===\n"
            . "To: {$to}\n"
            . "Subject: {$message->subject($appName)}\n\n"
            . $message->body . "\n";
        $stream = fopen('php://output', 'wb');
        if ($stream === false) {
            error_log($payload);
            return true;
        }
        fwrite($stream, $payload);
        return true;
    }

    private function createMailer(MailMessage $message, string $to): PHPMailer
    {
        $appName = $this->config->requireString('SMTP_FROM_NAME');
        $mailer = new PHPMailer(true);
        $this->configureSmtp($mailer);
        $mailer->setFrom($this->config->requireString('SMTP_FROM_EMAIL'), $appName);
        $mailer->addAddress($to);
        $mailer->Subject = $message->subject($appName);
        $mailer->Body = $message->body;
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
        $encryption = (string) $this->config->get('SMTP_ENCRYPTION');
        $mailer->SMTPSecure = $encryption === 'none' ? '' : $encryption;
    }
}
