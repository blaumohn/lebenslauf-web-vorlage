<?php

namespace App\Cli\Command;

use App\Cli\Util\PythonRunner;

trait PythonRunnerAware
{
    protected function pythonRunner(): PythonRunner
    {
        return new PythonRunner($this->rootPath());
    }
}
