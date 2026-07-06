<?php

namespace App\Http\Security;

use App\Http\SiteHtmlCache;

final class CvTokenSubjectResolver implements TokenSubjectResolver
{
    public function __construct(
        private readonly SiteHtmlCache $htmlCache,
    ) {}

    public function exists(string $profile): bool
    {
        return $this->htmlCache->hasPrivate($profile);
    }
}
