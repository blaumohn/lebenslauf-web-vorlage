<?php

namespace App\Http\Captcha;

use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Storage\FileStorage;

final class CaptchaService
{
    private FileStorage $storage;
    private RuntimeLockRunner $lockRunner;
    private RuntimeAtomicWriter $writer;
    private string $dir;
    private int $ttlSeconds;

    public function __construct(
        FileStorage $storage,
        RuntimeLockRunner $lockRunner,
        RuntimeAtomicWriter $writer,
        string $dir,
        int $ttlSeconds
    ) {
        $this->storage = $storage;
        $this->lockRunner = $lockRunner;
        $this->writer = $writer;
        $this->dir = rtrim($dir, DIRECTORY_SEPARATOR);
        $this->ttlSeconds = $ttlSeconds;
        $this->storage->ensureDir($this->dir);
    }

    public function createChallenge(string $ipHash): array
    {
        $id = bin2hex(random_bytes(16)) . '_' . time();
        $solution = $this->generateSolution();
        $now = time();
        $data = [
            'captcha_id' => $id,
            'solution_text' => $solution,
            'ip_hash' => $ipHash,
            'created_at' => $now,
            'expires_at' => $now + $this->ttlSeconds,
            'used_at' => null,
            'fail_count' => 0,
        ];

        $this->writeJson($this->pathFor($id), $data);
        return $data;
    }

    public function getChallenge(string $id): ?array
    {
        return $this->loadActiveChallenge($id);
    }

    public function verify(string $id, string $answer, string $ipHash): bool
    {
        $locked = fn() => $this->verifyLocked($id, $answer, $ipHash);
        return (bool) $this->lockRunner->runWithLock('captcha_' . $id, $locked);
    }

    public function parseIdTimestamp(string $id): ?int
    {
        $pos = strrpos($id, '_');
        if ($pos === false) {
            return null;
        }
        $ts = substr($id, $pos + 1);
        return ctype_digit($ts) ? (int) $ts : null;
    }

    public function cleanupExpired(): int
    {
        $files = glob($this->dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $count = 0;
        foreach ($files as $file) {
            $data = $this->storage->readJson($file);
            if (!$data) {
                continue;
            }
            if ($this->shouldDelete($data)) {
                $this->storage->delete($file);
                $count++;
            }
        }

        return $count;
    }

    public function renderPng(string $text): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return '';
        }

        $width = 160;
        $height = 50;
        $image = imagecreatetruecolor($width, $height);

        $bg = imagecolorallocate($image, 245, 245, 245);
        $fg = imagecolorallocate($image, 30, 30, 30);
        $noise = imagecolorallocate($image, 180, 180, 180);

        imagefilledrectangle($image, 0, 0, $width, $height, $bg);

        for ($i = 0; $i < 50; $i++) {
            imageline(
                $image,
                rand(0, $width),
                rand(0, $height),
                rand(0, $width),
                rand(0, $height),
                $noise
            );
        }

        imagestring($image, 5, 20, 15, $text, $fg);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        return $png === false ? '' : $png;
    }

    private function verifyLocked(string $id, string $answer, string $ipHash): bool
    {
        $path = $this->pathFor($id);
        $data = $this->loadActiveChallenge($id);
        if ($data === null) {
            return false;
        }

        if (!hash_equals($data['ip_hash'] ?? '', $ipHash)) {
            return false;
        }

        $expected = strtoupper((string) ($data['solution_text'] ?? ''));
        $actual = strtoupper(trim($answer));

        if (!hash_equals($expected, $actual)) {
            $data['fail_count'] = (int) ($data['fail_count'] ?? 0) + 1;
            $this->writeJson($path, $data);
            return false;
        }

        $data['used_at'] = time();
        $this->writeJson($path, $data);
        return true;
    }

    private function writeJson(string $path, array $data): void
    {
        $encoded = json_encode($data);
        if (!is_string($encoded)) {
            throw new \RuntimeException("CAPTCHA-Datei konnte nicht serialisiert werden: {$path}");
        }
        $this->writer->writeText($path, $encoded . "\n");
    }

    private function pathFor(string $id): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . $id . '.json';
    }

    private function loadChallenge(string $id): ?array
    {
        $data = $this->storage->readJson($this->pathFor($id));
        return $data ?: null;
    }

    private function loadActiveChallenge(string $id): ?array
    {
        $data = $this->loadChallenge($id);
        if ($data === null || $this->isInactive($data)) {
            return null;
        }

        return $data;
    }

    private function generateSolution(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $solution = '';
        for ($i = 0; $i < 6; $i++) {
            $solution .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $solution;
    }

    private function isExpired(array $data): bool
    {
        return (int) ($data['expires_at'] ?? 0) < time();
    }

    private function isUsed(array $data): bool
    {
        return !empty($data['used_at']);
    }

    private function isInactive(array $data): bool
    {
        return $this->isExpired($data) || $this->isUsed($data);
    }

    private function shouldDelete(array $data): bool
    {
        return $this->isExpired($data) || $this->isUsed($data);
    }
}
