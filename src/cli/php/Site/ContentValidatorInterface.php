<?php

namespace App\Cli\Site;

use Symfony\Component\Console\Output\OutputInterface;

interface ContentValidatorInterface
{
    public function validateContent(OutputInterface $output): bool;

    /** @return list<string> */
    public function schemaNames(): array;
}
