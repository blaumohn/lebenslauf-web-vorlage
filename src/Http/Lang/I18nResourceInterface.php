<?php

namespace App\Http\Lang;

interface I18nResourceInterface
{
    public function forLanguage(string $language): array;
}
