<?php

namespace App\Http\Security;

final class IpSaltStateValidator
{
    public function isSaltValid(?string $salt): bool
    {
        if (!is_string($salt)) {
            return false;
        }
        return (bool) preg_match('/^[a-f0-9]{32}$/', $salt);
    }

    public function hasMatchingFingerprint(string $salt, ?string $fingerprint): bool
    {
        if (!is_string($fingerprint) || $fingerprint === '') {
            return false;
        }
        $expected = $this->fingerprintFor($salt);
        return hash_equals($expected, $fingerprint);
    }

    public function fingerprintFor(string $salt): string
    {
        return hash('sha256', 'ip-salt:' . $salt);
    }
}
