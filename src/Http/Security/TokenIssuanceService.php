<?php

namespace App\Http\Security;

final class TokenIssuanceService
{
    public function __construct(
        private readonly TokenSubjectResolver $subjectResolver,
        private readonly TokenService $tokenService,
    ) {}

    /**
     * @param non-empty-string $profile
     * @param positive-int $count
     * @param non-empty-string|null $label
     * @return list<string> Klartext-Token
     */
    public function add(string $profile, int $count, ?int $expiresAt, ?string $label = null): array
    {
        $this->assertSubjectExists($profile);
        return $this->tokenService->add($profile, $count, $expiresAt, $label);
    }

    /**
     * @param non-empty-string $profile
     * @return list<array{hash: string, label: string|null, created_at: int, expires_at: int|null}>
     */
    public function list(string $profile): array
    {
        return $this->tokenService->list($profile);
    }

    /** @param non-empty-string $profile */
    public function revoke(string $profile, string $identifier): int
    {
        return $this->tokenService->revoke($profile, $identifier);
    }

    /** @param non-empty-string $profile */
    public function revokeAll(string $profile): void
    {
        $this->tokenService->revokeAll($profile);
    }

    private function assertSubjectExists(string $profile): void
    {
        if (!$this->subjectResolver->exists($profile)) {
            throw new \InvalidArgumentException("Profil '{$profile}' hat keine Seite.");
        }
    }
}
