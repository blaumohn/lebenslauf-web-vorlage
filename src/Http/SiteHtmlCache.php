<?php

namespace App\Http;

use App\Http\Storage\FileStorage;
use Symfony\Component\Filesystem\Path;

final class SiteHtmlCache
{
    private FileStorage $storage;
    private string $cacheDir;

    public function __construct(FileStorage $storage, string $cacheDir)
    {
        $this->storage = $storage;
        $this->cacheDir = $cacheDir;
        $this->storage->ensureDir($this->cacheDir);
    }

    public function getHomeHtmlForLang(string $lang): ?string
    {
        return $this->storage->readText(Path::join($this->cacheDir, 'home', $lang, 'index.html'));
    }

    public function getBlogIndexHtmlForLang(string $lang): ?string
    {
        return $this->storage->readText(Path::join($this->cacheDir, 'blog', $lang, 'index.html'));
    }

    public function getBlogPostHtmlForLang(string $lang, string $slug): ?string
    {
        return $this->storage->readText(Path::join($this->cacheDir, 'blog', $lang, $slug . '.html'));
    }

    public function getPublicHtmlForLang(string $lang): ?string
    {
        return $this->storage->readText($this->publicPath($lang));
    }

    public function getPrivateHtmlForLang(string $profile, string $lang): ?string
    {
        return $this->storage->readText($this->privatePath($profile, $lang));
    }

    public function savePublicHtmlForLang(string $html, string $lang): void
    {
        $this->storage->writeText($this->publicPath($lang), $html);
    }

    public function savePrivateHtmlForLang(string $profile, string $html, string $lang): void
    {
        $this->storage->writeText($this->privatePath($profile, $lang), $html);
    }

    public function hasPrivate(string $profile): bool
    {
        $pattern = Path::join($this->cacheDir, 'cv-private-' . $profile . '.*.html');
        $matches = glob($pattern);
        return is_array($matches) && count($matches) > 0;
    }

    public function saveHeaderFragmentForLang(string $lang, string $html): void
    {
        $this->storage->writeText($this->headerFragmentPath($lang), $html);
    }

    public function getHeaderFragmentForLang(string $lang): ?string
    {
        return $this->storage->readText($this->headerFragmentPath($lang));
    }

    public function saveFooterFragmentForLang(string $lang, string $html): void
    {
        $this->storage->writeText($this->footerFragmentPath($lang), $html);
    }

    public function getFooterFragmentForLang(string $lang): ?string
    {
        return $this->storage->readText($this->footerFragmentPath($lang));
    }

    public function saveCvFooterFragmentForLang(string $lang, string $html): void
    {
        $this->storage->writeText($this->cvFooterFragmentPath($lang), $html);
    }

    public function getCvFooterFragmentForLang(string $lang): ?string
    {
        return $this->storage->readText($this->cvFooterFragmentPath($lang));
    }

    public function savePublicHtmlWithNoticeForLang(string $html, string $lang): void
    {
        $this->storage->writeText($this->publicWithNoticePath($lang), $html);
    }

    public function getPublicHtmlWithNoticeForLang(string $lang): ?string
    {
        return $this->storage->readText($this->publicWithNoticePath($lang));
    }

    public function saveLangSelectMessage(string $html): void
    {
        $this->storage->writeText($this->langSelectMessagePath(), $html);
    }

    public function getLangSelectMessage(): ?string
    {
        return $this->storage->readText($this->langSelectMessagePath());
    }

    public function saveLangSelectTitle(string $title): void
    {
        $this->storage->writeText($this->langSelectTitlePath(), $title);
    }

    public function getLangSelectTitle(): ?string
    {
        return $this->storage->readText($this->langSelectTitlePath());
    }

    public function saveErrorFragmentForLang(string $key, string $lang, string $html): void
    {
        $this->storage->writeText($this->errorFragmentPath($key, $lang), $html);
    }

    public function getErrorFragmentForLang(string $key, string $lang): ?string
    {
        return $this->storage->readText($this->errorFragmentPath($key, $lang));
    }

    public function hasPublic(): bool
    {
        $pattern = Path::join($this->cacheDir, 'cv-public.*.html');
        $matches = glob($pattern);
        return is_array($matches) && count($matches) > 0;
    }

    public function hasPublicForLang(string $lang): bool
    {
        return is_file($this->publicPath($lang));
    }

    private function langSelectMessagePath(): string
    {
        return Path::join($this->cacheDir, 'lang-select-message.html');
    }

    private function langSelectTitlePath(): string
    {
        return Path::join($this->cacheDir, 'lang-select-title.txt');
    }

    private function errorFragmentPath(string $key, string $lang): string
    {
        return Path::join($this->cacheDir, 'error-' . $key . '.' . $this->langSuffix($lang) . '.html');
    }

    private function cvFooterFragmentPath(string $lang): string
    {
        return Path::join($this->cacheDir, 'cv-footer.' . $this->langSuffix($lang) . '.html');
    }

    private function footerFragmentPath(string $lang): string
    {
        return Path::join($this->cacheDir, 'site-footer.' . $this->langSuffix($lang) . '.html');
    }

    private function headerFragmentPath(string $lang): string
    {
        return Path::join($this->cacheDir, 'site-header.' . $this->langSuffix($lang) . '.html');
    }

    private function publicPath(string $lang): string
    {
        return Path::join($this->cacheDir, 'cv-public.' . $this->langSuffix($lang) . '.html');
    }

    private function publicWithNoticePath(string $lang): string
    {
        return Path::join($this->cacheDir, 'cv-public-token-expired.' . $this->langSuffix($lang) . '.html');
    }

    private function privatePath(string $profile, string $lang): string
    {
        return Path::join($this->cacheDir, 'cv-private-' . $profile . '.' . $this->langSuffix($lang) . '.html');
    }

    private function langSuffix(string $lang): string
    {
        $normalized = strtolower(trim($lang));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized);
        return trim((string) $normalized, '-');
    }
}
