<?php

namespace App\Cli;

final class CliContext
{
    public function __construct(
        private string $appRoot
    ) {
        $this->appRoot = $appRoot;
    }

    public function appRoot(): string
    {
        return $this->appRoot;
    }
}
