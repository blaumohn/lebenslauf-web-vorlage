<?php

namespace App\Cli\Token;

use App\Http\SiteHtmlCache;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\CvTokenSubjectResolver;
use App\Http\Security\TokenIssuanceService;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;

final class LocalTokenCli
{
    /** @return non-empty-string Klartext-Token */
    public function add(string $appRoot, string $profile, ?int $expiresAt, ?string $label = null): string
    {
        return $this->buildService($appRoot)->add($profile, $expiresAt, $label);
    }

    /** @return list<array{hash: string, label: string|null, created_at: int, expires_at: int|null}> */
    public function list(string $appRoot, string $profile): array
    {
        return $this->buildService($appRoot)->list($profile);
    }

    public function revoke(string $appRoot, string $profile, string $identifier): int
    {
        return $this->buildService($appRoot)->revoke($profile, $identifier);
    }

    private function buildService(string $appRoot): TokenIssuanceService
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($appRoot . '/var/state/locks');
        $writer = new RuntimeAtomicWriter();
        $htmlCache = new SiteHtmlCache($storage, $appRoot . '/var/cache/html');
        $tokenService = new TokenService($storage, $lockRunner, $writer, $appRoot . '/var/state/tokens');
        return new TokenIssuanceService(new CvTokenSubjectResolver($htmlCache), $tokenService);
    }
}
