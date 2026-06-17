<?php

namespace App\Http\Security;

use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Storage\FileStorage;
use Symfony\Component\Filesystem\Path;

final class TokenService
{
    private FileStorage $storage;
    private RuntimeLockRunner $lockRunner;
    private RuntimeAtomicWriter $writer;
    private string $dir;

    public function __construct(
        FileStorage $storage,
        RuntimeLockRunner $lockRunner,
        RuntimeAtomicWriter $writer,
        string $dir
    ) {
        $this->storage = $storage;
        $this->lockRunner = $lockRunner;
        $this->writer = $writer;
        $this->dir = $dir;
        $this->storage->ensureDir($this->dir);
    }

    public function verify(string $profile, string $token): bool
    {
        $hash = $this->hashToken($token);
        $list = $this->readHashes($profile);
        return in_array($hash, $list, true);
    }

    public function findProfileForToken(string $token): ?string
    {
        $hash = $this->hashToken($token);
        $files = glob(Path::join($this->dir, '*.txt')) ?: [];
        foreach ($files as $file) {
            $profile = basename($file, '.txt');
            $list = $this->readHashes($profile);
            if (in_array($hash, $list, true)) {
                return $profile;
            }
        }

        return null;
    }

    public function rotate(string $profile, array $plainTokens): void
    {
        if (!$this->isValidProfileName($profile)) {
            throw new \InvalidArgumentException("Profilname ungültig: '{$profile}'.");
        }
        $locked = fn() => $this->rotateLocked($profile, $plainTokens);
        $this->lockRunner->runWithLock('token_' . $profile, $locked);
    }

    private function isValidProfileName(string $profile): bool
    {
        return $profile !== '' && (bool) preg_match('/^[A-Za-z0-9_.-]+$/', $profile);
    }

    public function generateTokens(int $count): array
    {
        $tokens = [];
        for ($i = 0; $i < $count; $i++) {
            $tokens[] = bin2hex(random_bytes(16));
        }
        return $tokens;
    }

    public function readHashes(string $profile): array
    {
        $path = $this->tokenPath($profile);
        $content = $this->storage->readText($path);
        if ($content === null) {
            return [];
        }

        $lines = array_filter(array_map('trim', explode("\n", $content)));
        return array_values(array_unique($lines));
    }

    private function rotateLocked(string $profile, array $plainTokens): void
    {
        $hashes = array_map(fn ($token) => $this->hashToken($token), $plainTokens);
        $content = implode("\n", $hashes) . "\n";
        $this->writer->writeText($this->tokenPath($profile), $content);
    }

    private function tokenPath(string $profile): string
    {
        return Path::join($this->dir, $profile . '.txt');
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
