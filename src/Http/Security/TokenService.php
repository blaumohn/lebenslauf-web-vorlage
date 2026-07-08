<?php

namespace App\Http\Security;

use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Storage\FileStorage;
use Symfony\Component\Filesystem\Path;

/**
 * @phpstan-type TokenEntry array{hash: string, label: string|null, created_at: int, expires_at: int|null}
 */
final class TokenService
{
    public function __construct(
        private readonly FileStorage $storage,
        private readonly RuntimeLockRunner $lockRunner,
        private readonly RuntimeAtomicWriter $writer,
        private readonly string $dir,
    ) {
        $this->storage->ensureDir($this->dir);
    }

    public function verify(string $profile, string $token): bool
    {
        return $this->hasActiveHash($profile, $this->hashToken($token));
    }

    public function findProfileForToken(string $token): ?string
    {
        $hash = $this->hashToken($token);
        foreach ($this->profileNames() as $profile) {
            if ($this->hasActiveHash($profile, $hash)) {
                return $profile;
            }
        }
        return null;
    }

    /**
     * True, wenn der Token einmal gültig war (Hash bekannt), aber inzwischen
     * abgelaufen ist. Unterscheidet "abgelaufen" von "nie gültig".
     */
    public function isExpired(string $token): bool
    {
        $entry = $this->findEntryByHash($this->hashToken($token));
        if ($entry === null) {
            return false;
        }
        return $entry['expires_at'] !== null && $entry['expires_at'] <= time();
    }

    private function hasActiveHash(string $profile, string $hash): bool
    {
        foreach ($this->activeEntries($profile) as $entry) {
            if ($entry['hash'] === $hash) {
                return true;
            }
        }
        return false;
    }

    /** @return TokenEntry|null */
    private function findEntryByHash(string $hash): ?array
    {
        foreach ($this->allEntries() as $entry) {
            if ($entry['hash'] === $hash) {
                return $entry;
            }
        }
        return null;
    }

    /** @return list<TokenEntry> */
    private function allEntries(): array
    {
        $entries = [];
        foreach ($this->profileNames() as $profile) {
            array_push($entries, ...$this->readEntries($profile));
        }
        return $entries;
    }

    /**
     * Erzeugt einen neuen Token und hängt ihn an bestehende Freigaben des
     * Profils an, ohne diese zu entfernen.
     *
     * @param non-empty-string $profile
     * @param int|null $expiresAt Unix-Zeitstempel, ab dem der Token abläuft; null für kein Ablaufdatum
     * @param non-empty-string|null $label Frei wählbare Bezeichnung
     * @return non-empty-string Klartext-Token
     */
    public function add(string $profile, ?int $expiresAt, ?string $label = null): string
    {
        if (!$this->isValidProfileName($profile)) {
            throw new \InvalidArgumentException("Profilname ungültig: '{$profile}'.");
        }

        $token = $this->generateToken();
        $newEntry = [
            'hash' => $this->hashToken($token),
            'label' => $label,
            'created_at' => time(),
            'expires_at' => $expiresAt,
        ];

        $locked = function () use ($profile, $newEntry): void {
            $entries = array_merge($this->readEntries($profile), [$newEntry]);
            $this->writeEntries($profile, $entries);
        };
        $this->lockRunner->runWithLock('token_' . $profile, $locked);

        return $token;
    }

    /**
     * @param non-empty-string $profile
     * @return list<TokenEntry>
     */
    public function list(string $profile): array
    {
        return $this->readEntries($profile);
    }

    /**
     * Entfernt gezielt Freigaben, deren Hash mit $identifier beginnt oder deren
     * Label exakt übereinstimmt.
     *
     * @param non-empty-string $profile
     * @param non-empty-string $identifier Hash-Präfix oder Label
     * @return int Anzahl entfernter Einträge
     */
    public function revoke(string $profile, string $identifier): int
    {
        $locked = fn (): int => $this->revokeLocked($profile, $identifier);
        return $this->lockRunner->runWithLock('token_' . $profile, $locked);
    }

    private function revokeLocked(string $profile, string $identifier): int
    {
        $remaining = [];
        $removed = 0;
        foreach ($this->readEntries($profile) as $entry) {
            if ($this->matchesIdentifier($entry, $identifier)) {
                $removed++;
                continue;
            }
            $remaining[] = $entry;
        }
        $this->writeEntries($profile, $remaining);
        return $removed;
    }

    /** @param TokenEntry $entry */
    private function matchesIdentifier(array $entry, string $identifier): bool
    {
        if ($entry['label'] !== null && $entry['label'] === $identifier) {
            return true;
        }
        return $identifier !== '' && str_starts_with($entry['hash'], $identifier);
    }

    /** @return list<TokenEntry> */
    private function activeEntries(string $profile): array
    {
        $now = time();
        return array_values(array_filter(
            $this->readEntries($profile),
            fn (array $entry): bool => $entry['expires_at'] === null || $entry['expires_at'] > $now
        ));
    }

    /** @return list<string> */
    private function profileNames(): array
    {
        $files = glob(Path::join($this->dir, '*.json')) ?: [];
        return array_map(fn (string $file): string => basename($file, '.json'), $files);
    }

    /** @return list<TokenEntry> */
    private function readEntries(string $profile): array
    {
        $data = $this->storage->readJson($this->entriesPath($profile));
        if ($data === null) {
            return [];
        }

        $entries = [];
        foreach ($data as $item) {
            if (!is_array($item) || !isset($item['hash'])) {
                continue;
            }
            $entries[] = [
                'hash' => (string) $item['hash'],
                'label' => isset($item['label']) && $item['label'] !== null ? (string) $item['label'] : null,
                'created_at' => (int) ($item['created_at'] ?? 0),
                'expires_at' => isset($item['expires_at']) && $item['expires_at'] !== null
                    ? (int) $item['expires_at']
                    : null,
            ];
        }
        return $entries;
    }

    /** @param list<TokenEntry> $entries */
    private function writeEntries(string $profile, array $entries): void
    {
        $encoded = json_encode(array_values($entries), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($encoded)) {
            throw new \RuntimeException("Token-Liste konnte nicht serialisiert werden: {$profile}");
        }
        $this->writer->writeText($this->entriesPath($profile), $encoded . "\n");
    }

    private function entriesPath(string $profile): string
    {
        return Path::join($this->dir, $profile . '.json');
    }

    private function isValidProfileName(string $profile): bool
    {
        return $profile !== '' && (bool) preg_match('/^[A-Za-z0-9_.-]+$/', $profile);
    }

    /** @return non-empty-string */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
