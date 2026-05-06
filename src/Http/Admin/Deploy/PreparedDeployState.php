<?php

namespace App\Http\Admin\Deploy;

final class PreparedDeployState
{
    private const VALID_SLOTS = ['a', 'b'];

    private function __construct(
        private readonly string $tree,
        private readonly string $vendor,
    ) {}

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Prepared-State nicht gefunden: {$path}");
        }
        $data = parse_ini_file($path, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Prepared-State nicht lesbar: {$path}");
        }
        $section = $data['prepared'] ?? [];
        $tree = (string) ($section['tree'] ?? '');
        $vendor = (string) ($section['vendor'] ?? '');
        if (!self::isValidSlot($tree) || !self::isValidSlot($vendor)) {
            throw new \RuntimeException("Prepared-State enthält ungültige Slots: {$path}");
        }
        return new self($tree, $vendor);
    }

    public function toIni(): string
    {
        return "[state]\ntree={$this->tree}\nvendor={$this->vendor}\n";
    }

    private static function isValidSlot(string $slot): bool
    {
        return in_array($slot, self::VALID_SLOTS, true);
    }
}
