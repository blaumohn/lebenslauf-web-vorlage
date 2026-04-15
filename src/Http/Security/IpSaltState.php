<?php

namespace App\Http\Security;

final class IpSaltState
{
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_READY = 'READY';

    private ?string $salt;
    private ?string $fingerprint;
    private ?string $status;
    private int $generation;

    public function __construct(?string $salt, ?string $fingerprint, ?string $status, int $generation)
    {
        $this->salt = $salt;
        $this->fingerprint = $fingerprint;
        $this->status = $status;
        $this->generation = $generation;
    }

    public function salt(): ?string
    {
        return $this->salt;
    }

    public function fingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function status(): ?string
    {
        return $this->status;
    }

    public function generation(): int
    {
        return $this->generation;
    }

    public function hasReadyMarker(): bool
    {
        return $this->status === self::STATUS_READY && $this->generation > 0;
    }

    public function nextGeneration(): int
    {
        if ($this->generation > 0) {
            return $this->generation + 1;
        }
        return 1;
    }

    public function withMarker(string $status, int $generation): self
    {
        return new self($this->salt, $this->fingerprint, $status, $generation);
    }
}
