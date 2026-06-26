<?php

namespace App\Http\Lang;

/**
 * Lang-Auflösung aus dem Accept-Language-Header.
 *
 * Methoden portiert aus symfony/http-foundation Request::getPreferredLanguage,
 * getLanguages, formatLocale, getLanguageCombinations, getLanguageComponents.
 * Source: https://github.com/symfony/http-foundation/blob/main/Request.php
 * To update: copy the relevant methods and adjust the header-access below.
 */
final class LangResolver
{
    /**
     * Returns the best matching supported language or null if none matches.
     *
     * Abweichungen von Symfony:
     * - leere $locales gibt null zurück (statt erste Browser-Sprache)
     * - kein Match gibt null zurück (statt $locales[0] als Fallback)
     *
     * @param string[] $locales
     */
    public function fromHeader(string $header, array $locales): ?string
    {
        if (!$locales) {
            return null;
        }

        $preferredLanguages = $this->getLanguages($header);

        $locales = array_map($this->formatLocale(...), $locales);
        if (!$preferredLanguages) {
            return null;
        }

        $combinations = array_merge(...array_map($this->getLanguageCombinations(...), $preferredLanguages));
        foreach ($combinations as $combination) {
            foreach ($locales as $locale) {
                if (str_starts_with($locale, $combination)) {
                    return $locale;
                }
            }
        }

        return null;
    }

    /** @return string[] */
    private function getLanguages(string $header): array
    {
        $languages = AcceptHeader::fromString($header)->all();
        $result = [];
        foreach ($languages as $acceptHeaderItem) {
            $result[] = $this->formatLocale($acceptHeaderItem->getValue());
        }
        return array_unique($result);
    }

    private function formatLocale(string $locale): string
    {
        [$language, $script, $region] = $this->getLanguageComponents($locale);

        return implode('_', array_filter([$language, $script, $region]));
    }

    /** @return string[] */
    private function getLanguageCombinations(string $locale): array
    {
        [$language, $script, $region] = $this->getLanguageComponents($locale);

        return array_unique([
            implode('_', array_filter([$language, $script, $region])),
            implode('_', array_filter([$language, $script])),
            implode('_', array_filter([$language, $region])),
            $language,
        ]);
    }

    /** @return array{string, string|null, string|null} */
    private function getLanguageComponents(string $locale): array
    {
        $locale = str_replace('_', '-', strtolower($locale));
        $pattern = '/^([a-zA-Z]{2,3}|i-[a-zA-Z]{5,})(?:-([a-zA-Z]{4}))?(?:-([a-zA-Z]{2}))?(?:-(.+))?$/';
        if (!preg_match($pattern, $locale, $matches)) {
            return [$locale, null, null];
        }
        if (str_starts_with($matches[1], 'i-')) {
            $matches[1] = substr($matches[1], 2);
        }

        return [
            $matches[1],
            isset($matches[2]) ? ucfirst(strtolower($matches[2])) : null,
            isset($matches[3]) ? strtoupper($matches[3]) : null,
        ];
    }
}
