<?php

namespace App\Http\Lang;

final class LanguageProjection
{
    private string $language;
    /** @var list<string> */
    private array $availableLanguages;

    /** @param list<string> $availableLanguages */
    public function __construct(string $language, array $availableLanguages)
    {
        $this->language = strtolower(trim($language));
        if ($this->language === '') {
            throw new \InvalidArgumentException('Sprache darf nicht leer sein.');
        }
        $this->availableLanguages = $availableLanguages;
    }

    public function apply(array $data): array
    {
        return $this->applyToValue($data);
    }

    private function applyToValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        return $this->applyToArray($value);
    }

    private function applyToArray(array $value): mixed
    {
        if ($this->isIntlString($value)) {
            return $this->resolveIntlString($value);
        }

        $projected = [];
        foreach ($value as $key => $item) {
            $projected[$key] = $this->applyToValue($item);
        }
        return $projected;
    }

    private function isIntlString(array $value): bool
    {
        return $this->isAssoc($value) && $this->looksLikeLanguageMap($value);
    }

    private function resolveIntlString(array $value): string
    {
        if (isset($value[$this->language])) {
            return (string) $value[$this->language];
        }

        if (isset($value['default'])) {
            return (string) $value['default'];
        }

        throw new \RuntimeException("Übersetzung für Sprache {$this->language} fehlt.");
    }

    private function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function looksLikeLanguageMap(array $value): bool
    {
        foreach ($value as $key => $item) {
            if (!is_string($key) || !is_string($item)) {
                return false;
            }
            if ($key === 'default') {
                continue;
            }
            if (!in_array($key, $this->availableLanguages, true)) {
                return false;
            }
        }
        return count($value) > 0;
    }
}
