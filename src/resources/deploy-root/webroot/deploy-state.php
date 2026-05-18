<?php

if (class_exists('DeployRuntimeState', false)) {
    return;
}

final class DeployRuntimeSlot
{
    private const VALID_LABELS = ['a', 'b'];

    private function __construct(
        private readonly string $kind,
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
        return "{$this->kind}-{$this->label}";
    }

    private static function validLabel(string $label): string
    {
        if (in_array($label, self::VALID_LABELS, true)) {
            return $label;
        }
        throw new \RuntimeException("Ungültiger Slot: {$label}");
    }
}

final class DeployRuntimeState
{
    public function __construct(
        private readonly DeployRuntimeSlot $appSlot,
        private readonly DeployRuntimeSlot $vendorSlot,
    ) {
    }

    public static function fromLabels(string $app, string $vendor): self
    {
        return new self(
            DeployRuntimeSlot::app($app),
            DeployRuntimeSlot::vendor($vendor),
        );
    }

    public static function fromIniFile(string $path): self
    {
        $data = is_file($path) ? parse_ini_file($path, true) : [];
        $data = is_array($data) ? $data : [];
        $section = $data['state'] ?? [];
        $section = is_array($section) ? $section : [];
        return self::fromLabels(
            (string) ($section['app'] ?? 'a'),
            (string) ($section['vendor'] ?? 'a'),
        );
    }

    public function appLabel(): string
    {
        return $this->appSlot->label();
    }

    public function vendorLabel(): string
    {
        return $this->vendorSlot->label();
    }

    public function appDir(): string
    {
        return $this->appSlot->directory();
    }

    public function vendorDir(): string
    {
        return $this->vendorSlot->directory();
    }

    public function toIni(): string
    {
        return "[state]\n"
            . "app={$this->appLabel()}\n"
            . "vendor={$this->vendorLabel()}\n";
    }
}
