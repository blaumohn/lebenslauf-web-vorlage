<?php

namespace App\Http\Security;

interface IpSaltStateStore
{
    public function readState(): IpSaltState;

    public function writeState(IpSaltState $state): void;
}
