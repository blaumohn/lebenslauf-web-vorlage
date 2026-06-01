<?php

namespace App\Http\Task\Deploy;

final class SlotSnapshot
{
    public function __construct(
        public readonly string $appLabel,
        public readonly string $vendorLabel,
    ) {
    }
}
