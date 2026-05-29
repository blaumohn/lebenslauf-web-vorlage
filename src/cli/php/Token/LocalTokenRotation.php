<?php

namespace App\Cli\Token;

use App\Http\Cv\CvStorage;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\TokenRotationService;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;

final class LocalTokenRotation
{
    public function rotate(string $appRoot, string $profile, int $count): array
    {
        return $this->buildService($appRoot)->rotate($profile, $count);
    }

    private function buildService(string $appRoot): TokenRotationService
    {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($appRoot . '/var/state/locks');
        $writer = new RuntimeAtomicWriter();
        $cvStorage = new CvStorage($storage, $appRoot . '/var/cache/html');
        $tokenService = new TokenService($storage, $lockRunner, $writer, $appRoot . '/var/state/tokens');
        return new TokenRotationService($cvStorage, $tokenService);
    }
}
