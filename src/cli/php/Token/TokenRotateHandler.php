<?php

namespace App\Cli\Token;

use App\Http\Cv\CvStorage;
use App\Http\Security\TokenService;

final class TokenRotateHandler
{
    public function __construct(
        private readonly CvStorage $cvStorage,
        private readonly TokenService $tokenService,
    ) {}

    public function rotate(string $profile, int $count): array
    {
        if (!$this->cvStorage->hasPrivate($profile)) {
            throw new \InvalidArgumentException("Profil '{$profile}' hat keine Lebenslauf-Seite.");
        }

        $tokens = $this->tokenService->generateTokens($count);
        $this->tokenService->rotate($profile, $tokens);

        return $tokens;
    }
}
