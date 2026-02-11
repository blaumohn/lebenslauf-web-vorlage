<?php

namespace App\Cli;

use App\Cli\Command\BuildCommand;
use App\Cli\Command\CaptchaCommand;
use App\Cli\Command\ConfigCommand;
use App\Cli\Command\IpHashCommand;
use App\Cli\Util\PythonCommand;
use App\Cli\Command\RunCommand;
use App\Cli\Command\SetupCommand;
use App\Cli\Command\TokenCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('lebenslauf-cli', '1.0.0');
        $this->registerCommands();
    }

    private function registerCommands(): void
    {
        $this->add(new SetupCommand());
        $this->add(new BuildCommand());
        $this->add(new PythonCommand());
        $this->add(new RunCommand());
        $this->add(new TokenCommand());
        $this->add(new CaptchaCommand());
        $this->add(new ConfigCommand());
        $this->add(new IpHashCommand());
    }
}
