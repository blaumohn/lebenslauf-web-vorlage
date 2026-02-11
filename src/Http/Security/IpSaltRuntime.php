<?php

namespace App\Http\Security;

use App\Http\Storage\FileStorage;

final class IpSaltRuntime
{
    private FileStorage $storage;
    private string $stateDir;
    private string $captchaDir;
    private string $rateLimitDir;

    public function __construct(
        FileStorage $storage,
        string $stateDir,
        string $captchaDir,
        string $rateLimitDir
    ) {
        $this->storage = $storage;
        $this->stateDir = rtrim($stateDir, DIRECTORY_SEPARATOR);
        $this->captchaDir = rtrim($captchaDir, DIRECTORY_SEPARATOR);
        $this->rateLimitDir = rtrim($rateLimitDir, DIRECTORY_SEPARATOR);
    }

    public function resolveSalt(): string
    {
        $lockHandle = $this->acquireLock();
        try {
            return $this->resolveSaltLocked();
        } finally {
            $this->releaseLock($lockHandle);
        }
    }

    public function resetSalt(): string
    {
        $lockHandle = $this->acquireLock();
        try {
            return $this->rotateSaltAndClearState();
        } finally {
            $this->releaseLock($lockHandle);
        }
    }

    private function resolveSaltLocked(): string
    {
        $salt = $this->readSalt();
        if ($salt === null) {
            return $this->rotateSaltAndClearState();
        }
        if ($this->hasValidFingerprint($salt)) {
            return $salt;
        }
        return $this->rotateSaltAndClearState();
    }

    private function hasValidFingerprint(string $salt): bool
    {
        $current = $this->readFingerprint();
        if ($current === null) {
            return false;
        }
        $expected = $this->fingerprintFor($salt);
        return hash_equals($expected, $current);
    }

    private function rotateSaltAndClearState(): string
    {
        $salt = $this->generateSalt();
        $fingerprint = $this->fingerprintFor($salt);
        $this->writeProtectedFile($this->saltPath(), $salt . "\n");
        $this->writeProtectedFile($this->fingerprintPath(), $fingerprint . "\n");
        $this->clearIpState();
        return $salt;
    }

    private function clearIpState(): void
    {
        $this->clearDirFiles($this->captchaDir);
        $this->clearDirFiles($this->rateLimitDir);
    }

    private function clearDirFiles(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (!is_array($items)) {
            throw new \RuntimeException("Verzeichnis nicht lesbar: {$dir}");
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (!is_file($path)) {
                continue;
            }
            if (@unlink($path)) {
                continue;
            }
            throw new \RuntimeException("Datei konnte nicht geloescht werden: {$path}");
        }
    }

    private function acquireLock()
    {
        $this->ensureDir($this->stateDir);
        $handle = fopen($this->lockPath(), 'c');
        if ($handle === false) {
            throw new \RuntimeException('Lock-Datei konnte nicht geoeffnet werden.');
        }
        if (@flock($handle, LOCK_EX)) {
            return $handle;
        }
        fclose($handle);
        throw new \RuntimeException('Lock fuer IP_SALT konnte nicht gesetzt werden.');
    }

    private function releaseLock($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }
        @flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function readSalt(): ?string
    {
        $value = $this->readTrimmed($this->saltPath());
        if ($value === null) {
            return null;
        }
        if ($this->isHexSalt($value)) {
            return $value;
        }
        return null;
    }

    private function readFingerprint(): ?string
    {
        return $this->readTrimmed($this->fingerprintPath());
    }

    private function readTrimmed(string $path): ?string
    {
        $content = $this->storage->readText($path);
        if ($content === null) {
            return null;
        }
        $value = trim($content);
        if ($value === '') {
            return null;
        }
        return $value;
    }

    private function writeProtectedFile(string $path, string $content): void
    {
        $this->ensureDir(dirname($path));
        $tmpPath = $this->buildTmpPath($path);
        $written = file_put_contents($tmpPath, $content);
        if ($written === false) {
            $this->safeUnlink($tmpPath);
            throw new \RuntimeException("Temporare Datei konnte nicht geschrieben werden: {$tmpPath}");
        }
        $this->applySecureMode($tmpPath);
        if (@rename($tmpPath, $path)) {
            $this->applySecureMode($path);
            return;
        }
        $this->safeUnlink($tmpPath);
        throw new \RuntimeException("Datei konnte nicht atomar geschrieben werden: {$path}");
    }

    private function buildTmpPath(string $path): string
    {
        $suffix = bin2hex(random_bytes(6));
        return $path . '.tmp.' . $suffix;
    }

    private function safeUnlink(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function applySecureMode(string $path): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return;
        }
        if (@chmod($path, 0600)) {
            return;
        }
        throw new \RuntimeException("Dateirechte konnten nicht gesetzt werden: {$path}");
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (@mkdir($dir, 0700, true) || is_dir($dir)) {
            return;
        }
        throw new \RuntimeException("Verzeichnis konnte nicht angelegt werden: {$dir}");
    }

    private function isHexSalt(string $value): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $value);
    }

    private function generateSalt(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function fingerprintFor(string $salt): string
    {
        return hash('sha256', 'ip-salt:' . $salt);
    }

    private function lockPath(): string
    {
        return $this->stateDir . DIRECTORY_SEPARATOR . 'ip_salt.lock';
    }

    private function saltPath(): string
    {
        return $this->stateDir . DIRECTORY_SEPARATOR . 'ip_salt.txt';
    }

    private function fingerprintPath(): string
    {
        return $this->stateDir . DIRECTORY_SEPARATOR . 'ip_salt.fingerprint';
    }
}
