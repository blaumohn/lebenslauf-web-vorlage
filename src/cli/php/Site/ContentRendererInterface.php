<?php

namespace App\Cli\Site;

use Symfony\Component\Console\Output\OutputInterface;

interface ContentRendererInterface
{
    public function render(OutputInterface $output): void;
}
