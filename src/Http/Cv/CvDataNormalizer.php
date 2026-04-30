<?php

namespace App\Http\Cv;

final class CvDataNormalizer
{
    private string $lang;

    public function __construct(string $lang = '')
    {
        $this->lang = strtolower(trim($lang));
    }

    public function normalize(array $data): array
    {
        return $this->normalizeValue($data);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        return $this->normalizeArray($value);
    }

    private function normalizeArray(array $value): mixed
    {
        if ($this->isNameObject($value)) {
            return $this->normalizeNameObject($value);
        }

        if ($this->isIntlString($value)) {
            return $this->pickLang($value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeValue($item);
        }
        return $normalized;
    }

    private function normalizeNameObject(array $value): array
    {
        return [
            'voll' => $this->normalizeValue($value['voll'] ?? ''),
            'kurz' => $this->normalizeValue($value['kurz'] ?? ''),
        ];
    }

    private function isNameObject(array $value): bool
    {
        return array_key_exists('voll', $value) || array_key_exists('kurz', $value);
    }

    private function isIntlString(array $value): bool
    {
        if (!$this->isAssoc($value) || !$this->looksLikeLanguageMap($value)) {
            return false;
        }

        return $this->hasPreferredLang($value) || $this->hasSingleLang($value);
    }

    private function pickLang(array $value): string
    {
        if ($this->lang !== '' && isset($value[$this->lang])) {
            return (string) $value[$this->lang];
        }

        if (isset($value['de'])) {
            return (string) $value['de'];
        }

        $first = reset($value);
        return is_string($first) ? $first : '';
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
            if (!preg_match('/^[a-z]{2,5}(-[a-z]{2})?$/i', $key)) {
                return false;
            }
        }
        return count($value) > 0;
    }

    private function hasPreferredLang(array $value): bool
    {
        return ($this->lang !== '' && array_key_exists($this->lang, $value))
            || array_key_exists('de', $value);
    }

    private function hasSingleLang(array $value): bool
    {
        return count($value) === 1;
    }
}
