<?php

namespace App\Cli\Site;

use Symfony\Component\Console\Output\OutputInterface;

interface ContentRendererInterface extends ContentValidatorInterface
{
    public function render(OutputInterface $output): void;

    public function sectionKey(): ?string;
}
