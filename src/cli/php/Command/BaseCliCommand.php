<?php

namespace App\Cli\Command;

use App\Cli\CliContext;
use Symfony\Component\Console\Command\Command;

abstract class BaseCliCommand extends Command
{
    public function __construct(
        protected CliContext $context
    ) {
        parent::__construct();
    }

    protected function appRoot(): string
    {
        return $this->context->appRoot();
    }
}
