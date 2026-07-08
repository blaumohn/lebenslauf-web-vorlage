<?php

namespace App\Http\Task;

final class TaskResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $body,
        public readonly string $mailExtra = '',
    ) {}

    public static function ok(string $body, string $mailExtra = ''): self
    {
        return new self(true, $body, $mailExtra);
    }

    public static function fail(string $body): self
    {
        return new self(false, $body);
    }
}
