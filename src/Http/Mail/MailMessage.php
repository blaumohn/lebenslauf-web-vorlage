<?php

namespace App\Http\Mail;

final class MailMessage
{
    public function __construct(
        public readonly string $module,
        public readonly string $title,
        public readonly string $body,
    ) {}

    public function subject(string $appName): string
    {
        return "[{$appName}/{$this->module}] {$this->title}";
    }
}
