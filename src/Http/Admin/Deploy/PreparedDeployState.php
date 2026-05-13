<?php

namespace App\Http\Admin\Deploy;

final class PreparedDeployState
{
    private const VALID_SLOTS = ['a', 'b'];

    private function __construct(
        private readonly string $app,
        private readonly string $vendor,
    ) {}

    public static function fromParams(string $app, string $vendor): self
    {
        if (!self::isValidSlot($app) || !self::isValidSlot($vendor)) {
            throw new \RuntimeException("Ungültige Slots: app={$app}, vendor={$vendor}");
        }
        return new self($app, $vendor);
    }

    public function toIni(): string
    {
        return "[state]\napp={$this->app}\nvendor={$this->vendor}\n";
    }

    private static function isValidSlot(string $slot): bool
    {
        return in_array($slot, self::VALID_SLOTS, true);
    }
}
