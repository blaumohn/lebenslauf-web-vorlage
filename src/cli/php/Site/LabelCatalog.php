<?php

namespace App\Cli\Site;

use App\Http\Lang\I18nResourceInterface;
use App\Http\Lang\LanguageProjection;

final class LabelCatalog implements I18nResourceInterface
{
    /** @var list<string> */
    private array $availableLanguages;

    public function __construct(private array $labels)
    {
        $this->availableLanguages = $this->collectAvailableLanguages();
    }

    public static function fromJsonFile(string $path): self
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Label-Katalog konnte nicht gelesen werden: {$path}");
        }
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException("Label-Katalog enthält kein Objekt: {$path}");
        }
        return new self($data);
    }

    public function labels(): array
    {
        return $this->labels;
    }

    public function forLanguage(string $language): array
    {
        return (new LanguageProjection($language, $this->languages()))
            ->apply($this->labels);
    }

    /** @return list<string> */
    public function languages(): array
    {
        return $this->availableLanguages;
    }

    /** @return list<string> */
    private function collectAvailableLanguages(): array
    {
        $languages = [];
        foreach ($this->labels as $group) {
            if (is_array($group)) {
                $this->collectLanguages($group, $languages);
            }
        }
        sort($languages);
        return array_values(array_unique($languages));
    }

    /** @param list<string> $languages */
    private function collectLanguages(array $group, array &$languages): void
    {
        $value = $group['value'] ?? null;
        if (is_array($value)) {
            foreach (array_keys($value) as $language) {
                if (is_string($language) && $language !== 'default') {
                    $languages[] = $language;
                }
            }
        }

        $children = $group['childLabels'] ?? [];
        if (!is_array($children)) {
            return;
        }
        foreach ($children as $child) {
            if (is_array($child)) {
                $this->collectLanguages($child, $languages);
            }
        }
    }
}
