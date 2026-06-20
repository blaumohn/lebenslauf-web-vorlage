<?php

namespace App\Http\Cv;

use App\Http\Storage\FileStorage;
use Symfony\Component\Filesystem\Path;

final class CvStorage
{
    private FileStorage $storage;
    private string $cacheDir;

    public function __construct(FileStorage $storage, string $cacheDir)
    {
        $this->storage = $storage;
        $this->cacheDir = $cacheDir;
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

    public function saveHeaderFragment(string $html): void
    {
        $this->storage->writeText($this->headerFragmentPath(), $html);
    }

    public function saveHeaderFragmentForLang(string $lang, string $html): void
    {
        $this->storage->writeText($this->headerFragmentPath($lang), $html);
    }

    public function getHeaderFragment(): ?string
    {
        return $this->storage->readText($this->headerFragmentPath());
    }

    public function getHeaderFragmentForLang(string $lang): ?string
    {
        return $this->readWithFallback(
            $this->headerFragmentPath($lang),
            $this->headerFragmentPath()
        );
    }

    public function saveFooterFragment(string $html): void
    {
        $this->storage->writeText($this->footerFragmentPath(), $html);
    }

    public function saveFooterFragmentForLang(string $lang, string $html): void
    {
        $this->storage->writeText($this->footerFragmentPath($lang), $html);
    }

    public function getFooterFragment(): ?string
    {
        return $this->storage->readText($this->footerFragmentPath());
    }

    public function getFooterFragmentForLang(string $lang): ?string
    {
        return $this->readWithFallback(
            $this->footerFragmentPath($lang),
            $this->footerFragmentPath()
        );
    }

    public function saveCvFooterFragment(string $html): void
    {
        $this->storage->writeText($this->cvFooterFragmentPath(), $html);
    }

    public function saveCvFooterFragmentForLang(string $lang, string $html): void
    {
        $this->storage->writeText($this->cvFooterFragmentPath($lang), $html);
    }

    public function getCvFooterFragment(): ?string
    {
        return $this->storage->readText($this->cvFooterFragmentPath());
    }

    public function getCvFooterFragmentForLang(string $lang): ?string
    {
        return $this->readWithFallback(
            $this->cvFooterFragmentPath($lang),
            $this->cvFooterFragmentPath()
        );
    }

    public function hasPublic(): bool
    {
        if (is_file($this->publicPath())) {
            return true;
        }

        $pattern = Path::join($this->cacheDir, 'cv-public.*.html');
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

    private function cvFooterFragmentPath(?string $lang = null): string
    {
        $suffix = $this->langSuffix($lang);
        if ($suffix !== '') {
            return Path::join($this->cacheDir, 'cv-footer.' . $suffix . '.html');
        }
        return Path::join($this->cacheDir, 'cv-footer.html');
    }

    private function footerFragmentPath(?string $lang = null): string
    {
        $suffix = $this->langSuffix($lang);
        if ($suffix !== '') {
            return Path::join($this->cacheDir, 'site-footer.' . $suffix . '.html');
        }
        return Path::join($this->cacheDir, 'site-footer.html');
    }

    private function headerFragmentPath(?string $lang = null): string
    {
        $suffix = $this->langSuffix($lang);
        if ($suffix !== '') {
            return Path::join($this->cacheDir, 'site-header.' . $suffix . '.html');
        }
        return Path::join($this->cacheDir, 'site-header.html');
    }

    private function publicPath(?string $lang = null): string
    {
        $suffix = $this->langSuffix($lang);
        if ($suffix !== '') {
            return Path::join($this->cacheDir, 'cv-public.' . $suffix . '.html');
        }

        return Path::join($this->cacheDir, 'cv-public.html');
    }

    private function privatePath(string $profile, ?string $lang = null): string
    {
        $suffix = $this->langSuffix($lang);
        if ($suffix !== '') {
            return Path::join($this->cacheDir, 'cv-private-' . $profile . '.' . $suffix . '.html');
        }

        return Path::join($this->cacheDir, 'cv-private-' . $profile . '.html');
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
