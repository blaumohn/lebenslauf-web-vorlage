<?php

namespace App\Http\Task;

final class QueuedTask
{
    private function __construct(
        private readonly string $type,
        private readonly array $data,
    ) {}

    public static function fromParsed(string $type, array $data): self
    {
        return new self($type, $data);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function get(string $key): string
    {
        return (string) ($this->data[$key] ?? '');
    }
}
