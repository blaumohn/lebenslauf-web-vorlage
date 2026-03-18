<?php

namespace App\Http\Security;

use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Storage\FileStorage;

final class IpSaltService
{
    private const LOCK_KEY = 'ip_salt_runtime';

    private RuntimeLockRunner $lockRunner;
    private IpSaltStateStore $stateStore;
    private IpSaltDecisionPolicy $decisionPolicy;
    private IpSaltActionPlan $actionPlan;

    public function __construct(
        FileStorage $storage,
        RuntimeLockRunner $lockRunner,
        RuntimeAtomicWriter $writer,
        string $stateDir,
        string $captchaDir,
        string $rateLimitDir
    ) {
        $this->lockRunner = $lockRunner;
        $this->stateStore = new FileIpSaltStateStore($storage, $writer, $stateDir);
        $validator = new IpSaltStateValidator();
        $this->decisionPolicy = new IpSaltDecisionPolicy($validator);
        $resetExecutor = new IpSaltResetExecutor(
            $this->stateStore,
            $validator,
            $captchaDir,
            $rateLimitDir
        );
        $this->actionPlan = new IpSaltActionPlan($resetExecutor);
    }

    public function resolveSalt(): string
    {
        $result = $this->lockRunner->runWithLock(self::LOCK_KEY, [$this, 'resolveSaltLocked']);
        return $this->requireSalt($result, 'resolveSalt');
    }

    public function resetSalt(): string
    {
        $result = $this->lockRunner->runWithLock(self::LOCK_KEY, [$this, 'resetSaltLocked']);
        return $this->requireSalt($result, 'resetSalt');
    }

    public function resolveSaltLocked(): string
    {
        $state = $this->stateStore->readState();
        $reason = $this->decideResolveReason($state);
        $next = $this->actionPlan->execute($reason, $state);
        return $this->requireSalt($next->salt(), 'resolveSaltLocked');
    }

    public function resetSaltLocked(): string
    {
        $state = $this->stateStore->readState();
        $reason = $this->decisionPolicy->decideForReset();
        $next = $this->actionPlan->execute($reason, $state);
        return $this->requireSalt($next->salt(), 'resetSaltLocked');
    }

    private function decideResolveReason(IpSaltState $state): TriggerReason
    {
        if (!$state->hasReadyMarker()) {
            return TriggerReason::MISSING;
        }
        return $this->decisionPolicy->decideForResolve($state);
    }

    private function requireSalt(mixed $value, string $method): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }
        throw new \RuntimeException("Ungueltiges Ergebnis fuer {$method}.");
    }
}
