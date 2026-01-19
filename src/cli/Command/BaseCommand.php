<?php

namespace App\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    protected function rootPath(): string
    {
        return dirname(__DIR__, 3);
    }

    protected function applyAppEnvFromArg(InputInterface $input): ?string
    {
        $profile = trim((string) $input->getArgument('profile'));
        if ($profile === '') {
            return null;
        }
        $this->setAppEnv($profile);
        return $profile;
    }

    protected function setPhaseEnv(string $phase): void
    {
        putenv('PHASE=' . $phase);
    }

    protected function setAppEnv(string $value): void
    {
        putenv('APP_ENV=' . $value);
    }
}
