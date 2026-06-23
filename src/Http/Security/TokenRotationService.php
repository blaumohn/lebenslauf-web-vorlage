<?php

namespace App\Http\Security;

use App\Http\SiteHtmlCache;

final class TokenRotationService
{
    public function __construct(
        private readonly SiteHtmlCache $htmlCache,
        private readonly TokenService $tokenService,
    ) {}

    public function rotate(string $profile, int $count): array
    {
        if (!$this->htmlCache->hasPrivate($profile)) {
            throw new \InvalidArgumentException("Profil '{$profile}' hat keine Lebenslauf-Seite.");
        }

        $tokens = $this->tokenService->generateTokens($count);
        $this->tokenService->rotate($profile, $tokens);

        return $tokens;
    }
}
