<?php

namespace App\Http\Admin;

final class AdminTask
{
    private function __construct(
        private readonly string $type,
        private readonly array $data,
        private readonly string $filePath,
    ) {}

    public static function fromFile(string $filePath): self
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException("Admin-Task nicht gefunden: {$filePath}");
        }
        $parsed = parse_ini_file($filePath, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException("Admin-Task nicht lesbar: {$filePath}");
        }
        $section = $parsed['task'] ?? [];
        $type = (string) ($section['type'] ?? '');
        if ($type === '') {
            throw new \RuntimeException("Admin-Task fehlt type: {$filePath}");
        }
        return new self($type, $section, $filePath);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function get(string $key): string
    {
        return (string) ($this->data[$key] ?? '');
    }

    public function filePath(): string
    {
        return $this->filePath;
    }
}
