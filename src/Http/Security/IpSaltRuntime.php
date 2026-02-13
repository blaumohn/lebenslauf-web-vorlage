<?php

namespace App\Http\Security;

use App\Http\Storage\FileStorage;

final class IpSaltRuntime
{
    private IpSaltService $service;

    public function __construct(
        FileStorage $storage,
        RuntimeLockRunner $lockRunner,
        RuntimeAtomicWriter $writer,
        string $stateDir,
        string $captchaDir,
        string $rateLimitDir
    ) {
        $this->service = new IpSaltService(
            $storage,
            $lockRunner,
            $writer,
            $stateDir,
            $captchaDir,
            $rateLimitDir
        );
    }

    public function resolveSalt(): string
    {
        return $this->service->resolveSalt();
    }

    public function resetSalt(): string
    {
        return $this->service->resetSalt();
    }

    public function resolveSaltLocked(): string
    {
        return $this->service->resolveSaltLocked();
    }

    public function resetSaltLocked(): string
    {
        return $this->service->resetSaltLocked();
    }
}
