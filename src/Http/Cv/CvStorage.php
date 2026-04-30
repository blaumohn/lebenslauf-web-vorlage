<?php

namespace App\Http\Cv;

use App\Http\Storage\FileStorage;

final class CvStorage
{
    private FileStorage $storage;
    private string $cacheDir;

    public function __construct(FileStorage $storage, string $cacheDir)
    {
        $this->storage = $storage;
        $this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
        $this->storage->ensureDir($this->cacheDir);
    }

    public function getPublicHtml(): ?string
    {
        return $this->storage->readText($this->publicPath());
    }

    public function getPublicHtmlForLang(?string $lang): ?string
    {
        return $this->readWithFallback($this->publicPath($lang), $this->publicPath());
    }

    public function getPrivateHtml(string $profile): ?string
    {
        return $this->storage->readText($this->privatePath($profile));
    }

    public function getPrivateHtmlForLang(string $profile, ?string $lang): ?string
    {
        return $this->readWithFallback(
            $this->privatePath($profile, $lang),
            $this->privatePath($profile)
        );
    }

    public function savePublicHtml(string $html): void
    {
        $this->storage->writeText($this->publicPath(), $html);
    }

    public function savePublicHtmlForLang(string $html, ?string $lang): void
    {
        $this->storage->writeText($this->publicPath($lang), $html);
    }

    public function savePrivateHtml(string $profile, string $html): void
    {
        $this->storage->writeText($this->privatePath($profile), $html);
    }

    public function savePrivateHtmlForLang(string $profile, string $html, ?string $lang): void
    {
        $this->storage->writeText($this->privatePath($profile, $lang), $html);
    }

    public function hasPrivate(string $profile): bool
    {
        return is_file($this->privatePath($profile));
    }

    public function hasPublic(): bool
    {
        if (is_file($this->publicPath())) {
            return true;
        }

        $pattern = $this->cacheDir . DIRECTORY_SEPARATOR . 'cv-public.*.html';
        $matches = glob($pattern);
        return is_array($matches) && count($matches) > 0;
    }

    public function hasPublicForLang(?string $lang): bool
    {
        $path = $this->publicPath($lang);
        if (is_file($path)) {
            return true;
        }

        return is_file($this->publicPath());
    }

    private function publicPath(?string $lang = null): string
    {
        $suffix = $this->langSuffix($lang);
        if ($suffix !== '') {
            return $this->cacheDir . DIRECTORY_SEPARATOR . 'cv-public.' . $suffix . '.html';
        }

        return $this->cacheDir . DIRECTORY_SEPARATOR . 'cv-public.html';
    }

    private function privatePath(string $profile, ?string $lang = null): string
    {
        $suffix = $this->langSuffix($lang);
        if ($suffix !== '') {
            return $this->cacheDir . DIRECTORY_SEPARATOR . 'cv-private-' . $profile . '.' . $suffix . '.html';
        }

        return $this->cacheDir . DIRECTORY_SEPARATOR . 'cv-private-' . $profile . '.html';
    }

    private function langSuffix(?string $lang): string
    {
        $lang = $lang === null ? '' : trim($lang);
        if ($lang === '') {
            return '';
        }

        $normalized = strtolower($lang);
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized);
        return trim((string) $normalized, '-');
    }

    private function readWithFallback(string $primaryPath, ?string $fallbackPath = null): ?string
    {
        $html = $this->storage->readText($primaryPath);
        if ($html !== null || $fallbackPath === null) {
            return $html;
        }

        return $this->storage->readText($fallbackPath);
    }
}
