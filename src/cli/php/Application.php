<?php

namespace App\Cli;

use App\Cli\Command\BuildCommand;
use App\Cli\Command\CaptchaCommand;
use App\Cli\Command\CiCommand;
use App\Cli\Command\ConfigCommand;
use App\Cli\Command\IpHashCommand;
use App\Cli\Command\PublishCommand;
use App\Cli\Command\StartCommand;
use App\Cli\Command\SetupCommand;
use App\Cli\Command\TokenCommand;
use App\Cli\Util\PythonCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct(
        private CliContext $context
    ) {
        parent::__construct('lebenslauf-cli', '1.0.0');
        $this->registerCommands();
    }

    private function registerCommands(): void
    {
        $this->addCommand(new SetupCommand($this->context));
        $this->addCommand(new StartCommand($this->context));
        $this->addCommand(new BuildCommand($this->context));
        $this->addCommand(new CiCommand($this->context));
        $this->addCommand(new PythonCommand($this->context));
        $this->addCommand(new TokenCommand($this->context));
        $this->addCommand(new CaptchaCommand($this->context));
        $this->addCommand(new ConfigCommand($this->context));
        $this->addCommand(new IpHashCommand($this->context));
        $this->addCommand(new PublishCommand($this->context));
    }
}
