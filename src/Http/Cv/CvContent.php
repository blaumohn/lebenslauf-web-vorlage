<?php

namespace App\Http\Cv;

use App\Http\Lang\I18nResourceInterface;
use App\Http\Lang\LanguageProjection;

final class CvContent implements I18nResourceInterface
{
    /** @param list<string> $availableLanguages */
    public function __construct(
        private array $data,
        private array $availableLanguages,
    ) {}

    public function forLanguage(string $language): array
    {
        return (new LanguageProjection($language, $this->availableLanguages))
            ->apply($this->data);
    }
}
