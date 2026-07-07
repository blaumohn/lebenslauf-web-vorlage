<?php

namespace App\Cli\Site;

use Symfony\Component\Console\Output\OutputInterface;

interface ContentValidatorInterface
{
    public function validateContent(OutputInterface $output): bool;

    public function schemaName(): ?string;
}
