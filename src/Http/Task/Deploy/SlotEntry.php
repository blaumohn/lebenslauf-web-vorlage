<?php

namespace App\Http\Task\Deploy;

final class SlotEntry
{
    private const VALID_LABELS = ['a', 'b'];

    private function __construct(
        private readonly string $slotType,
        private readonly string $label,
    ) {
    }

    public static function app(string $label): self
    {
        return new self('app', self::validLabel($label));
    }

    public static function vendor(string $label): self
    {
        return new self('vendor', self::validLabel($label));
    }

    public function label(): string
    {
        return $this->label;
    }

    public function directory(): string
    {
        return "{$this->slotType}-{$this->label}";
    }

    public function runMarkerPath(): string
    {
        return $this->directory() . '/.deploy-run';
    }

    private static function validLabel(string $label): string
    {
        if (in_array($label, self::VALID_LABELS, true)) {
            return $label;
        }
        throw new \RuntimeException("Ungültiger Slot: {$label}");
    }
}
