<?php

namespace App\Cli\Command;

use App\Cli\Token\LocalTokenCli;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'token', description: 'Token-Tools (add, list, revoke).')]
final class TokenCommand extends BasePipelineCommand
{
    use PythonRunnerAware;

    private const ACTIONS = ['add', 'list', 'revoke'];

    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('action', InputArgument::REQUIRED, 'Aktion (add|list|revoke)')
            ->addArgument('profile', InputArgument::OPTIONAL, 'Token-Profil')
            ->addArgument('value', InputArgument::OPTIONAL, 'revoke: Hash-Präfix oder Label')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Bezeichnung der neuen Freigabe (nur add)')
            ->addOption('ttl-days', null, InputOption::VALUE_REQUIRED, 'Gültigkeitsdauer in Tagen (nur add, überschreibt Default)')
            ->addOption('no-expiry', null, InputOption::VALUE_NONE, 'Token ohne Ablauf erzeugen (nur add)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = strtolower(trim((string) $input->getArgument('action')));
        if (!in_array($action, self::ACTIONS, true)) {
            $output->writeln('<error>Usage: token <pipeline> add|list PROFIL [--label=] [--ttl-days=] [--no-expiry] | revoke PROFIL WERT</error>');
            return Command::FAILURE;
        }

        $profile = trim((string) $input->getArgument('profile'));
        if ($profile === '') {
            $output->writeln('<error>Profil ist erforderlich.</error>');
            return Command::FAILURE;
        }

        if (!$this->pipelineHasPhase('deploy')) {
            return $this->runLocally($action, $profile, $input, $output);
        }

        return $this->dispatchRemotely($action, $profile, $input, $output);
    }

    private function runLocally(string $action, string $profile, InputInterface $input, OutputInterface $output): int
    {
        $cli = new LocalTokenCli();
        try {
            return match ($action) {
                'add' => $this->runAdd($cli, $profile, $input, $output),
                'list' => $this->runList($cli, $profile, $output),
                'revoke' => $this->runRevoke($cli, $profile, $input, $output),
            };
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function runAdd(LocalTokenCli $cli, string $profile, InputInterface $input, OutputInterface $output): int
    {
        $ttlDays = $this->resolveTtlDays($input, $output);
        if ($ttlDays === false) {
            return Command::FAILURE;
        }

        $expiresAt = $ttlDays !== null ? time() + $ttlDays * 86400 : null;
        $label = $this->resolveLabel($input);

        $token = $cli->add($this->appRoot(), $profile, $expiresAt, $label);
        $output->writeln($token);
        return Command::SUCCESS;
    }

    private function runList(LocalTokenCli $cli, string $profile, OutputInterface $output): int
    {
        $entries = $cli->list($this->appRoot(), $profile);
        if ($entries === []) {
            $output->writeln('Keine Freigaben für dieses Profil.');
            return Command::SUCCESS;
        }
        foreach ($entries as $entry) {
            $output->writeln($this->formatEntry($entry));
        }
        return Command::SUCCESS;
    }

    private function runRevoke(LocalTokenCli $cli, string $profile, InputInterface $input, OutputInterface $output): int
    {
        $identifier = trim((string) $input->getArgument('value'));
        if ($identifier === '') {
            $output->writeln('<error>Hash-Präfix oder Label ist erforderlich.</error>');
            return Command::FAILURE;
        }

        $removed = $cli->revoke($this->appRoot(), $profile, $identifier);
        if ($removed > 0) {
            $output->writeln("{$removed} Freigabe(n) entfernt.");
            return Command::SUCCESS;
        }

        $output->writeln('<error>Keine passende Freigabe gefunden.</error>');
        return Command::FAILURE;
    }

    private function dispatchRemotely(string $action, string $profile, InputInterface $input, OutputInterface $output): int
    {
        $values = $this->getValuesByPhase(['deploy'], $output);
        if ($values === null) {
            return Command::FAILURE;
        }

        try {
            $args = $this->buildTaskArgs($action, $profile, $input, $output);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        if ($args === null) {
            return Command::FAILURE;
        }

        return $this->pythonRunner()->runScript('src/cli/py/task/dispatch.py', $values, $args);
    }

    /** @return list<string>|null null bedeutet: Fehler wurde bereits ausgegeben */
    private function buildTaskArgs(string $action, string $profile, InputInterface $input, OutputInterface $output): ?array
    {
        return match ($action) {
            'add' => $this->buildAddArgs($profile, $input, $output),
            'list' => ['cv_token_list', '--profile', $profile],
            'revoke' => $this->buildRevokeArgs($profile, $input),
        };
    }

    /** @return list<string>|null */
    private function buildAddArgs(string $profile, InputInterface $input, OutputInterface $output): ?array
    {
        $ttlDays = $this->resolveTtlDays($input, $output);
        if ($ttlDays === false) {
            return null;
        }

        $args = ['cv_token_add', '--profile', $profile];

        if ($ttlDays !== null) {
            $args[] = '--expires-at';
            $args[] = (string) (time() + $ttlDays * 86400);
        }

        $label = $this->resolveLabel($input);
        if ($label !== null) {
            $args[] = '--label';
            $args[] = $label;
        }

        return $args;
    }

    private function buildRevokeArgs(string $profile, InputInterface $input): array
    {
        $identifier = trim((string) $input->getArgument('value'));
        if ($identifier === '') {
            throw new \InvalidArgumentException('Hash-Präfix oder Label ist erforderlich.');
        }
        return ['cv_token_revoke', '--profile', $profile, '--identifier', $identifier];
    }

    private function resolveLabel(InputInterface $input): ?string
    {
        $label = trim((string) $input->getOption('label'));
        return $label === '' ? null : $label;
    }

    /**
     * @return positive-int|null|false Anzahl Tage; null = kein Ablauf (--no-expiry);
     *         false = Konfiguration nicht auflösbar (Fehler wurde bereits ausgegeben)
     */
    private function resolveTtlDays(InputInterface $input, OutputInterface $output): int|null|false
    {
        if ((bool) $input->getOption('no-expiry')) {
            return null;
        }

        $ttlDaysOption = $input->getOption('ttl-days');
        if ($ttlDaysOption !== null) {
            return max(1, (int) $ttlDaysOption);
        }

        $config = $this->resolvePipelineConfig('runtime', $output);
        if ($config === null) {
            return false;
        }
        return max(1, (int) $config->get('URL_TOKEN_DEFAULT_TTL_DAYS'));
    }

    /** @param array{hash: string, label: string|null, created_at: int, expires_at: int|null} $entry */
    private function formatEntry(array $entry): string
    {
        return sprintf(
            '%s label=%s created_at=%s expires_at=%s',
            substr($entry['hash'], 0, 12),
            $entry['label'] ?? '-',
            date('Y-m-d', $entry['created_at']),
            $entry['expires_at'] !== null ? date('Y-m-d', $entry['expires_at']) : 'never'
        );
    }
}
