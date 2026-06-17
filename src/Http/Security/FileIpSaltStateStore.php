<?php

namespace App\Http\Security;

use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Storage\FileStorage;
use Symfony\Component\Filesystem\Path;

final class FileIpSaltStateStore implements IpSaltStateStore
{
    private const STATE_FILE = 'ip_salt.state.json';

    private FileStorage $storage;
    private RuntimeAtomicWriter $writer;
    private string $stateDir;

    public function __construct(
        FileStorage $storage,
        RuntimeAtomicWriter $writer,
        string $stateDir
    ) {
        $this->storage = $storage;
        $this->writer = $writer;
        $this->stateDir = $stateDir;
    }

    public function readState(): IpSaltState
    {
        $raw = $this->storage->readText($this->statePath());
        if ($raw === null) {
            return $this->emptyState();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $this->emptyState();
        }
        $salt = $this->readStringField($decoded, 'salt');
        $fingerprint = $this->readStringField($decoded, 'fingerprint');
        $status = $this->readStatus($decoded);
        $generation = $this->readGeneration($decoded);
        return new IpSaltState($salt, $fingerprint, $status, $generation);
    }

    public function writeState(IpSaltState $state): void
    {
        $payload = $this->buildPayload($state);
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new \RuntimeException('IP-Salt-State konnte nicht serialisiert werden.');
        }
        $this->writer->writeText($this->statePath(), $encoded . "\n");
    }

    private function buildPayload(IpSaltState $state): array
    {
        return [
            'salt' => $state->salt(),
            'fingerprint' => $state->fingerprint(),
            'status' => $state->status(),
            'generation' => $state->generation(),
            'updated_at' => gmdate('c'),
        ];
    }

    private function readStringField(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        return $trimmed;
    }

    private function readStatus(array $payload): ?string
    {
        $status = $this->readStringField($payload, 'status');
        if ($status === IpSaltState::STATUS_IN_PROGRESS) {
            return $status;
        }
        if ($status === IpSaltState::STATUS_READY) {
            return $status;
        }
        return null;
    }

    private function readGeneration(array $payload): int
    {
        $value = $payload['generation'] ?? null;
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (!is_string($value) || !ctype_digit($value)) {
            return 0;
        }
        $parsed = (int) $value;
        if ($parsed > 0) {
            return $parsed;
        }
        return 0;
    }

    private function emptyState(): IpSaltState
    {
        return new IpSaltState(null, null, null, 0);
    }

    private function statePath(): string
    {
        return Path::join($this->stateDir, self::STATE_FILE);
    }
}
