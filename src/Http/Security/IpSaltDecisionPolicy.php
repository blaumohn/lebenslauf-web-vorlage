<?php

namespace App\Http\Security;

final class IpSaltDecisionPolicy
{
    private IpSaltStateValidator $validator;

    public function __construct(IpSaltStateValidator $validator)
    {
        $this->validator = $validator;
    }

    public function decideForResolve(IpSaltState $state): TriggerReason
    {
        $salt = $state->salt();
        if ($salt === null) {
            return TriggerReason::MISSING;
        }
        if (!$this->validator->isSaltValid($salt)) {
            return TriggerReason::INVALID;
        }
        if ($this->validator->hasMatchingFingerprint($salt, $state->fingerprint())) {
            return TriggerReason::CLEAN;
        }
        return TriggerReason::MISMATCH;
    }

    public function decideForReset(): TriggerReason
    {
        return TriggerReason::EXPLICIT_RESET;
    }
}
