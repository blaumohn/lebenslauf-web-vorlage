<?php

namespace App\Cli\Command;

use App\Cli\Site\SiteValidateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'template', description: 'Vorlage-Operationen (validate, ...).')]
final class TemplateCommand extends BasePipelinePhaseCommand
{
    protected function commandPhase(): string
    {
        return 'build';
    }

    protected function configurePipelineCommand(): void
    {
        $this->addArgument('task', InputArgument::OPTIONAL, 'Subtask (validate)');
    }

    protected function runPipelineCommand(InputInterface $input, OutputInterface $output): int
    {
        $task = strtolower(trim((string) $input->getArgument('task')));
        if ($task === 'validate') {
            return $this->runValidate($output);
        }
        $output->writeln('<error>Usage: template <PIPELINE> [validate]</error>');
        return Command::FAILURE;
    }

    private function runValidate(OutputInterface $output): int
    {
        $service = SiteValidateService::create($this->commandConfig(), $this->appRoot());
        return $service->validate($output) ? Command::SUCCESS : Command::FAILURE;
    }
}
