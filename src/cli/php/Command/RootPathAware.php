<?php

namespace App\Cli\Command;

trait RootPathAware
{
    protected function rootPath(): string
    {
        return dirname(__DIR__, 4);
    }
}
