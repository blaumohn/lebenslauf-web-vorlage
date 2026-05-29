<?php

namespace App\Cli;

final class CliContext
{
    public function __construct(
        private string $appRoot
    ) {
        $this->appRoot = rtrim($this->appRoot, DIRECTORY_SEPARATOR);
    }

    public function appRoot(): string
    {
        return $this->appRoot;
    }
}
