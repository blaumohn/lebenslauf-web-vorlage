<?php

namespace App\Http\Task\Deploy;

final class CurrentSlotReader
{
    private const PATTERNS_FILE = __DIR__ . '/../../../resources/slot-patterns.json';

    private string $appPattern;
    private string $vendorPattern;

    public function __construct(private readonly string $deployRoot)
    {
        $patterns = $this->loadPatterns();
        $this->appPattern    = '#' . $patterns['app']    . '#';
        $this->vendorPattern = '#' . $patterns['vendor'] . '#';
    }

    public function readCurrent(): ?SlotSnapshot
    {
        $htaccess = $this->readFile('.htaccess');
        if ($htaccess === null) {
            return null;
        }
        $appLabel = $this->matchPattern($htaccess, $this->appPattern);
        if ($appLabel === null) {
            return null;
        }
        $indexPhp = $this->readFile("app-{$appLabel}/public/index.php");
        if ($indexPhp === null) {
            return null;
        }
        $vendorLabel = $this->matchPattern($indexPhp, $this->vendorPattern);
        if ($vendorLabel === null) {
            return null;
        }
        return new SlotSnapshot($appLabel, $vendorLabel);
    }

    private function loadPatterns(): array
    {
        $raw = file_get_contents(self::PATTERNS_FILE);
        if ($raw === false) {
            throw new \RuntimeException('slot-patterns.json nicht lesbar: ' . self::PATTERNS_FILE);
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (empty($data['app']) || empty($data['vendor'])) {
            throw new \RuntimeException('slot-patterns.json ungültig: ' . self::PATTERNS_FILE);
        }
        return $data;
    }

    private function readFile(string $relPath): ?string
    {
        $full = $this->deployRoot . '/' . $relPath;
        if (!is_file($full)) {
            return null;
        }
        $content = file_get_contents($full);
        return $content !== false ? $content : null;
    }

    private function matchPattern(string $content, string $pattern): ?string
    {
        if (!preg_match($pattern, $content, $m)) {
            return null;
        }
        return $m[1];
    }
}
