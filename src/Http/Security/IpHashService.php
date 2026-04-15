<?php

namespace App\Http\Security;

final class IpHashService
{
    private string $salt;

    public function __construct(string $salt)
    {
        $this->salt = $salt;
    }

    public function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, $this->salt);
    }
}
